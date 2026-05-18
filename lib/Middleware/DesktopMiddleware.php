<?php
/*
 * Copyright (C) Ascensio System SIA, 2009-2026
 *
 * This program is a free software product. You can redistribute it and/or
 * modify it under the terms of the GNU Affero General Public License (AGPL)
 * version 3 as published by the Free Software Foundation, together with the
 * additional terms provided in the LICENSE file.
 *
 * This program is distributed WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. For
 * details, see the GNU AGPL at: https://www.gnu.org/licenses/agpl-3.0.html
 *
 * You can contact Ascensio System SIA by email at info@onlyoffice.com
 * or by postal mail at 20A-6 Ernesta Birznieka-Upisha Street, Riga,
 * LV-1050, Latvia, European Union.
 *
 * The interactive user interfaces in modified versions of the Program
 * are required to display Appropriate Legal Notices in accordance with
 * Section 5 of the GNU AGPL version 3.
 *
 * No trademark rights are granted under this License.
 *
 * All non-code elements of the Product, including illustrations,
 * icon sets, and technical writing content, are licensed under the
 * Creative Commons Attribution-ShareAlike 4.0 International License:
 * https://creativecommons.org/licenses/by-sa/4.0/legalcode
 *
 * This license applies only to such non-code elements and does not
 * modify or replace the licensing terms applicable to the Program's
 * source code, which remains licensed under the GNU Affero General
 * Public License v3.
 *
 * SPDX-License-Identifier: AGPL-3.0-only
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
