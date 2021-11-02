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

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\Template\PublicTemplateResponse;
use OCP\AppFramework\QueryException;
use OCP\Constants;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\Files\NotPermittedException;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IRequest;
use OCP\ISession;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\IGroupManager;
use OCP\Share\IManager;
use OCP\Share\IShare;

use OCA\Files\Helper;
use OCA\Files_Versions\Versions\IVersionManager;

use OCA\Onlyoffice\AppConfig;
use OCA\Onlyoffice\Crypt;
use OCA\Onlyoffice\DocumentService;
use OCA\Onlyoffice\FileUtility;
use OCA\Onlyoffice\TemplateManager;
use OCA\Onlyoffice\FileVersions;

/**
 * Controller with the main functions
 */
class EditorController extends Controller {

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
     * Url generator service
     *
     * @var IURLGenerator
     */
    private $urlGenerator;

    /**
     * l10n service
     *
     * @var IL10N
     */
    private $trans;

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
     * Hash generator
     *
     * @var Crypt
     */
    private $crypt;

    /**
     * File utility
     *
     * @var FileUtility
     */
    private $fileUtility;

    /**
     * File version manager
     *
     * @var IVersionManager
    */
    private $versionManager;

    /**
     * Share manager
     *
     * @var IManager
     */
    private $shareManager;

    /**
     * Group manager
     *
     * @var IGroupManager
     */
    private $groupManager;

    /**
     * @param string $AppName - application name
     * @param IRequest $request - request object
     * @param IRootFolder $root - root folder
     * @param IUserSession $userSession - current user session
     * @param IUserManager $userManager - user manager
     * @param IURLGenerator $urlGenerator - url generator service
     * @param IL10N $trans - l10n service
     * @param ILogger $logger - logger
     * @param AppConfig $config - application configuration
     * @param Crypt $crypt - hash generator
     * @param IManager $shareManager - Share manager
     * @param ISession $session - Session
     * @param IGroupManager $groupManager - group Manager
     */
    public function __construct($AppName,
                                    IRequest $request,
                                    IRootFolder $root,
                                    IUserSession $userSession,
                                    IUserManager $userManager,
                                    IURLGenerator $urlGenerator,
                                    IL10N $trans,
                                    ILogger $logger,
                                    AppConfig $config,
                                    Crypt $crypt,
                                    IManager $shareManager,
                                    ISession $session,
                                    IGroupManager $groupManager
                                    ) {
        parent::__construct($AppName, $request);

        $this->userSession = $userSession;
        $this->userManager = $userManager;
        $this->root = $root;
        $this->urlGenerator = $urlGenerator;
        $this->trans = $trans;
        $this->logger = $logger;
        $this->config = $config;
        $this->crypt = $crypt;
        $this->shareManager = $shareManager;
        $this->groupManager = $groupManager;

        if (\OC::$server->getAppManager()->isInstalled("files_versions")) {
            try {
                $this->versionManager = \OC::$server->query(IVersionManager::class);
            } catch (QueryException $e) {
                $this->logger->logException($e, ["message" => "VersionManager init error", "app" => $this->appName]);
            }
        }

        $this->fileUtility = new FileUtility($AppName, $trans, $logger, $config, $shareManager, $session);
    }

