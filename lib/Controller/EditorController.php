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

namespace OCA\Onlyoffice\Controller;

use OCA\Files\Helper;
use OCA\Files_Sharing\SharedStorage;
use OCA\Files_Versions\Versions\IVersionManager;
use OCA\GroupFolders\Folder\FolderManager;
use OCA\GroupFolders\Mount\GroupFolderStorage;
use OCA\Onlyoffice\AppConfig;
use OCA\Onlyoffice\Crypt;
use OCA\Onlyoffice\DocumentService;
use OCA\Onlyoffice\EmailManager;
use OCA\Onlyoffice\FileUtility;
use OCA\Onlyoffice\FileVersions;
use OCA\Onlyoffice\KeyManager;
use OCA\Onlyoffice\TemplateManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\Template\PublicTemplateResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\Constants;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\Files\NotPermittedException;
use OCP\IAvatarManager;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Server;
use OCP\Share\IManager;
use OCP\Share\IShare;
use Psr\Log\LoggerInterface;

/**
 * Controller with the main functions
 */
class EditorController extends Controller {

    /**
     * File version manager
     *
     * @var IVersionManager
     */
    private $versionManager;

    /**
     * Folder manager
     *
     * @var FolderManager
     */
    private $folderManager;

    public function __construct(
        string $appName,
        IRequest $request,
        private readonly IRootFolder $root,
        private readonly IUserSession $userSession,
        private readonly IUserManager $userManager,
        private readonly IURLGenerator $urlGenerator,
        private readonly IL10N $trans,
        private readonly LoggerInterface $logger,
        private readonly AppConfig $appConfig,
        private readonly Crypt $crypt,
        private readonly IManager $shareManager,
        private readonly IGroupManager $groupManager,
        private readonly FileUtility $fileUtility,
        private readonly IAvatarManager $avatarManager,
        private readonly EmailManager $emailManager,
        private readonly DocumentService $documentService
    ) {
        parent::__construct($appName, $request);

        $appManager = Server::get(\OCP\App\IAppManager::class);
        $this->versionManager = $appManager->isInstalled("files_versions")
            ? Server::get(IVersionManager::class)
            : null;
        $this->folderManager = $appManager->isInstalled("groupfolders")
            ? Server::get(FolderManager::class)
            : null;
    }

    /**
     * Create new file in folder
     *
     * @param string $name - file name
     * @param string $dir - folder path
     * @param string $templateId - file identifier
     * @param int $targetId - identifier of the file for using as template for create
     * @param string $shareToken - access token
     *
     * @return DataResponse
     */
    #[NoAdminRequired]
    #[PublicPage]
    public function create($name, $dir, $templateId = null, $targetId = 0, $shareToken = null) {
        $this->logger->debug("Create: $name");

        if (empty($shareToken) && !$this->appConfig->isUserAllowedToUse()) {
            return new DataResponse(["error" => $this->trans->t("Not permitted")]);
        }

        if (empty($name)) {
            $this->logger->error("File name for creation was not found: $name");
            return new DataResponse(["error" => $this->trans->t("Template not found")]);
        }

        $user = null;
        if (empty($shareToken)) {
            $user = $this->userSession->getUser();
            $userId = $user->getUID();
            $userFolder = $this->root->getUserFolder($userId);
        } else {
            [$userFolder, $error, $share] = $this->fileUtility->getNodeByToken($shareToken);

            if (isset($error)) {
                $this->logger->error("Create: $error");
                return new DataResponse(["error" => $error]);
            }

            if ($userFolder instanceof File) {
                return new DataResponse(["error" => $this->trans->t("You don't have enough permission to create")]);
            }

            if (!empty($shareToken) && ($share->getPermissions() & Constants::PERMISSION_CREATE) === 0) {
                $this->logger->error("Create in public folder without access");
                return new DataResponse(["error" => $this->trans->t("You do not have enough permissions to view the file")]);
            }
        }

        $folder = $userFolder->get($dir);

        if ($folder === null) {
            $this->logger->error("Folder for file creation was not found: $dir");
            return new DataResponse(["error" => $this->trans->t("The required folder was not found")]);
        }
        if (!($folder->isCreatable() && $folder->isUpdateable())) {
            $this->logger->error("Folder for file creation without permission: $dir");
            return new DataResponse(["error" => $this->trans->t("You don't have enough permission to create")]);
        }

        if (!empty($templateId)) {
            $templateFile = TemplateManager::getTemplate($templateId);
            if ($templateFile !== null) {
                $template = $templateFile->getContent();
            }
        } elseif (!empty($targetId)) {
            $targetFile = $userFolder->getById($targetId)[0];
            $targetName = $targetFile->getName();
            $targetExt = strtolower(pathinfo((string) $targetName, PATHINFO_EXTENSION));
            $targetKey = $this->fileUtility->getKey($targetFile);

            $fileUrl = $this->getUrl($targetFile, $user, $shareToken);

            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $region = str_replace("_", "-", $this->trans->getLocaleCode());
            try {
                $newFileUri = $this->documentService->getConvertedUri($fileUrl, $targetExt, $ext, $targetKey, $region, $ext === "pdf");
            } catch (\Exception $e) {
                $this->logger->error("getConvertedUri: " . $targetFile->getId(), ["exception" => $e]);
                return new DataResponse(["error" => $e->getMessage()]);
            }
            $template = $this->documentService->request($newFileUri);
        } else {
            $template = TemplateManager::getEmptyTemplate($name);
        }

        if (!$template) {
            $this->logger->error("Template for file creation not found: $name ($templateId)");
            return new DataResponse(["error" => $this->trans->t("Template not found")]);
        }

        $name = $folder->getNonExistingName($name);

        try {
            if (\version_compare(\implode(".", Server::get(\OCP\ServerVersion::class)->getVersion()), "19", "<")) {
                $file = $folder->newFile($name);

                $file->putContent($template);
            } else {
                $file = $folder->newFile($name, $template);
            }
        } catch (NotPermittedException $e) {
            $this->logger->error("Can't create file: $name", ["exception" => $e]);
            return new DataResponse(["error" => $this->trans->t("Can't create file")]);
        }

        return new DataResponse(Helper::formatFileInfo($file->getFileInfo()));
    }

