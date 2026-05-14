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

use OCA\Onlyoffice\DocumentService;
use OCA\Onlyoffice\FileUtility;
use OCA\Onlyoffice\KeyManager;
use OCA\Onlyoffice\RemoteInstance;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/**
 * OCS handler
 */
class FederationController extends OCSController {

    public function __construct(
        string $appName,
        IRequest $request,
        private readonly LoggerInterface $logger,
        private readonly FileUtility $fileUtility,
        private readonly KeyManager $keyManager
    ) {
        parent::__construct($appName, $request);
    }

    /**
     * Returns the origin document key for editor
     *
     * @param string $shareToken - access token
     * @param string $path - file path
     *
     * @return DataResponse
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    #[PublicPage]
    public function key(string $shareToken, string $path): DataResponse {
        [$file, $error, $share] = $this->fileUtility->getFileByToken(null, $shareToken, $path);

        if (isset($error)) {
            $this->logger->error("Federated getFileByToken: $error");
            return new DataResponse(["error" => $error]);
        }

        $key = $this->fileUtility->getKey($file, true);

        $key = DocumentService::generateRevisionId($key);

        $this->logger->debug("Federated request get for " . $file->getId() . " key $key");

        return new DataResponse(["key" => $key]);
    }

    /**
     * Lock the origin document key for editor
     *
     * @param string $shareToken - access token
     * @param string $path - file path
     * @param bool $lock - status
     * @param bool $fs - status
     *
     * @return DataResponse
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    #[PublicPage]
    public function keylock(string $shareToken, string $path, bool $lock, ?bool $fs): DataResponse {
        [$file, $error, $share] = $this->fileUtility->getFileByToken(null, $shareToken, $path);

        if (isset($error)) {
            $this->logger->error("Federated getFileByToken: $error");
            return new DataResponse(["error" => $error]);
        }

        $fileId = $file->getId();

        if (RemoteInstance::isRemoteFile($file)) {
            $isLock = RemoteInstance::lockRemoteKey($file, $lock, $fs);
            if (!$isLock) {
                return new DataResponse(["error" => "Failed request"]);
            }
        } else {
            $this->keyManager->lock($fileId, $lock);
            if (!empty($fs)) {
                $this->keyManager->setForcesave($fileId, $fs);
            }
        }

        $this->logger->debug("Federated request lock for " . $fileId);
        return new DataResponse();
    }

    /**
     * Health check instance
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    #[PublicPage]
    public function healthcheck(): DataResponse {
        $this->logger->debug("Federated healthcheck");

        return new DataResponse(["alive" => true]);
    }
}