    /**
     * Create new file in folder
     *
     * @param string $name - file name
     * @param string $dir - folder path
     * @param string $templateId - file identifier
     * @param string $shareToken - access token
     *
     * @return array
     *
     * @NoAdminRequired
     * @PublicPage
     */
    public function create($name, $dir, $templateId = null, $shareToken = null) {
        $this->logger->debug("Create: $name", ["app" => $this->appName]);

        if (empty($shareToken) && !$this->config->isUserAllowedToUse()) {
            return ["error" => $this->trans->t("Not permitted")];
        }

        if (empty($name)) {
            $this->logger->error("File name for creation was not found: $name", ["app" => $this->appName]);
            return ["error" => $this->trans->t("Template not found")];
        }

        if (empty($shareToken)) {
            $userId = $this->userSession->getUser()->getUID();
            $userFolder = $this->root->getUserFolder($userId);
        } else {
            list ($userFolder, $error, $share) = $this->fileUtility->getNodeByToken($shareToken);

            if (isset($error)) {
                $this->logger->error("Create: $error", ["app" => $this->appName]);
                return ["error" => $error];
            }

            if ($userFolder instanceof File) {
                return ["error" => $this->trans->t("You don't have enough permission to create")];
            }

            if (!empty($shareToken) && ($share->getPermissions() & Constants::PERMISSION_CREATE) === 0) {
                $this->logger->error("Create in public folder without access", ["app" => $this->appName]);
                return ["error" => $this->trans->t("You do not have enough permissions to view the file")];
            }
        }

        $folder = $userFolder->get($dir);

        if ($folder === null) {
            $this->logger->error("Folder for file creation was not found: $dir", ["app" => $this->appName]);
            return ["error" => $this->trans->t("The required folder was not found")];
        }
        if (!($folder->isCreatable() && $folder->isUpdateable())) {
            $this->logger->error("Folder for file creation without permission: $dir", ["app" => $this->appName]);
            return ["error" => $this->trans->t("You don't have enough permission to create")];
        }

        if (empty($templateId)) {
            $template = TemplateManager::GetEmptyTemplate($name);
        } else {
            $templateFile = TemplateManager::GetTemplate($templateId);
            if ($templateFile !== null) {
                $template = $templateFile->getContent();
            }
        }

        if (!$template) {
            $this->logger->error("Template for file creation not found: $name ($templateId)", ["app" => $this->appName]);
            return ["error" => $this->trans->t("Template not found")];
        }

        $name = $folder->getNonExistingName($name);

        try {
            if (\version_compare(\implode(".", \OCP\Util::getVersion()), "19", "<")) {
                $file = $folder->newFile($name);

                $file->putContent($template);
            } else {
                $file = $folder->newFile($name, $template);
            }
        } catch (NotPermittedException $e) {
            $this->logger->logException($e, ["message" => "Can't create file: $name", "app" => $this->appName]);
            return ["error" => $this->trans->t("Can't create file")];
        }

        $fileInfo = $file->getFileInfo();

        $result = Helper::formatFileInfo($fileInfo);
        return $result;
    }

