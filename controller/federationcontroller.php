<?php
/**
 *
 * (c) Copyright Ascensio System SIA 2021
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 */

namespace OCA\Onlyoffice\Controller;

use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IRequest;
use OCP\ISession;
use OCP\Share\IManager;

use OCA\Files_Sharing\External\Storage as SharingExternalStorage;

use OCA\Onlyoffice\AppConfig;
use OCA\Onlyoffice\DocumentService;
use OCA\Onlyoffice\FileUtility;
use OCA\Onlyoffice\KeyManager;

/**
 * OCS handler
 */
class FederationController extends OCSController {

    /**
     * Logger
     *
     * @var ILogger
     */
    private $logger;

    /**
     * Application configuration
     *
     * @var AppConfig
     */
    public $config;

    /**
     * File utility
     *
     * @var FileUtility
     */
    private $fileUtility;

    /**
     * @param string $AppName - application name
     * @param IRequest $request - request object
     * @param IL10N $trans - l10n service
     * @param ILogger $logger - logger
     * @param IManager $shareManager - Share manager
     * @param IManager $ISession - Session
     */
    public function __construct($AppName,
                                    IRequest $request,
                                    IL10N $trans,
                                    ILogger $logger,
                                    IManager $shareManager,
                                    ISession $session
                                    ) {
        parent::__construct($AppName, $request);

        $this->logger = $logger;

        $this->config = new AppConfig($this->appName);
        $this->fileUtility = new FileUtility($AppName, $trans, $logger, $this->config, $shareManager, $session);
    }

    /**
     * Returns the origin document key for editor
     *
     * @param string $shareToken - access token
     * @param string $path - file path
     *
     * @return DataResponse
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     */
    public function key($shareToken, $path) {
        list ($file, $error, $share) = $this->fileUtility->getFileByToken(null, $shareToken, $path);

        if (isset($error)) {
            $this->logger->error("Federated getFileByToken: $error", ["app" => $this->appName]);
            return new DataResponse(["error" => $error]);
        }

        $key = $this->fileUtility->getKey($file, true);

        $key = DocumentService::GenerateRevisionId($key);

        $this->logger->debug("Federated request get for " . $file->getId() . " key $key", ["app" => $this->appName]);

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
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     */
    public function keylock($shareToken, $path, $lock, $fs) {
        list ($file, $error, $share) = $this->fileUtility->getFileByToken(null, $shareToken, $path);

        if (isset($error)) {
            $this->logger->error("Federated getFileByToken: $error", ["app" => $this->appName]);
            return new DataResponse(["error" => $error]);
        }

        $fileId = $file->getId();

        if ($file->getStorage()->instanceOfStorage(SharingExternalStorage::class)) {
            $isLock = KeyManager::lockFederatedKey($file, $lock, $fs);
            if (!$isLock) {
                return new DataResponse(["error" => "Failed request"]);
            }
        } else {
            KeyManager::lock($fileId, $lock);
            if (!empty($fs)) {
                KeyManager::setForcesave($fileId, $fs);
            }
        }

        $this->logger->debug("Federated request lock for " . $fileId, ["app" => $this->appName]);
        return new DataResponse();
    }
}