    /**
     * Create new file in folder from editor
     *
     * @param string $name - file name
     * @param string $dir - folder path
     * @param string $templateId - file identifier
     *
     * @return TemplateResponse|RedirectResponse
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function createNew($name, $dir, $templateId = null) {
        $this->logger->debug("Create from editor: $name in $dir");

        $response = $this->create($name, $dir, $templateId);
        $data = $response->getData();
        if (isset($data['error'])) {
            return $this->renderError(error: $data["error"]);
        }

        $openEditor = $this->urlGenerator->linkToRouteAbsolute($this->appName . ".editor.index", ["fileId" => $data["id"]]);
        return new RedirectResponse($openEditor);
    }

    /**
     * Get users
     *
     * @param $fileId - file identifier
     * @param $operationType - type of operation
     *
     * @return DataResponse
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function users($fileId, $operationType = null, $from = null, $count = null, $search = null) {
        $this->logger->debug("Search users");
        $result = [];
        $currentUserGroups = [];

        if (!$this->appConfig->isUserAllowedToUse()) {
            return new DataResponse();
        }

        if (!$this->shareManager->allowEnumeration()) {
            return new DataResponse();
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
        if ((count(array_intersect($currentUserGroups, $excludedGroups)) !== count($currentUserGroups)) || empty($currentUserGroups)) {
            $isMemberExcludedGroups = false;
        }

        [$file, $error, $share] = $this->getFile($currentUserId, $fileId);
        if (isset($error)) {
            $this->logger->error("Users: $fileId $error");
            return new DataResponse();
        }

        $canShare = (($file->getPermissions() & Constants::PERMISSION_SHARE) === Constants::PERMISSION_SHARE)
                    && !$isMemberExcludedGroups;

        $shareMemberGroups = $this->shareManager->shareWithGroupMembersOnly();

        $all = false;
        $users = [];
        $searchString = $search ?? "";
        $offset = $from !== null ? (int)$from : 0;
        $limit = $count !== null ? (int)$count : null;
        if ($canShare && $operationType !== "protect") {
            // who can be given access
            if ($shareMemberGroups || $autocompleteMemberGroup) {
                foreach ($currentUserGroups as $currentUserGroup) {
                    $group = $this->groupManager->get($currentUserGroup);
                    foreach ($group->getUsers() as $user) {
                        if ($this->filterUser($user, $currentUserId, $operationType, $searchString)) {
                            $users[$user->getUID()] = $user;
                        }
                    }
                }
            } else {
                // all users
                $all = true;
                $allUsers = $this->userManager->searchDisplayName($searchString);

                foreach ($allUsers as $user) {
                    if ($this->filterUser($user, $currentUserId, $operationType, $searchString)) {
                        $users[$user->getUID()] = $user;
                    }
                }
            }
        }

        if (!$all) {
            // who has access
            $accessList = $this->shareManager->getAccessList($file);
            foreach ($accessList["users"] as $accessUser) {
                $user = $this->userManager->get($accessUser);
                if ($this->filterUser($user, $currentUserId, $operationType, $searchString)) {
                    $users[$user->getUID()] = $user;
                }
            }

            $fileInfo = $file->getFileInfo();
            if ($fileInfo->getStorage()->instanceOfStorage(GroupFolderStorage::class)) {
                if ($this->folderManager !== null) {
                    $folderId = $this->folderManager->getFolderByPath($fileInfo->getPath());
                    $folderUsers = $this->folderManager->searchUsers($folderId, "", -1);
                    foreach ($folderUsers as $folderUser) {
                        $user = $this->userManager->get($folderUser["uid"]);
                        if ($this->filterUser($user, $currentUserId, $operationType, $searchString)) {
                            $users[$user->getUID()] = $user;
                        }
                    }
                } else {
                    $this->logger->error("Group folder manager is not available");
                }
            }
        }

        if ($limit !== null) {
            $users = array_slice($users, $offset, $limit);
        }

        foreach ($users as $user) {
            $userElement = [
                "name" => $user->getDisplayName(),
                "id" => $operationType === "protect" ? $this->buildUserId($user->getUID()) : $user->getUID(),
                "email" => $user->getEMailAddress()
            ];
            $result[] = $userElement;
        }

        return new DataResponse($result);
    }

    /**
     * Checking if the user matches the filter
     *
     * @param IUser $user - user
     * @param string $currentUserId - id of current user
     * @param string $operationType - type of the get user operation
     * @param int $searchString - string for searching
     */
    private function filterUser($user, $currentUserId, $operationType, $searchString): bool {
        return $user->getUID() != $currentUserId
            && (!empty($user->getEMailAddress()) || $operationType === "protect")
            && $this->searchInUser($user, $searchString);
    }