    /**
     * Create new file in folder from editor
     *
     * @param string $name - file name
     * @param string $dir - folder path
     * @param string $templateId - file identifier
     *
     * @return TemplateResponse|RedirectResponse
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function createNew($name, $dir, $templateId = null) {
        $this->logger->debug("Create from editor: $name in $dir", ["app" => $this->appName]);

        $result = $this->create($name, $dir, $templateId);
        if (isset($result["error"])) {
            return $this->renderError($result["error"]);
        }

        $openEditor = $this->urlGenerator->linkToRouteAbsolute($this->appName . ".editor.index", ["fileId" => $result["id"]]);
        return new RedirectResponse($openEditor);
    }

    /**
     * Get users
     *
     * @param $fileId - file identifier
     *
     * @return array
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function users($fileId) {
        $this->logger->debug("Search users", ["app" => $this->appName]);
        $result = [];
        $currentUserGroups = [];

        if (!$this->config->isUserAllowedToUse()) {
            return $result;
        }

        if (!$this->shareManager->allowEnumeration()) {
            return $result;
        }

        $autocompleteMemberGroup = false;
        if ($this->shareManager->limitEnumerationToGroups()) {
            $autocompleteMemberGroup = true;
        }

        $currentUser = $this->userSession->getUser();
        $currentUserId = $currentUser->getUID();

        $currentUserGroups = $this->groupManager->getUserGroupIds($currentUser);

        $excludedGroups = $this->getShareExcludedGroups();
        $isMemberExcludedGroups = true;
        if (count(array_intersect($currentUserGroups, $excludedGroups)) !== count($currentUserGroups)) {
            $isMemberExcludedGroups = false;
        }

        list ($file, $error, $share) = $this->getFile($currentUserId, $fileId);
        if (isset($error)) {
            $this->logger->error("Users: $fileId $error", ["app" => $this->appName]);
            return $result;
        }

        $canShare = (($file->getPermissions() & Constants::PERMISSION_SHARE) === Constants::PERMISSION_SHARE)
                    && !$isMemberExcludedGroups;

        $shareMemberGroups = $this->shareManager->shareWithGroupMembersOnly();

        $all = false;
        $users = [];
        if ($canShare) {
            if ($shareMemberGroups || $autocompleteMemberGroup) {
                foreach ($currentUserGroups as $currentUserGroup) {
                    $group = $this->groupManager->get($currentUserGroup);
                    foreach ($group->getUsers() as $user) {
                        if (!in_array($user, $users)) {
                            array_push($users, $user);
                        }
                    }
                }
            } else {
                $users = $this->userManager->search("");
                $all = true;
            }
        }

        if (!$all) {
            $accessList = $this->shareManager->getAccessList($file);
            foreach ($accessList["users"] as $accessUser) {
                $user = $this->userManager->get($accessUser);
                if (!in_array($user, $users)) {
                    array_push($users, $this->userManager->get($accessUser));
                }
            }
        }

        foreach ($users as $user) {
            $email = $user->getEMailAddress();
            if ($user->getUID() != $currentUserId && !empty($email)) {
                array_push($result, [
                    "email" => $email,
                    "name" => $user->getDisplayName()
                ]);
            }
        }

        return $result;
    }

    /**
     * Send notify about mention
     *
     * @param int $fileId - file identifier
     * @param string $anchor - the anchor on target content
     * @param string $comment - comment
     * @param array $emails - emails array to whom to send notify
     *
     * @return array
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function mention($fileId, $anchor, $comment, $emails) {
        $this->logger->debug("mention: from $fileId to " . json_encode($emails), ["app" => $this->appName]);

        if (!$this->config->isUserAllowedToUse()) {
            return ["error" => $this->trans->t("Not permitted")];
        }

        if (empty($emails)) {
            return ["error" => $this->trans->t("Failed to send notification")];
        }

        $recipientIds = [];
        foreach ($emails as $email) {
            $recipients = $this->userManager->getByEmail($email);
            foreach ($recipients as $recipient) {
                $recipientId = $recipient->getUID(); 
                if (!in_array($recipientId, $recipientIds)) {
                    array_push($recipientIds, $recipientId);
                }
            }
        }

        $user = $this->userSession->getUser();
        $userId = null;
        if (!empty($user)) {
            $userId = $user->getUID();
        }

        $currentUserGroups = $this->groupManager->getUserGroupIds($user);

        $excludedGroups = $this->getShareExcludedGroups();
        $isMemberExcludedGroups = true;
        if (count(array_intersect($currentUserGroups, $excludedGroups)) !== count($currentUserGroups)) {
            $isMemberExcludedGroups = false;
        }

        list ($file, $error, $share) = $this->getFile($userId, $fileId);
        if (isset($error)) {
            $this->logger->error("Mention: $fileId $error", ["app" => $this->appName]);
            return ["error" => $this->trans->t("Failed to send notification")];
        }

        $notificationManager = \OC::$server->getNotificationManager();
        $notification = $notificationManager->createNotification();
        $notification->setApp($this->appName)
            ->setDateTime(new \DateTime())
            ->setObject("mention", $comment)
            ->setSubject("mention_info", [
                "notifierId" => $userId,
                "fileId" => $file->getId(),
                "fileName" => $file->getName(),
                "anchor" => $anchor
            ]);

        $shareMemberGroups = $this->shareManager->shareWithGroupMembersOnly();
        $canShare = (($file->getPermissions() & Constants::PERMISSION_SHARE) === Constants::PERMISSION_SHARE)
                    && !$isMemberExcludedGroups;

        $accessList = $this->shareManager->getAccessList($file);

        foreach ($recipientIds as $recipientId) {
            if (!in_array($recipientId, $accessList["users"])) {
                if (!$canShare) {
                    continue;
                }
                if ($shareMemberGroups) {
                    $recipient = $this->userManager->get($recipientId);
                    $recipientGroups = $this->groupManager->getUserGroupIds($recipient);
                    if (empty(array_intersect($currentUserGroups, $recipientGroups))) {
                        continue;
                    }
                }

                $share = $this->shareManager->newShare();
                $share->setNode($file)
                    ->setShareType(IShare::TYPE_USER)
                    ->setSharedBy($userId)
                    ->setSharedWith($recipientId)
                    ->setShareOwner($userId)
                    ->setPermissions(Constants::PERMISSION_READ);

                $this->shareManager->createShare($share);

                $this->logger->debug("mention: share $fileId to $recipientId", ["app" => $this->appName]);
            }

            $notification->setUser($recipientId);

            $notificationManager->notify($notification);
        }

        return ["message" => $this->trans->t("Notification sent successfully")];
    }

    /**
     * Conversion file to Office Open XML format
     *
     * @param integer $fileId - file identifier
     * @param string $shareToken - access token
     *
     * @return array
     *
     * @NoAdminRequired
     * @PublicPage
     */
    public function convert($fileId, $shareToken = null) {
        $this->logger->debug("Convert: $fileId", ["app" => $this->appName]);

        if (empty($shareToken) && !$this->config->isUserAllowedToUse()) {
            return ["error" => $this->trans->t("Not permitted")];
        }

        $user = $this->userSession->getUser();
        $userId = null;
        if (!empty($user)) {
            $userId = $user->getUID();
        }

        list ($file, $error, $share) = empty($shareToken) ? $this->getFile($userId, $fileId) : $this->fileUtility->getFileByToken($fileId, $shareToken);

        if (isset($error)) {
            $this->logger->error("Convertion: $fileId $error", ["app" => $this->appName]);
            return ["error" => $error];
        }

        if (!empty($shareToken) && ($share->getPermissions() & Constants::PERMISSION_CREATE) === 0) {
            $this->logger->error("Convertion in public folder without access: $fileId", ["app" => $this->appName]);
            return ["error" => $this->trans->t("You do not have enough permissions to view the file")];
        }

        $fileName = $file->getName();
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $format = $this->config->FormatsSetting()[$ext];
        if (!isset($format)) {
            $this->logger->info("Format for convertion not supported: $fileName", ["app" => $this->appName]);
            return ["error" => $this->trans->t("Format is not supported")];
        }

        if (!isset($format["conv"]) || $format["conv"] !== true) {
            $this->logger->info("Conversion is not required: $fileName", ["app" => $this->appName]);
            return ["error" => $this->trans->t("Conversion is not required")];
        }

        $internalExtension = "docx";
        switch ($format["type"]) {
            case "cell":
                $internalExtension = "xlsx";
                break;
            case "slide":
                $internalExtension = "pptx";
                break;
        }

        $newFileUri = null;
        $documentService = new DocumentService($this->trans, $this->config);
        $key = $this->fileUtility->getKey($file);
        $fileUrl = $this->getUrl($file, $user, $shareToken);
        try {
            $newFileUri = $documentService->GetConvertedUri($fileUrl, $ext, $internalExtension, $key);
        } catch (\Exception $e) {
            $this->logger->logException($e, ["message" => "GetConvertedUri: " . $file->getId(), "app" => $this->appName]);
            return ["error" => $e->getMessage()];
        }

        $folder = $file->getParent();
        if (!($folder->isCreatable() && $folder->isUpdateable())) {
            $folder = $this->root->getUserFolder($userId);
        }

        try {
            $newData = $documentService->Request($newFileUri);
        } catch (\Exception $e) {
            $this->logger->logException($e, ["message" => "Failed to download converted file", "app" => $this->appName]);
            return ["error" => $this->trans->t("Failed to download converted file")];
        }

        $fileNameWithoutExt = substr($fileName, 0, strlen($fileName) - strlen($ext) - 1);
        $newFileName = $folder->getNonExistingName($fileNameWithoutExt . "." . $internalExtension);

        try {
            $file = $folder->newFile($newFileName);

            $file->putContent($newData);
        } catch (NotPermittedException $e) {
            $this->logger->logException($e, ["message" => "Can't create file: $newFileName", "app" => $this->appName]);
            return ["error" => $this->trans->t("Can't create file")];
        }

        $fileInfo = $file->getFileInfo();

        $result = Helper::formatFileInfo($fileInfo);
        return $result;
    }

