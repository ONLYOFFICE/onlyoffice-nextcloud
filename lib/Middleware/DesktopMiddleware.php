<?php

/**
 *
 * (c) Copyright Ascensio System SIA 2026
 *
 * This program is a free software product.
 * You can redistribute it and/or modify it under the terms of the GNU Affero General Public License
 * (AGPL) version 3 as published by the Free Software Foundation.
 * In accordance with Section 7(a) of the GNU AGPL its Section 15 shall be amended to the effect
 * that Ascensio System SIA expressly excludes the warranty of non-infringement of any third-party rights.
 *
 * This program is distributed WITHOUT ANY WARRANTY;
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * For details, see the GNU AGPL at: http://www.gnu.org/licenses/agpl-3.0.html
 *
 * You can contact Ascensio System SIA at 20A-12 Ernesta Birznieka-Upisha street, Riga, Latvia, EU, LV-1050.
 *
 * The interactive user interfaces in modified source and object code versions of the Program
 * must display Appropriate Legal Notices, as required under Section 5 of the GNU AGPL version 3.
 *
 * Pursuant to Section 7(b) of the License you must retain the original Product logo when distributing the program.
 * Pursuant to Section 7(e) we decline to grant you any rights under trademark law for use of our trademarks.
 *
 * All the Product's GUI elements, including illustrations and icon sets, as well as technical
 * writing content are licensed under the terms of the Creative Commons Attribution-ShareAlike 4.0 International.
 * See the License terms at http://creativecommons.org/licenses/by-sa/4.0/legalcode
 *
 */

namespace OCA\Onlyoffice\Middleware;

use Exception;
use OCA\Onlyoffice\Exceptions\DesktopRedirectException;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Middleware;
use OCP\INavigationManager;
use OCP\IRequest;
use OCP\IURLGenerator;

class DesktopMiddleware extends Middleware {

    public function __construct(
        private readonly IRequest $request,
        private readonly INavigationManager $navigationManager,
        private readonly IURLGenerator $urlGenerator,
    ) {}

    public function beforeController(Controller $controller, string $methodName): void {
        $userAgent = $this->request->getHeader('User-Agent');
        if (!$userAgent || !str_contains($userAgent, 'AscDesktopEditor')) {
            return;
        }

        $defaultAppId = $this->navigationManager->getDefaultEntryIdForUser();
        if ($defaultAppId === 'files') {
            return;
        }

        $entry = $this->navigationManager->get($defaultAppId);
        if ($entry === null) {
            return;
        }

        $entryPath = rtrim(parse_url($entry['href'], PHP_URL_PATH) ?? '', '/');
        $requestPath = rtrim(parse_url($this->request->getRequestUri(), PHP_URL_PATH) ?? '', '/');
        if ($entryPath !== '' && $entryPath === $requestPath) {
            throw new DesktopRedirectException();
        }
    }

    public function afterException(Controller $controller, string $methodName, Exception $exception): Response {
        if ($exception instanceof DesktopRedirectException) {
            return new RedirectResponse($this->urlGenerator->linkToRouteAbsolute('files.view.index'));
        }
        throw $exception;
    }
}