    /**
     * Check if the user contains the search string
     *
     * @param IUser $user - user
     * @param int $searchString - string for searching
     */
    private function searchInUser($user, $searchString): bool {
        return empty($searchString)
            || stripos((string) $user->getUID(), (string) $searchString) !== false
            || stripos((string) $user->getDisplayName(), (string) $searchString) !== false
            || !empty($user->getEMailAddress()) && stripos((string) $user->getEMailAddress(), (string) $searchString) !== false;
    }

    /**
     * Get user for Info
     *
     * @param string $userIds - users identifiers
     *
     * @return DataResponse
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function userInfo($userIds) {
        $result = [];
        $userIds = json_decode($userIds, true);

        if ($userIds !== null && is_array($userIds)) {
            foreach ($userIds as $userId) {
                $userData = [];
                $user = $this->userManager->get($this->getUserId($userId));
                if (!empty($user)) {
                    $userData = [
                        "name" => $user->getDisplayName(),
                        "id" => $userId
                    ];
                    $avatar = $this->avatarManager->getAvatar($user->getUID());
                    if ($avatar->exists() && $avatar->isCustomAvatar()) {
                        $userAvatarUrl = $this->urlGenerator->getAbsoluteURL(
                            $this->urlGenerator->linkToRoute("core.avatar.getAvatar", [
                                "userId" => $user->getUID(),
                                "size" => 64,
                            ])
                        );
                        $userData["image"] = $userAvatarUrl;
                    }
                    $result[] = $userData;
                }
            }
        }
        return new DataResponse($result);
    }

    /**
     * Send notify about mention
     *
     * @param int $fileId - file identifier
     * @param string $anchor - the anchor on target content
     * @param string $comment - comment
     * @param array $emails - emails array to whom to send notify
     *
     * @return DataResponse
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function mention($fileId, $anchor, $comment, $emails) {
        $this->logger->debug("mention: from $fileId to " . json_encode($emails));

        if (!$this->appConfig->isUserAllowedToUse()) {
            return new DataResponse(["error" => $this->trans->t("Not permitted")]);
        }

        if (empty($emails)) {
            return new DataResponse(["error" => $this->trans->t("Failed to send notification")]);
        }

        $recipientIds = [];
        foreach ($emails as $email) {
            $recipients = $this->userManager->getByEmail($email);
            foreach ($recipients as $recipient) {
                $recipientId = $recipient->getUID();
                if (!in_array($recipientId, $recipientIds)) {
                    $recipientIds[] = $recipientId;
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
        if ((count(array_intersect($currentUserGroups, $excludedGroups)) !== count($currentUserGroups)) || empty($currentUserGroups)) {
            $isMemberExcludedGroups = false;
        }

        [$file, $error, $share] = $this->getFile($userId, $fileId);
        if (isset($error)) {
            $this->logger->error("Mention: $fileId $error");
            return new DataResponse(["error" => $this->trans->t("Failed to send notification")]);
        }

        foreach ($emails as $email) {
            $substrToDelete = "+" . $email . " ";
            $comment = str_replace($substrToDelete, "", $comment);
        }

        //Length from Nextcloud:
        //https://github.com/nextcloud/server/blob/88b03d69cedab6f210178e9dcb04bc512beeb9be/lib/private/Notification/Notification.php#L204
        $maxLen = 64;
        if (strlen($comment) > $maxLen) {
            $ending = "...";
            $comment = substr($comment, 0, ($maxLen - strlen($ending))) . $ending;
        }

        $notificationManager = Server::get(\OCP\Notification\IManager::class);
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
            $isAvailable = in_array($recipientId, $accessList["users"]);

            if (!$isAvailable
                && ($file->getFileInfo()->getStorage()->instanceOfStorage(GroupFolderStorage::class)
                || $file->getFileInfo()->getMountPoint() instanceof \OCA\Files_External\Config\ExternalMountPoint)) {
                $recipientFolder = $this->root->getUserFolder($recipientId);
                $recipientFile = $recipientFolder->getById($file->getId());

                $isAvailable = !empty($recipientFile);
            }

            if (!$isAvailable) {
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

                $this->logger->debug("mention: share $fileId to $recipientId");
            }

            $notification->setUser($recipientId);

            $notificationManager->notify($notification);
            if ($this->appConfig->getEmailNotifications()) {
                $this->emailManager->notifyMentionEmail($userId, $recipientId, $file->getId(), $file->getName(), $anchor, $notification->getObjectId());
            }
        }

        return new DataResponse(["message" => $this->trans->t("Notification sent successfully")]);
    }

    /**
     * Reference data
     *
     * @param array $referenceData - reference data
     * @param string $path - file path
     * @param string $link - file link
     *
     * @return DataResponse
     */
    #[NoAdminRequired]
    #[PublicPage]
    public function reference($referenceData, $path = null, $link = null) {
        $this->logger->debug("reference: " . json_encode($referenceData) . " $path");

        if (!$this->appConfig->isUserAllowedToUse()) {
            return new DataResponse(["error" => $this->trans->t("Not permitted")]);
        }

        $user = $this->userSession->getUser();
        if (empty($user)) {
            return new DataResponse(["error" => $this->trans->t("Not permitted")]);
        }

        $userId = $user->getUID();

        $file = null;
        $fileId = (integer)($referenceData["fileKey"] ?? 0);
        if (!empty($fileId)
            && $referenceData["instanceId"] === $this->appConfig->getSystemValue("instanceid", true)) {
            [$file, $error, $share] = $this->getFile($userId, $fileId);
        }

        $userFolder = $this->root->getUserFolder($userId);
        if ($file === null
            && $path !== null
            && $userFolder->nodeExists($path)) {
            $node = $userFolder->get($path);
            if ($node instanceof File
                && $node->isReadable()) {
                $file = $node;
            }
        }

        if ($file === null
            && !empty($link)) {
            [$fileId, $redirect] = $this->getFileIdByLink($link);
            if (!empty($fileId)) {
                [$file, $error, $share] = $this->getFile($userId, $fileId);
            } elseif ($redirect) {
                return new DataResponse(["url" => $link]);
            }
        }

        if ($file === null) {
            $this->logger->error("Reference not found: $fileId $path");
            return new DataResponse(["error" => $this->trans->t("File not found")]);
        }

        $fileName = $file->getName();
        $ext = strtolower(pathinfo((string) $fileName, PATHINFO_EXTENSION));
        $key = $this->fileUtility->getKey($file);
        $key = DocumentService::generateRevisionId($key);

        $response = [
            "fileType" => $ext,
            "path" => $userFolder->getRelativePath($file->getPath()),
            "key" => $key,
            "referenceData" => [
                "fileKey" => (string)$file->getId(),
                "instanceId" => $this->appConfig->getSystemValue("instanceid", true),
            ],
            "url" => $this->getUrl($file, $user),
        ];

        if (!empty($this->appConfig->getDocumentServerSecret())) {
            $now = time();
            $iat = $now;
            $exp = $now + $this->appConfig->getJwtExpiration() * 60;
            $response["iat"] = $iat;
            $response["exp"] = $exp;
            $token = \Firebase\JWT\JWT::encode($response, $this->appConfig->getDocumentServerSecret(), "HS256");
            $response["token"] = $token;
        }

        return new DataResponse($response);
    }