    /**
     * Save file to folder
     *
     * @param string $name - file name
     * @param string $dir - folder path
     * @param string $url - file url
     *
     * @return array
     *
     * @NoAdminRequired
     */
    public function save($name, $dir, $url) {
        $this->logger->debug("Save: $name", ["app" => $this->appName]);

        if (!$this->config->isUserAllowedToUse()) {
            return ["error" => $this->trans->t("Not permitted")];
        }

        $userId = $this->userSession->getUser()->getUID();
        $userFolder = $this->root->getUserFolder($userId);

        $folder = $userFolder->get($dir);

        if ($folder === null) {
            $this->logger->error("Folder for saving file was not found: $dir", ["app" => $this->appName]);
            return ["error" => $this->trans->t("The required folder was not found")];
        }
        if (!($folder->isCreatable() && $folder->isUpdateable())) {
            $this->logger->error("Folder for saving file without permission: $dir", ["app" => $this->appName]);
            return ["error" => $this->trans->t("You don't have enough permission to create")];
        }

        $url = $this->config->ReplaceDocumentServerUrlToInternal($url);

        try {
            $documentService = new DocumentService($this->trans, $this->config);
            $newData = $documentService->Request($url);
        } catch (\Exception $e) {
            $this->logger->logException($e, ["message" => "Failed to download file for saving: $url", "app" => $this->appName]);
            return ["error" => $this->trans->t("Download failed")];
        }

        $name = $folder->getNonExistingName($name);

        try {
            $file = $folder->newFile($name);

            $file->putContent($newData);
        } catch (NotPermittedException $e) {
            $this->logger->logException($e, ["message" => "Can't save file: $name", "app" => $this->appName]);
            return ["error" => $this->trans->t("Can't create file")];
        }

        $fileInfo = $file->getFileInfo();

        $result = Helper::formatFileInfo($fileInfo);
        return $result;
    }

