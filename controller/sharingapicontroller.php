<?php
/**
 *
 * (c) Copyright Ascensio System SIA 2022
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
use OCP\Constants;
use OCP\Share\IManager;
use OCP\Files\IRootFolder;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Share\IShare;


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
    private $config;

    /**
     * Share manager
     *
     * @var IManager
    */
    private $shareManager;

    /**
     * @param string $AppName - application name
     * @param IRequest $request - request object
     * @param IRootFolder $root - root folder
     * @param ILogger $logger - logger
     * @param IUserSession $userSession - current user session
     * @param IUserManager $userManager - user manager
     * @param IManager $shareManager - Share manager
     * @param AppConfig $config - application configuration
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
        $result = [];

        $userId = null;
        $user = $this->userSession->getUser();
        if (!empty($user)) {
            $userId = $user->getUID();
        }

        $file = null;
        $userFolder = $this->root->getUserFolder($userId);
        if (!empty($userFolder)) {
            $files = $userFolder->getById($fileId);
            if (!empty($files)) {
                $file = $files[0];
            }
        }

        if (empty($file)) {
            $this->logger->error("getShares: file not found: " . $fileId, ["app" => $this->appName]);
            return new DataResponse($result);
        }

        $extension = pathinfo($file->getName())["extension"];

        $formats = $this->appConfig->FormatsSetting();
        $format = $formats[$extension];

        $shares = $this->shareManager->getSharesBy($userId, IShare::TYPE_USER, $file);
        foreach ($shares as $share) {

            $available = false;
            if (($share->getPermissions() & Constants::PERMISSION_UPDATE) === Constants::PERMISSION_UPDATE) {
                if (array_key_exists(ExtraPermissions::ModifyFilterName, $format)) {
                    $available = true;
                }
            }
            if (($share->getPermissions() & Constants::PERMISSION_UPDATE) !== Constants::PERMISSION_UPDATE) {
                if (array_key_exists(ExtraPermissions::ReviewName, $format)
                    || array_key_exists(ExtraPermissions::CommentName, $format)
                    || array_key_exists(ExtraPermissions::FillFormsName, $format)) {
                    $available = true;
                }
            }

            if (!$available) {
                continue;
            }

            $extra = ExtraPermissions::get($share->getId());
            if (empty($extra)) {
                $extra["id"] = -1;
                $extra["share_id"] = $share->getId();
                $extra["permissions"] = 0;
            }

            $extra["shareWith"] = $share->getSharedWith();
            $extra["shareWithName"] = $share->getSharedWithDisplayName();

            array_push($result, $extra);
        }

        return new DataResponse($result);
    }
}