    /**
     * Conversion file to Office Open XML format
     *
     * @param integer $fileId - file identifier
     * @param string $shareToken - access token
     *
     * @return DataResponse
     */
    #[NoAdminRequired]
    #[PublicPage]
    public function convert($fileId, $shareToken = null) {
        $this->logger->debug("Convert: $fileId");

        if (empty($shareToken) && !$this->appConfig->isUserAllowedToUse()) {
            return new DataResponse(["error" => $this->trans->t("Not permitted")]);
        }

        $user = $this->userSession->getUser();
        $userId = null;
        if (!empty($user)) {
            $userId = $user->getUID();
        }

        [$file, $error, $share] = empty($shareToken) ? $this->getFile($userId, $fileId) : $this->fileUtility->getFileByToken($fileId, $shareToken);

        if (isset($error)) {
            $this->logger->error("Convertion: $fileId $error");
            return new DataResponse(["error" => $error]);
        }

        if (!empty($shareToken) && ($share->getPermissions() & Constants::PERMISSION_CREATE) === 0) {
            $this->logger->error("Convertion in public folder without access: $fileId");
            return new DataResponse(["error" => $this->trans->t("You do not have enough permissions to view the file")]);
        }

        $fileName = $file->getName();
        $ext = strtolower(pathinfo((string) $fileName, PATHINFO_EXTENSION));
        $format = $this->appConfig->formatsSetting()[$ext];
        if (!isset($format)) {
            $this->logger->info("Format for convertion not supported: $fileName");
            return new DataResponse(["error" => $this->trans->t("Format is not supported")]);
        }

        if (!isset($format["conv"]) || $format["conv"] !== true) {
            $this->logger->info("Conversion is not required: $fileName");
            return new DataResponse(["error" => $this->trans->t("Conversion is not required")]);
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
        $key = $this->fileUtility->getKey($file);
        $fileUrl = $this->getUrl($file, $user, $shareToken);
        $region = str_replace("_", "-", $this->trans->getLocaleCode());
        try {
            $newFileUri = $this->documentService->getConvertedUri($fileUrl, $ext, $internalExtension, $key, $region);
        } catch (\Exception $e) {
            $this->logger->error("getConvertedUri: " . $file->getId(), ["exception" => $e]);
            return new DataResponse(["error" => $e->getMessage()]);
        }

        $folder = $file->getParent();
        if (!($folder->isCreatable() && $folder->isUpdateable())) {
            $folder = $this->root->getUserFolder($userId);
        }

        try {
            $newData = $this->documentService->request($newFileUri);
        } catch (\Exception $e) {
            $this->logger->error("Failed to download converted file", ["exception" => $e]);
            return new DataResponse(["error" => $this->trans->t("Failed to download converted file")]);
        }

        $fileNameWithoutExt = substr((string) $fileName, 0, strlen((string) $fileName) - strlen($ext) - 1);
        $newFileName = $folder->getNonExistingName($fileNameWithoutExt . "." . $internalExtension);

        try {
            $file = $folder->newFile($newFileName);

            $file->putContent($newData);
        } catch (NotPermittedException $e) {
            $this->logger->error("Can't create file: $newFileName", ["exception" => $e]);
            return new DataResponse(["error" => $this->trans->t("Can't create file")]);
        }

        return new DataResponse(Helper::formatFileInfo($file->getFileInfo()));
    }

    /**
     * Save file to folder
     *
     * @param string $name - file name
     * @param string $dir - folder path
     * @param string $url - file url
     *
     * @return DataResponse
     */
    #[NoAdminRequired]
    public function save($name, $dir, $url) {
        $this->logger->debug("Save: $name");

        if (!$this->appConfig->isUserAllowedToUse()) {
            return new DataResponse(["error" => $this->trans->t("Not permitted")]);
        }

        $userId = $this->userSession->getUser()->getUID();
        $userFolder = $this->root->getUserFolder($userId);

        try {
            /**
             * @var \OC\Files\Node\Folder
             */
            $folder = $userFolder->get($dir);
        } catch(\OCP\Files\NotFoundException $e) {
            $this->logger->error("Folder for saving file was not found: $dir", ['exception' => $e]);
            return new DataResponse(["error" => $this->trans->t("The required folder was not found")]);
        }

        if (!($folder->isCreatable() && $folder->isUpdateable())) {
            $this->logger->error("Folder for saving file without permission: $dir");
            return new DataResponse(["error" => $this->trans->t("You don't have enough permission to create")]);
        }
        $documentServerUrl = $this->appConfig->getDocumentServerUrl();

        if (empty($documentServerUrl)) {
            $this->logger->error("documentServerUrl is empty");
            return new DataResponse(["error" => $this->trans->t("ONLYOFFICE app is not configured. Please contact admin")]);
        }

        if (str_starts_with($documentServerUrl, "/")) {
            $documentServerUrl = $this->urlGenerator->getAbsoluteURL($documentServerUrl);
        }

        if (parse_url($url, PHP_URL_HOST) !== parse_url((string) $documentServerUrl, PHP_URL_HOST)) {
            $this->logger->error("Incorrect domain in file url");
            return new DataResponse(["error" => $this->trans->t("The domain in the file url does not match the domain of the Document server")]);
        }

        $url = $this->appConfig->replaceDocumentServerUrlToInternal($url);

        try {
            $newData = $this->documentService->request($url);
        } catch (\Exception $e) {
            $this->logger->error("Failed to download file for saving: $url", ["exception" => $e]);
            return new DataResponse(["error" => $this->trans->t("Download failed")]);
        }

        $name = $folder->getNonExistingName($name);

        try {
            $file = $folder->newFile($name);

            $file->putContent($newData);
        } catch (NotPermittedException $e) {
            $this->logger->error("Can't save file: $name", ["exception" => $e]);
            return new DataResponse(["error" => $this->trans->t("Can't create file")]);
        }

        return new DataResponse(Helper::formatFileInfo($file->getFileInfo()));
    }

    /**
     * Get versions history for file
     *
     * @param integer $fileId - file identifier
     *
     * @return DataResponse
     */
    #[NoAdminRequired]
    public function history($fileId) {
        $this->logger->debug("Request history for: $fileId");

        if (!$this->appConfig->isUserAllowedToUse()) {
            return new DataResponse(["error" => $this->trans->t("Not permitted")]);
        }

        $history = [];

        $user = $this->userSession->getUser();
        $userId = null;
        if (!empty($user)) {
            $userId = $user->getUID();
        }

        [$file, $error, $share] = $this->getFile($userId, $fileId);

        if (isset($error)) {
            $this->logger->error("History: $fileId $error");
            return new DataResponse(["error" => $error]);
        }

        if ($fileId === 0) {
            $fileId = $file->getId();
        }

        $ownerId = null;
        $owner = $file->getFileInfo()->getOwner();
        if ($owner !== null) {
            $ownerId = $owner->getUID();
        }

        $versions = [];
        if ($this->versionManager !== null
            && $owner !== null) {
            $versions = FileVersions::processVersionsArray($this->versionManager->getVersionsForFile($owner, $file));
        }

        $prevVersion = "";
        $versionNum = 0;
        foreach ($versions as $version) {
            $versionNum += 1;

            $key = $this->fileUtility->getVersionKey($version);
            $key = DocumentService::generateRevisionId($key);

            $historyItem = [
                "created" => $version->getTimestamp(),
                "key" => $key,
                "version" => $versionNum
            ];

            $versionId = $version->getRevisionId();

            $author = FileVersions::getAuthor($ownerId, $file->getFileInfo(), $versionId);

            if ($author !== null) {
                $historyItem["user"] = [
                    "id" => $this->buildUserId($author["id"]),
                    "name" => $author["name"],
                ];
            } elseif (!empty($this->appConfig->getUnknownAuthor()) && $versionNum !== 1) {
                $authorName = $this->appConfig->getUnknownAuthor();
                $historyItem["user"] = [
                    "name" => $authorName,
                ];
            } else {
                $authorName = $owner->getDisplayName();
                $authorId = $owner->getUID();
                $historyItem["user"] = [
                    "id" => $this->buildUserId($authorId),
                    "name" => $authorName,
                ];
            }

            $historyData = FileVersions::getHistoryData($ownerId, $file->getFileInfo(), $versionId, $prevVersion);
            if ($historyData !== null) {
                $historyItem["changes"] = $historyData["changes"];
                $historyItem["serverVersion"] = $historyData["serverVersion"];
            }

            $prevVersion = $versionId;

            $history[] = $historyItem;
        }

        $key = $this->fileUtility->getKey($file, true);
        $key = DocumentService::generateRevisionId($key);

        $historyItem = [
            "created" => $file->getMTime(),
            "key" => $key,
            "version" => $versionNum + 1
        ];

        $versionId = $file->getFileInfo()->getMtime();

        $author = FileVersions::getAuthor($ownerId, $file->getFileInfo(), $versionId);
        if ($author !== null) {
            $historyItem["user"] = [
                "id" => $this->buildUserId($author["id"]),
                "name" => $author["name"],
            ];
        } elseif (!empty($this->appConfig->getUnknownAuthor()) && $versionNum !== 0) {
            $authorName = $this->appConfig->getUnknownAuthor();
            $historyItem["user"] = [
                "name" => $authorName,
            ];
        } else {
            $authorName = $owner->getDisplayName();
            $authorId = $owner->getUID();
            $historyItem["user"] = [
                "id" => $this->buildUserId($authorId),
                "name" => $authorName,
            ];
        }

        $historyData = FileVersions::getHistoryData($ownerId, $file->getFileInfo(), $versionId, $prevVersion);
        if ($historyData !== null) {
            $historyItem["changes"] = $historyData["changes"];
            $historyItem["serverVersion"] = $historyData["serverVersion"];
        }
        $history[] = $historyItem;

        return new DataResponse($history);
    }

    /**
     * Get file attributes of specific version
     *
     * @param integer $fileId - file identifier
     * @param integer $version - file version
     *
     * @return DataResponse
     */
    #[NoAdminRequired]
    public function version($fileId, $version) {
        $this->logger->debug("Request version for: $fileId ($version)");

        if (!$this->appConfig->isUserAllowedToUse()) {
            return new DataResponse(["error" => $this->trans->t("Not permitted")]);
        }

        $version = empty($version) ? null : $version;

        $user = $this->userSession->getUser();
        $userId = null;
        if (!empty($user)) {
            $userId = $user->getUID();
        }

        [$file, $error, $share] = $this->getFile($userId, $fileId);

        if (isset($error)) {
            $this->logger->error("History: $fileId $error");
            return new DataResponse(["error" => $error]);
        }

        if ($fileId === 0) {
            $fileId = $file->getId();
        }

        $owner = null;
        $ownerId = null;
        $versions = [];
        if ($this->versionManager !== null) {
            $owner = $file->getFileInfo()->getOwner();
            if ($owner !== null) {
                $ownerId = $owner->getUID();
                $versions = FileVersions::processVersionsArray($this->versionManager->getVersionsForFile($owner, $file));
            }
        }

        $key = null;
        $fileUrl = null;
        $versionId = null;
        if ($version > count($versions)) {
            $key = $this->fileUtility->getKey($file, true);
            $versionId = $file->getFileInfo()->getMtime();

            $fileUrl = $this->getUrl($file, $user);
        } else {
            $fileVersion = array_values($versions)[$version - 1];

            $key = $this->fileUtility->getVersionKey($fileVersion);
            $versionId = $fileVersion->getRevisionId();

            $fileUrl = $this->getUrl($file, $user, null, $version);
        }
        $key = DocumentService::generateRevisionId($key);
        $fileName = $file->getName();
        $ext = strtolower(pathinfo((string) $fileName, PATHINFO_EXTENSION));

        $result = [
            "fileType" => $ext,
            "url" => $fileUrl,
            "version" => $version,
            "key" => $key
        ];

        if ($version > 1
            && count($versions) >= $version - 1
            && FileVersions::hasChanges($ownerId, $file->getFileInfo(), $versionId)) {
            $changesUrl = $this->getUrl($file, $user, null, $version, true);
            $result["changesUrl"] = $changesUrl;

            $prevVersion = array_values($versions)[$version - 2];
            $prevVersionKey = $this->fileUtility->getVersionKey($prevVersion);
            $prevVersionKey = DocumentService::generateRevisionId($prevVersionKey);

            $prevVersionUrl = $this->getUrl($file, $user, null, $version - 1);

            $result["previous"] = [
                "fileType" => $ext,
                "key" => $prevVersionKey,
                "url" => $prevVersionUrl
            ];
        }

        if (!empty($this->appConfig->getDocumentServerSecret())) {
            $now = time();
            $iat = $now;
            $exp = $now + $this->appConfig->getJwtExpiration() * 60;
            $result["iat"] = $iat;
            $result["exp"] = $exp;
            $token = \Firebase\JWT\JWT::encode($result, $this->appConfig->getDocumentServerSecret(), "HS256");
            $result["token"] = $token;
        }

        return new DataResponse($result);
    }

    /**
     * Restore file version
     *
     * @param integer $fileId - file identifier
     * @param integer $version - file version
     *
     * @return DataResponse
     */
    #[NoAdminRequired]
    public function restore($fileId, $version) {
        $this->logger->debug("Request restore version for: $fileId ($version)");

        if (!$this->appConfig->isUserAllowedToUse()) {
            return new DataResponse(["error" => $this->trans->t("Not permitted")]);
        }

        $version = empty($version) ? null : $version;

        $user = $this->userSession->getUser();
        $userId = null;
        if (!empty($user)) {
            $userId = $user->getUID();
        }

        [$file, $error, $share] = $this->getFile($userId, $fileId);

        if (isset($error)) {
            $this->logger->error("Restore: $fileId $error");
            return new DataResponse(["error" => $error]);
        }

        if ($fileId === 0) {
            $fileId = $file->getId();
        }

        $owner = null;
        $versions = [];
        if ($this->versionManager !== null) {
            $owner = $file->getFileInfo()->getOwner();
            if ($owner !== null) {
                $versions = FileVersions::processVersionsArray($this->versionManager->getVersionsForFile($owner, $file));
            }

            if (count($versions) >= $version) {
                $fileVersion = array_values($versions)[$version - 1];
                $this->versionManager->rollback($fileVersion);
                if ($fileVersion->getSourceFile()->getFileInfo()->getStorage()->instanceOfStorage(GroupFolderStorage::class)) {
                    KeyManager::delete($fileVersion->getSourceFile()->getId());
                }
            }
        }

        return $this->history($fileId);
    }

    /**
     * Get presigned url to file
     *
     * @param string $filePath - file path
     *
     * @return DataResponse
     */
    #[NoAdminRequired]
    public function url($filePath) {
        $this->logger->debug("Request url for: $filePath");

        if (!$this->appConfig->isUserAllowedToUse()) {
            return new DataResponse(["error" => $this->trans->t("Not permitted")]);
        }

        $user = $this->userSession->getUser();
        $userId = $user->getUID();
        $userFolder = $this->root->getUserFolder($userId);

        $file = $userFolder->get($filePath);

        if ($file === null) {
            $this->logger->error("File for generate presigned url was not found: $filePath");
            return new DataResponse(["error" => $this->trans->t("File not found")]);
        }

        $canDownload = true;

        /**
         * @var \OCP\Files\Storage\IStorage|\OCA\Files_Sharing\SharedStorage
         */
        $fileStorage = $file->getStorage();
        if ($fileStorage->instanceOfStorage(SharedStorage::class)) {
            $share = $fileStorage->getShare();
            $canDownload = FileUtility::canShareDownload($share);
        }

        if (!$file->isReadable() || !$canDownload) {
            $this->logger->error("File without permission: $filePath");
            return new DataResponse(["error" => $this->trans->t("You do not have enough permissions to view the file")]);
        }

        $fileName = $file->getName();
        $ext = strtolower(pathinfo((string) $fileName, PATHINFO_EXTENSION));
        $fileUrl = $this->getUrl($file, $user);

        $result = [
            "fileType" => $ext,
            "url" => $fileUrl
        ];

        if (!empty($this->appConfig->getDocumentServerSecret())) {
            $now = time();
            $iat = $now;
            $exp = $now + $this->appConfig->getJwtExpiration() * 60;
            $result["iat"] = $iat;
            $result["exp"] = $exp;
            $token = \Firebase\JWT\JWT::encode($result, $this->appConfig->getDocumentServerSecret(), "HS256");
            $result["token"] = $token;
        }

        return new DataResponse($result);
    }

    /**
     * Download method
     *
     * @param int $fileId - file identifier
     * @param string $toExtension - file extension to download
     * @param bool $template - file extension to download
     *
     * @return DataDownloadResponse|TemplateResponse
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function download($fileId, $toExtension = null, $template = false) {
        $this->logger->debug("Download: $fileId $toExtension");

        if (!$this->appConfig->isUserAllowedToUse() || $this->appConfig->getDisableDownload()) {
            return $this->renderError($this->trans->t("Not permitted"));
        }

        if ($template) {
            $templateFile = TemplateManager::getTemplate($fileId);
            if (empty($templateFile)) {
                $this->logger->info("Download: template not found: $fileId");
                return $this->renderError($this->trans->t("File not found"));
            }

            $file = $templateFile;
        } else {
            $user = $this->userSession->getUser();
            $userId = null;
            if (!empty($user)) {
                $userId = $user->getUID();
            }

            [$file, $error, $share] = $this->getFile($userId, $fileId);

            if (isset($error)) {
                $this->logger->error("Download: $fileId $error");
                return $this->renderError($error);
            }
        }

        $fileStorage = $file->getStorage();
        if ($fileStorage->instanceOfStorage(SharedStorage::class)) {
            $share = empty($share) ? $fileStorage->getShare() : $share;
            if (!FileUtility::canShareDownload($share)) {
                return $this->renderError($this->trans->t("Not permitted"));
            }
        }

        $fileName = $file->getName();
        $ext = strtolower(pathinfo((string) $fileName, PATHINFO_EXTENSION));
        $toExtension = strtolower((string) $toExtension);

        if ($toExtension === null
            || $ext === $toExtension
            || $template) {
            return new DataDownloadResponse($file->getContent(), $fileName, $file->getMimeType());
        }

        $newFileUri = null;
        $newFileType = $toExtension;
        $key = $this->fileUtility->getKey($file);
        $fileUrl = $this->getUrl($file, $user);
        $thumbnail = ['first' => false];
        try {
            $response = $this->documentService->sendRequestToConvertService(
                $fileUrl,
                $ext,
                $toExtension,
                $key,
                false,
                null,
                false,
                $thumbnail,
            );

            if (isset($response->error)) {
                $this->documentService->processConvServResponceError($response->error);
            }

            if (isset($response->endConvert) && $response->endConvert === true) {
                $newFileUri = $response->fileUrl;
                $newFileType = $response->fileType;
            }
        } catch (\Exception $e) {
            $this->logger->error("sendRequestToConvertService: " . $file->getId(), ["exception" => $e]);
            return $this->renderError($e->getMessage());
        }

        try {
            $newData = $this->documentService->request($newFileUri);
        } catch (\Exception $e) {
            $this->logger->error("Failed to download converted file", ["exception" => $e]);
            return $this->renderError($this->trans->t("Failed to download converted file"));
        }

        $fileNameWithoutExt = substr((string) $fileName, 0, strlen((string) $fileName) - strlen($ext) - 1);
        $newFileName = "$fileNameWithoutExt.$newFileType";

        $mimeType = $this->appConfig->getMimeType($newFileType);

        return new DataDownloadResponse($newData, $newFileName, $mimeType);
    }

    /**
     * Print editor section
     *
     * @param integer $fileId - file identifier
     * @param string $filePath - file path
     * @param string $shareToken - access token
     * @param bool $inframe - open in frame
     * @param bool $inviewer - open in viewer
     * @param bool $template - file is template
     * @param string $anchor - anchor for file content
     *
     * @return TemplateResponse|RedirectResponse
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function index($fileId, $filePath = null, $shareToken = null, $inframe = false, $inviewer = false, $template = false, $anchor = null) {
        $this->logger->debug("Open: $fileId $filePath ");

        $isLoggedIn = $this->userSession->isLoggedIn();
        if (empty($shareToken) && !$isLoggedIn) {
            $redirectUrl = $this->urlGenerator->linkToRoute("core.login.showLoginForm", [
                "redirect_url" => $this->request->getRequestUri()
            ]);
            return new RedirectResponse($redirectUrl);
        }

        $shareBy = null;
        if (!empty($shareToken) && !$isLoggedIn) {
            [$share, $error] = $this->fileUtility->getShare($shareToken);
            if (!empty($share)) {
                $shareBy = $share->getSharedBy();
            }
        }

        if (!$this->appConfig->isUserAllowedToUse($shareBy)) {
            return $this->renderError($this->trans->t("Not permitted"));
        }

        $documentServerUrl = $this->appConfig->getDocumentServerUrl();

        if (empty($documentServerUrl)) {
            $this->logger->error("documentServerUrl is empty");
            return $this->renderError($this->trans->t("ONLYOFFICE app is not configured. Please contact admin"));
        }

        $params = [
            "fileId" => $fileId,
            "filePath" => $filePath,
            "shareToken" => $shareToken,
            "directToken" => null,
            "isTemplate" => $template,
            "inframe" => false,
            "inviewer" => $inviewer === true,
            "anchor" => $anchor
        ];

        $response = null;
        if ($inframe === true) {
            $params["inframe"] = true;
            $response = new TemplateResponse($this->appName, "editor", $params, "base");
        } elseif ($isLoggedIn) {
            $response = new TemplateResponse($this->appName, "editor", $params);
        } else {
            $response = new PublicTemplateResponse($this->appName, "editor", $params);

            [$file, $error, $share] = $this->fileUtility->getFileByToken($fileId, $shareToken);
            if (!isset($error)) {
                $response->setHeaderTitle($file->getName());
            }
        }

        \OCP\Util::addHeader("meta", ["name" => "apple-touch-fullscreen", "content" => "yes"]);

        $csp = new ContentSecurityPolicy();

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
     * @param bool $inframe - open in frame
     *
     * @return TemplateResponse
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    #[PublicPage]
    public function publicPage($fileId, $shareToken, $inframe = false) {
        return $this->index($fileId, null, $shareToken, $inframe);
    }

    /**
     * Getting file by identifier
     *
     * @param string $userId - user identifier
     * @param integer $fileId - file identifier
     * @param string $filePath - file path
     * @param bool $template - file is template
     */
    private function getFile(?string $userId, $fileId, $filePath = null, $template = false): array {
        if (empty($userId)) {
            return [null, $this->trans->t("UserId is empty"), null];
        }

        if (empty($fileId)) {
            return [null, $this->trans->t("FileId is empty"), null];
        }

        try {
            $folder = $template ? TemplateManager::getGlobalTemplateDir() : $this->root->getUserFolder($userId);
            $files = $folder->getById($fileId);
        } catch (\Exception $e) {
            $this->logger->error("getFile: $fileId", ["exception" => $e]);
            return [null, $this->trans->t("Invalid request"), null];
        }

        if (empty($files)) {
            $this->logger->info("Files not found: $fileId");
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
    private function getUrl($file, $user = null, $shareToken = null, $version = 0, bool $changes = false, $template = false) {

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

        $hashUrl = $this->crypt->getHash($data);

        $fileUrl = $this->urlGenerator->linkToRouteAbsolute($this->appName . ".callback.download", ["doc" => $hashUrl]);

        if (!$this->appConfig->useDemo() && !empty($this->appConfig->getStorageUrl())) {
            $fileUrl = str_replace($this->urlGenerator->getAbsoluteURL("/"), $this->appConfig->getStorageUrl(), $fileUrl);
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

        if (Server::get(\OCP\IAppConfig::class)->getValueString("core", "shareapi_exclude_groups", "no") === "yes") {
            $excludedGroups = json_decode((string) Server::get(\OCP\IAppConfig::class)->getValueString("core", "shareapi_exclude_groups_list", ""), true);
        }

        return $excludedGroups;
    }

    /**
     * Generate unique user identifier
     *
     * @param string $userId - current user identifier
     */
    private function buildUserId(string $userId): string {
        $instanceId = $this->appConfig->getSystemValue("instanceid", true);
        return $instanceId . "_" . $userId;
    }

    /**
     * Get Nextcloud userId from unique user identifier
     *
     * @param string $userId - current user identifier
     *
     * @return string
     */
    private function getUserId($userId) {
        if (str_contains($userId, "_")) {
            $userIdExp = explode("_", $userId);
            $userId = end($userIdExp);
        }
        return $userId;
    }

    /**
     * Get File id from by link
     *
     * @param string $link - link to the file
     */
    private function getFileIdByLink(string $link): array {
        $path = parse_url($link, PHP_URL_PATH);
        $encodedPath = array_map(urlencode(...), explode("/", $path));
        $parsedLink = str_replace($path, implode("/", $encodedPath), $link);
        if (filter_var($parsedLink, FILTER_VALIDATE_URL) === false) {
            return [null, true];
        }

        $storageUrl = $this->urlGenerator->getAbsoluteURL("/");
        if (parse_url($parsedLink, PHP_URL_HOST) !== parse_url((string) $storageUrl, PHP_URL_HOST)) {
            return [null, true];
        }

        if (preg_match('/\/(files|f|onlyoffice)\/(\d+)/', $parsedLink, $matches)) {
            return [$matches[2], false];
        }

        return [null, false];
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