    /**
     * Get versions history for file
     *
     * @param integer $fileId - file identifier
     * @param string $shareToken - access token
     *
     * @return array
     *
     * @NoAdminRequired
     */
    public function history($fileId, $shareToken = null) {
        $this->logger->debug("Request history for: $fileId", ["app" => $this->appName]);

        if (empty($shareToken) && !$this->config->isUserAllowedToUse()) {
            return ["error" => $this->trans->t("Not permitted")];
        }

        $history = [];

        $user = $this->userSession->getUser();
        $userId = null;
        if (!empty($user)) {
            $userId = $user->getUID();
        }

        list ($file, $error, $share) = empty($shareToken) ? $this->getFile($userId, $fileId) : $this->fileUtility->getFileByToken($fileId, $shareToken);

        if (isset($error)) {
            $this->logger->error("History: $fileId $error", ["app" => $this->appName]);
            return ["error" => $error];
        }

        if ($fileId === 0) {
            $fileId = $file->getId();
        }

        $ownerId = null;
        $owner = $file->getFileInfo()->getOwner();
        if ($owner !== null) {
            $ownerId = $owner->getUID();
        }

        $versions = array();
        if ($this->versionManager !== null
            && $owner !== null) {
            $versions = array_reverse($this->versionManager->getVersionsForFile($owner, $file->getFileInfo()));
        }

        $prevVersion = "";
        $versionNum = 0;
        foreach ($versions as $version) {
            $versionNum = $versionNum + 1;

            $key = $this->fileUtility->getVersionKey($version);
            $key = DocumentService::GenerateRevisionId($key);

            $historyItem = [
                "created" => $version->getTimestamp(),
                "key" => $key,
                "version" => $versionNum
            ];

            $versionId = $version->getRevisionId();

            $author = FileVersions::getAuthor($ownerId, $fileId, $versionId);
            $authorId = $author !== null ? $author["id"] : $ownerId;
            $authorName = $author !== null ? $author["name"] : $owner->getDisplayName();

            $historyItem["user"] = [
                "id" => $this->buildUserId($authorId),
                "name" => $authorName
            ];

            $historyData = FileVersions::getHistoryData($ownerId, $fileId, $versionId, $prevVersion);
            if ($historyData !== null) {
                $historyItem["changes"] = $historyData["changes"];
                $historyItem["serverVersion"] = $historyData["serverVersion"];
            }

            $prevVersion = $versionId;

            array_push($history, $historyItem);
        }

        $key = $this->fileUtility->getKey($file, true);
        $key = DocumentService::GenerateRevisionId($key);

        $historyItem = [
            "created" => $file->getMTime(),
            "key" => $key,
            "version" => $versionNum + 1
        ];

        $versionId = $file->getFileInfo()->getMtime();

        $author = FileVersions::getAuthor($ownerId, $fileId, $versionId);
        if ($author !== null) {
            $historyItem["user"] = [
                "id" => $this->buildUserId($author["id"]),
                "name" => $author["name"]
            ];
        } else if ($owner !== null) {
            $historyItem["user"] = [
                "id" => $this->buildUserId($ownerId),
                "name" => $owner->getDisplayName()
            ];
        }

        $historyData = FileVersions::getHistoryData($ownerId, $fileId, $versionId, $prevVersion);
        if ($historyData !== null) {
            $historyItem["changes"] = $historyData["changes"];
            $historyItem["serverVersion"] = $historyData["serverVersion"];
        }

        array_push($history, $historyItem);

        return $history;
    }

    /**
     * Get file attributes of specific version
     *
     * @param integer $fileId - file identifier
     * @param integer $version - file version
     * @param string $shareToken - access token
     *
     * @return array
     *
     * @NoAdminRequired
     */
    public function version($fileId, $version, $shareToken = null) {
        $this->logger->debug("Request version for: $fileId ($version)", ["app" => $this->appName]);

        if (empty($shareToken) && !$this->config->isUserAllowedToUse()) {
            return ["error" => $this->trans->t("Not permitted")];
        }

        $version = empty($version) ? null : $version;

        $user = $this->userSession->getUser();
        $userId = null;
        if (!empty($user)) {
            $userId = $user->getUID();
        }

        list ($file, $error, $share) = empty($shareToken) ? $this->getFile($userId, $fileId) : $this->fileUtility->getFileByToken($fileId, $shareToken);

        if (isset($error)) {
            $this->logger->error("History: $fileId $error", ["app" => $this->appName]);
            return ["error" => $error];
        }

        if ($fileId === 0) {
            $fileId = $file->getId();
        }

        $owner = null;
        $ownerId = null;
        $versions = array();
        if ($this->versionManager !== null) {
            $owner = $file->getFileInfo()->getOwner();
            if ($owner !== null) {
                $ownerId = $owner->getUID();
                $versions = array_reverse($this->versionManager->getVersionsForFile($owner, $file->getFileInfo()));
            }
        }

        $key = null;
        $fileUrl = null;
        $versionId = null;
        if ($version > count($versions)) {
            $key = $this->fileUtility->getKey($file, true);
            $versionId = $file->getFileInfo()->getMtime();

            $fileUrl = $this->getUrl($file, $user, $shareToken);
        } else {
            $fileVersion = array_values($versions)[$version - 1];

            $key = $this->fileUtility->getVersionKey($fileVersion);
            $versionId = $fileVersion->getRevisionId();

            $fileUrl = $this->getUrl($file, $user, $shareToken, $version);
        }
        $key = DocumentService::GenerateRevisionId($key);

        $result = [
            "url" => $fileUrl,
            "version" => $version,
            "key" => $key
        ];

        if ($version > 1
            && count($versions) >= $version - 1
            && FileVersions::hasChanges($ownerId, $fileId, $versionId)) {

            $changesUrl = $this->getUrl($file, $user, $shareToken, $version, true);
            $result["changesUrl"] = $changesUrl;

            $prevVersion = array_values($versions)[$version - 2];
            $prevVersionKey = $this->fileUtility->getVersionKey($prevVersion);
            $prevVersionKey = DocumentService::GenerateRevisionId($prevVersionKey);

            $prevVersionUrl = $this->getUrl($file, $user, $shareToken, $version - 1);

            $result["previous"] = [
                "key" => $prevVersionKey,
                "url" => $prevVersionUrl
            ];
        }

        if (!empty($this->config->GetDocumentServerSecret())) {
            $token = \Firebase\JWT\JWT::encode($result, $this->config->GetDocumentServerSecret());
            $result["token"] = $token;
        }

        return $result;
    }

