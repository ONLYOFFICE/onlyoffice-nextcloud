<?php
/**
 *
 * (c) Copyright Ascensio System SIA 2025
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

namespace OCA\Onlyoffice\Controller;

use OCA\Onlyoffice\AppConfig;
use OCA\Onlyoffice\ExtraPermissions;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\OCSController;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Share\IManager;
use OCP\Share\IShare;
use Psr\Log\LoggerInterface;

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
     * @var LoggerInterface
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
     * @param LoggerInterface $logger - logger
     * @param IUserSession $userSession - current user session
     * @param IUserManager $userManager - user manager
     * @param IManager $shareManager - Share manager
     * @param AppConfig $appConfig - application configuration
     */
    public function __construct(
        $AppName,
        IRequest $request,
        IRootFolder $root,
        LoggerInterface $logger,
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

        if ($this->appConfig->getAdvanced()
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
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function getShares($fileId) {
        if ($this->extraPermissions === null) {
            $this->logger->debug("extraPermissions isn't init");
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
        $sharesRoom = $this->shareManager->getSharesBy($userId, IShare::TYPE_ROOM, $sourceFile, null, true);
        $sharesLink = $this->shareManager->getSharesBy($userId, IShare::TYPE_LINK, $sourceFile, null, true);
        $shares = array_merge($sharesUser, $sharesGroup, $sharesRoom, $sharesLink);
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
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function setShares($extraId, $shareId, $fileId, $permissions) {
        if ($this->extraPermissions === null) {
            $this->logger->debug("extraPermissions isn't init");
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
            $this->logger->error("setShares: couldn't set extra permissions for: " . $shareId);
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
            $this->logger->error("getFile: $fileId", ["exception" => $e]);
            return null;
        }

        if (empty($files)) {
            $this->logger->error("getFile: file not found: " . $fileId);
            return null;
        }

        return $files[0];
    }
}
