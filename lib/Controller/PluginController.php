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

namespace OCA\Onlyoffice\Controller;

use OCA\Onlyoffice\Crypt;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

/**
 * Controller serving editor plugins to the document server.
 */
class PluginController extends Controller {

    public function __construct(
        string $appName,
        IRequest $request,
        private readonly IUserManager $userManager,
        private readonly IL10N $trans,
        private readonly LoggerInterface $logger,
        private readonly Crypt $crypt
    ) {
        parent::__construct($appName, $request);
    }

    /**
     * Provide plugin data to the document server
     *
     * @param string $code - plugin code
     *
     * @return JSONResponse
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    #[PublicPage]
    public function data(?string $code = null): JSONResponse {
        if (empty($code)) {
            $this->logger->error("Plugin data without code");
            return new JSONResponse(["message" => $this->trans->t("Access denied")], Http::STATUS_FORBIDDEN);
        }

        [$hashData, $error] = $this->crypt->readHash($code);
        if ($hashData === null) {
            $this->logger->error("Plugin data with empty or not correct hash: $error");
            return new JSONResponse(["message" => $this->trans->t("Access denied")], Http::STATUS_FORBIDDEN);
        }
        if ($hashData->action !== "data") {
            $this->logger->error("Plugin data with other action");
            return new JSONResponse(["message" => $this->trans->t("Invalid request")], Http::STATUS_BAD_REQUEST);
        }

        $userId = $hashData->userId ?? null;
        $user = !empty($userId) ? $this->userManager->get($userId) : null;
        if ($user === null) {
            $this->logger->error("Plugin data for unknown user: $userId");
            return new JSONResponse(["message" => $this->trans->t("User not found")], Http::STATUS_NOT_FOUND);
        }

        return new JSONResponse([
            'data' => [
                'name' => $user->getDisplayName(),
                'email' => $user->getEmailAddress(),
            ],
            'code' => $this->crypt->getHash(["userId" => $userId, "action" => "data"]),
        ]);
    }
}