    /**
     * Restore file version
     *
     * @param integer $fileId - file identifier
     * @param integer $version - file version
     * @param string $shareToken - access token
     *
     * @return array
     *
     * @NoAdminRequired
     * @PublicPage
     */
    public function restore($fileId, $version, $shareToken = null) {
        $this->logger->debug("Request restore version for: $fileId ($version)", ["app" => $this->appName]);

        if (empty($shareToken) && !$this->config->isUserAllowedToUse()) {
            return ["error" => $this->trans->t("Not permitted")];
        }

        $version = empty($version) ? null : $version;

        $user = $this->userSession->getUser();
        $userId = null;
        if (!empty($user)) {
            $userId = $user->getUID();
        }

        list ($file, $error, $share) = empty($shareToken) ? $this->getFile($userId, $fileId) : $this->fileUtility->getFileByToken($fileId, $shareToken);

        if (isset($error)) {
            $this->logger->error("Restore: $fileId $error", ["app" => $this->appName]);
            return ["error" => $error];
        }

        if ($fileId === 0) {
            $fileId = $file->getId();
        }

        $owner = null;
        $versions = array();
        if ($this->versionManager !== null) {
            $owner = $file->getFileInfo()->getOwner();
            if ($owner !== null) {
                $versions = array_reverse($this->versionManager->getVersionsForFile($owner, $file->getFileInfo()));
            }

            if (count($versions) >= $version) {
                $fileVersion = array_values($versions)[$version - 1];
                $this->versionManager->rollback($fileVersion);
            }
        }

        return $this->history($fileId, $shareToken);
    }

    /**
     * Get presigned url to file
     *
     * @param string $filePath - file path
     *
     * @return array
     *
     * @NoAdminRequired
     */
    public function url($filePath) {
        $this->logger->debug("Request url for: $filePath", ["app" => $this->appName]);

        if (!$this->config->isUserAllowedToUse()) {
            return ["error" => $this->trans->t("Not permitted")];
        }

        $user = $this->userSession->getUser();
        $userId = $user->getUID();
        $userFolder = $this->root->getUserFolder($userId);

        $file = $userFolder->get($filePath);

        if ($file === null) {
            $this->logger->error("File for generate presigned url was not found: $filePath", ["app" => $this->appName]);
            return ["error" => $this->trans->t("File not found")];
        }
        if (!$file->isReadable()) {
            $this->logger->error("Folder for saving file without permission: $filePath", ["app" => $this->appName]);
            return ["error" => $this->trans->t("You do not have enough permissions to view the file")];
        }

        $fileName = $file->getName();
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $fileUrl = $this->getUrl($file, $user);

        $result = [
            "fileType" => $ext,
            "url" => $fileUrl
        ];

        if (!empty($this->config->GetDocumentServerSecret())) {
            $token = \Firebase\JWT\JWT::encode($result, $this->config->GetDocumentServerSecret());
            $result["token"] = $token;
        }

        return $result;
    }

