<?php
/**
 *
 * (c) Copyright Ascensio System SIA 2023
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

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IRequest;
use OCP\ISession;
use OCP\Constants;
use OCP\Share\IManager;
use OCP\Files\IRootFolder;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Files\File;
use OCP\Share\IShare;
use OCP\Share\Exceptions\ShareNotFound;


use OCA\Onlyoffice\AppConfig;
use OCA\Onlyoffice\DocumentService;
use OCA\Onlyoffice\FileUtility;
use OCA\Onlyoffice\KeyManager;
use OCA\Onlyoffice\ExtraPermissions;

/**
 * OCS handler
 */
class SharingApiController extends OCSController {

    /**
     * Current user session
     *
     * @var IUserSession
     */
    private $userSession;

    /**
     * User manager
     *
     * @var IUserManager
     */
    private $userManager;

    /**
     * Root folder
     *
     * @var IRootFolder
     */
    private $root;

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
    private $appConfig;

    /**
     * Share manager
     *
     * @var IManager
    */
    private $shareManager;

    /**
     * Extra permissions
     *
     * @var ExtraPermissions
    */
    private $extraPermissions;

    /**
     * @param string $AppName - application name
     * @param IRequest $request - request object
     * @param IRootFolder $root - root folder
     * @param ILogger $logger - logger
     * @param IUserSession $userSession - current user session
     * @param IUserManager $userManager - user manager
     * @param IManager $shareManager - Share manager
     * @param AppConfig $appConfig - application configuration
     */
    public function __construct($AppName,
                                    IRequest $request,
                                    IRootFolder $root,
                                    ILogger $logger,
                                    IUserSession $userSession,
                                    IUserManager $userManager,
                                    IManager $shareManager,
                                    AppConfig $appConfig
                                    ) {
        parent::__construct($AppName, $request);

        $this->root = $root;
        $this->logger = $logger;
        $this->userSession = $userSession;
        $this->userManager = $userManager;
        $this->shareManager = $shareManager;
        $this->appConfig = $appConfig;

        if ($this->appConfig->GetAdvanced()
            && \OC::$server->getAppManager()->isInstalled("files_sharing")) {
            $this->extraPermissions = new ExtraPermissions($AppName, $logger, $shareManager, $appConfig);
        }
    }

    /**
     * Get shares for file
     *
     * @param integer $fileId - file identifier
     *
     * @return DataResponse
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getShares($fileId) {
        if ($this->extraPermissions === null) {
            $this->logger->debug("extraPermissions isn't init", ["app" => $this->appName]);
            return new DataResponse([], Http::STATUS_BAD_REQUEST);
        }

        $user = $this->userSession->getUser();
        $userId = $user->getUID();

        $sourceFile = $this->getFile($fileId, $userId);
        $fileStorage = $sourceFile->getStorage();
        if ($fileStorage->instanceOfStorage("\OCA\Files_Sharing\SharedStorage")) {
            return new DataResponse([]);
        }

        $sharesUser = $this->shareManager->getSharesBy($userId, IShare::TYPE_USER, $sourceFile, null, true);
        $sharesGroup = $this->shareManager->getSharesBy($userId, IShare::TYPE_GROUP, $sourceFile, null, true);
        $shares = array_merge($sharesUser, $sharesGroup);
        $extras = $this->extraPermissions->getExtras($shares, $sourceFile);

        return new DataResponse($extras);
    }

    /**
     * Set shares for file
     *
     * @param integer $extraId - extra permission identifier
     * @param integer $shareId - share identifier
     * @param integer $fileId - file identifier
     * @param integer $permissions - permissions value
     *
     * @return DataResponse
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function setShares($extraId, $shareId, $fileId, $permissions) {
        if ($this->extraPermissions === null) {
            $this->logger->debug("extraPermissions isn't init", ["app" => $this->appName]);
            return new DataResponse([], Http::STATUS_BAD_REQUEST);
        }

        $user = $this->userSession->getUser();
        $userId = $user->getUID();

        $sourceFile = $this->getFile($fileId, $userId);
        $fileStorage = $sourceFile->getStorage();
        if ($fileStorage->instanceOfStorage("\OCA\Files_Sharing\SharedStorage")) {
            return new DataResponse([], Http::STATUS_BAD_REQUEST);
        }

        if (!$this->extraPermissions->setExtra($shareId, $permissions, $extraId)) {
            $this->logger->error("setShares: couldn't set extra permissions for: " . $shareId, ["app" => $this->appName]);
            return new DataResponse([], Http::STATUS_BAD_REQUEST);
        }

        $extra = $this->extraPermissions->getExtra($shareId);

        return new DataResponse($extra);
    }

    /**
     * Get source file
     *
     * @param integer $fileId - file identifier
     * @param string $userId - user identifier
     *
     * @return File
     */
    private function getFile($fileId, $userId) {
        try {
            $folder = $this->root->getUserFolder($userId);
            $files = $folder->getById($fileId);
        } catch (\Exception $e) {
            $this->logger->logException($e, ["message" => "getFile: $fileId", "app" => $this->appName]);
            return null;
        }

        if (empty($files)) {
            $this->logger->error("getFile: file not found: " . $fileId, ["app" => $this->appName]);
            return null;
        }

        return $files[0];
    }
}