    /**
     * Download method
     *
     * @param int $fileId - file identifier
     * @param string $toExtension - file extension to download
     * @param bool $template - file extension to download
     *
     * @return DataDownloadResponse|TemplateResponse
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function download($fileId, $toExtension = null, $template = false) {
        $this->logger->debug("Download: $fileId $toExtension", ["app" => $this->appName]);

        if (!$this->config->isUserAllowedToUse()) {
            return $this->renderError($this->trans->t("Not permitted"));
        }

        if ($template) {
            $templateFile = TemplateManager::GetTemplate($fileId);
            if (empty($templateFile)) {
                $this->logger->info("Download: template not found: $fileId", ["app" => $this->appName]);
                return $this->renderError($this->trans->t("File not found"));
            }

            $file = $templateFile;
        } else {
            $user = $this->userSession->getUser();
            $userId = null;
            if (!empty($user)) {
                $userId = $user->getUID();
            }

            list ($file, $error, $share) = $this->getFile($userId, $fileId);

            if (isset($error)) {
                $this->logger->error("Download: $fileId $error", ["app" => $this->appName]);
                return $this->renderError($error);
            }
        }

        $fileName = $file->getName();
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $toExtension = strtolower($toExtension);

        if ($toExtension === null
            || $ext === $toExtension
            || $template) {
            return new DataDownloadResponse($file->getContent(), $fileName, $file->getMimeType());
        }

        $newFileUri = null;
        $documentService = new DocumentService($this->trans, $this->config);
        $key = $this->fileUtility->getKey($file);
        $fileUrl = $this->getUrl($file, $user);
        try {
            $newFileUri = $documentService->GetConvertedUri($fileUrl, $ext, $toExtension, $key);
        } catch (\Exception $e) {
            $this->logger->logException($e, ["message" => "GetConvertedUri: " . $file->getId(), "app" => $this->appName]);
            return $this->renderError($e->getMessage());
        }

        try {
            $newData = $documentService->Request($newFileUri);
        } catch (\Exception $e) {
            $this->logger->logException($e, ["message" => "Failed to download converted file", "app" => $this->appName]);
            return $this->renderError($this->trans->t("Failed to download converted file"));
        }

        $fileNameWithoutExt = substr($fileName, 0, strlen($fileName) - strlen($ext) - 1);
        $newFileName = $fileNameWithoutExt . "." . $toExtension;

        $formats = $this->config->FormatsSetting();

        return new DataDownloadResponse($newData, $newFileName, $formats[$toExtension]["mime"]);
    }

    /**
     * Print editor section
     *
     * @param integer $fileId - file identifier
     * @param string $filePath - file path
     * @param string $shareToken - access token
     * @param integer $version - file version
     * @param bool $inframe - open in frame
     * @param bool $template - file is template
     * @param string $anchor - anchor for file content
     *
     * @return TemplateResponse|RedirectResponse
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function index($fileId, $filePath = null, $shareToken = null, $version = 0, $inframe = false, $template = false, $anchor = null) {
        $this->logger->debug("Open: $fileId ($version) $filePath ", ["app" => $this->appName]);

        $isLoggedIn = $this->userSession->isLoggedIn();
        if (empty($shareToken) && !$isLoggedIn) {
            $redirectUrl = $this->urlGenerator->linkToRoute("core.login.showLoginForm", [
                "redirect_url" => $this->request->getRequestUri()
            ]);
            return new RedirectResponse($redirectUrl);
        }

        $shareBy = null;
        if (!empty($shareToken) && !$isLoggedIn) {
            list ($share, $error) = $this->fileUtility->getShare($shareToken);
            if (!empty($share)) {
                $shareBy = $share->getSharedBy();
            }
        }

        if (!$this->config->isUserAllowedToUse($shareBy)) {
            return $this->renderError($this->trans->t("Not permitted"));
        }

        $documentServerUrl = $this->config->GetDocumentServerUrl();

        if (empty($documentServerUrl)) {
            $this->logger->error("documentServerUrl is empty", ["app" => $this->appName]);
            return $this->renderError($this->trans->t("ONLYOFFICE app is not configured. Please contact admin"));
        }

        $params = [
            "documentServerUrl" => $documentServerUrl,
            "fileId" => $fileId,
            "filePath" => $filePath,
            "shareToken" => $shareToken,
            "directToken" => null,
            "version" => $version,
            "isTemplate" => $template,
            "inframe" => false,
            "anchor" => $anchor
        ];

        $response = null;
        if ($inframe === true) {
            $params["inframe"] = true;
            $response = new TemplateResponse($this->appName, "editor", $params, "base");
        } else {
            if ($isLoggedIn) {
                $response = new TemplateResponse($this->appName, "editor", $params);
            } else {
                $response = new PublicTemplateResponse($this->appName, "editor", $params);

                list ($file, $error, $share) = $this->fileUtility->getFileByToken($fileId, $shareToken);
                if (!isset($error)) {
                    $response->setHeaderTitle($file->getName());
                }
            }
        }

        $csp = new ContentSecurityPolicy();
        $csp->allowInlineScript(true);

        if (preg_match("/^https?:\/\//i", $documentServerUrl)) {
            $csp->addAllowedScriptDomain($documentServerUrl);
            $csp->addAllowedFrameDomain($documentServerUrl);
        } else {
            $csp->addAllowedFrameDomain("'self'");
        }
        $response->setContentSecurityPolicy($csp);

        return $response;
    }

    /**
     * Print public editor section
     *
     * @param integer $fileId - file identifier
     * @param string $shareToken - access token
     * @param integer $version - file version
     * @param bool $inframe - open in frame
     *
     * @return TemplateResponse
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     */
    public function PublicPage($fileId, $shareToken, $version = 0, $inframe = false) {
        return $this->index($fileId, null, $shareToken, $version, $inframe);
    }

    /**
     * Getting file by identifier
     *
     * @param string $userId - user identifier
     * @param integer $fileId - file identifier
     * @param string $filePath - file path
     * @param bool $template - file is template
     *
     * @return array
     */
    private function getFile($userId, $fileId, $filePath = null, $template = false) {
        if (empty($fileId)) {
            return [null, $this->trans->t("FileId is empty"), null];
        }

        try {
            $folder = !$template ? $this->root->getUserFolder($userId) : TemplateManager::GetGlobalTemplateDir();
            $files = $folder->getById($fileId);
        } catch (\Exception $e) {
            $this->logger->logException($e, ["message" => "getFile: $fileId", "app" => $this->appName]);
            return [null, $this->trans->t("Invalid request"), null];
        }

        if (empty($files)) {
            $this->logger->info("Files not found: $fileId", ["app" => $this->appName]);
            return [null, $this->trans->t("File not found"), null];
        }

        $file = $files[0];

        if (count($files) > 1 && !empty($filePath)) {
            $filePath = "/" . $userId . "/files" . $filePath;
            foreach ($files as $curFile) {
                if ($curFile->getPath() === $filePath) {
                    $file = $curFile;
                    break;
                }
            }
        }

        if (!$file->isReadable()) {
            return [null, $this->trans->t("You do not have enough permissions to view the file"), null];
        }

        return [$file, null, null];
    }

    /**
     * Generate secure link to download document
     *
     * @param File $file - file
     * @param IUser $user - user with access
     * @param string $shareToken - access token
     * @param integer $version - file version
     * @param bool $changes - is required url to file changes
     * @param bool $template - file is template
     *
     * @return string
     */
    private function getUrl($file, $user = null, $shareToken = null, $version = 0, $changes = false, $template = false) {

        $data = [
            "action" => "download",
            "fileId" => $file->getId()
        ];

        $userId = null;
        if (!empty($user)) {
            $userId = $user->getUID();
            $data["userId"] = $userId;
        }
        if (!empty($shareToken)) {
            $data["shareToken"] = $shareToken;
        }
        if ($version > 0) {
            $data["version"] = $version;
        }
        if ($changes) {
            $data["changes"] = true;
        }
        if ($template) {
            $data["template"] = true;
        }

        $hashUrl = $this->crypt->GetHash($data);

        $fileUrl = $this->urlGenerator->linkToRouteAbsolute($this->appName . ".callback.download", ["doc" => $hashUrl]);

        if (!empty($this->config->GetStorageUrl())
            && !$changes) {
            $fileUrl = str_replace($this->urlGenerator->getAbsoluteURL("/"), $this->config->GetStorageUrl(), $fileUrl);
        }

        return $fileUrl;
    }

    /**
     * Return excluded groups list for share
     *
     * @return array
     */
    private function getShareExcludedGroups() {
        $excludedGroups = [];

        if (\OC::$server->getConfig()->getAppValue("core", "shareapi_exclude_groups", "no") === "yes") {
            $excludedGroups = json_decode(\OC::$server->getConfig()->getAppValue("core", "shareapi_exclude_groups_list", ""), true);
        }

        return $excludedGroups;
    }

    /**
     * Generate unique user identifier
     *
     * @param string $userId - current user identifier
     *
     * @return string
     */
    private function buildUserId($userId) {
        $instanceId = $this->config->GetSystemValue("instanceid", true);
        $userId = $instanceId . "_" . $userId;
        return $userId;
    }

    /**
     * Print error page
     *
     * @param string $error - error message
     * @param string $hint - error hint
     *
     * @return TemplateResponse
     */
    private function renderError($error, $hint = "") {
        return new TemplateResponse("", "error", [
                "errors" => [
                    [
                        "error" => $error,
                        "hint" => $hint
                    ]
                ]
            ], "error");
    }
}
