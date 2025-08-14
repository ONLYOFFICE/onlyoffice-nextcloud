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
use OCA\Onlyoffice\Crypt;
use OCA\Onlyoffice\DocumentService;
use OCA\Onlyoffice\ExtraPermissions;
use OCA\Onlyoffice\FileUtility;
use OCA\Onlyoffice\TemplateManager;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\OCSController;
use OCP\AppFramework\QueryException;
use OCA\DAV\CalDAV\TimezoneService;
use OCP\Constants;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\Lock\ILock;
use OCP\Files\Lock\ILockManager;
use OCP\Files\Lock\NoLockProviderException;
use OCP\IL10N;
use OCP\IRequest;
use OCP\ISession;
use OCP\ITagManager;
use OCP\ITags;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\PreConditionNotMetException;
use OCP\Share\IManager;
use OCP\Share\IShare;
use Psr\Log\LoggerInterface;

/**
 * Controller with the main functions
 */
class EditorApiController extends OCSController {

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
     * @var LoggerInterface
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
     * Tag manager
     *
     * @var ITagManager
     */
    private $tagManager;

    /**
     * Extra permissions
     *
     * @var ExtraPermissions
     */
    private $extraPermissions;

    /**
     * Lock manager
     *
     * @var ILockManager
     */
    private $lockManager;

    /**
     * Avatar manager
     *
     * @var IAvatarManager
     */
    private $avatarManager;

    /**
     * Timezone service
     *
     * @var TimezoneService
     */
    private $timezoneService;

    /**
     * Mobile regex from https://github.com/ONLYOFFICE/CommunityServer/blob/v9.1.1/web/studio/ASC.Web.Studio/web.appsettings.config#L35
     */
    public const USER_AGENT_MOBILE = "/android|avantgo|playbook|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od|ad)|iris|kindle|lge |maemo|midp|mmp|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\\/|plucker|pocket|psp|symbian|treo|up\\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i";

    /**
     * @param string $AppName - application name
     * @param IRequest $request - request object
     * @param IRootFolder $root - root folder
     * @param IUserSession $userSession - current user session
     * @param IUserManager $userManager - user manager
     * @param IURLGenerator $urlGenerator - url generator service
     * @param IL10N $trans - l10n service
     * @param LoggerInterface $logger - logger
     * @param AppConfig $config - application configuration
     * @param Crypt $crypt - hash generator
     * @param IManager $shareManager - Share manager
     * @param ISession $ISession - Session
     * @param ITagManager $tagManager - Tag manager
     * @param ILockManager $lockManager - Lock manager
     * @param TimezoneService $timezoneService - Timezone service
     */
    public function __construct(
        $AppName,
        IRequest $request,
        IRootFolder $root,
        IUserSession $userSession,
        IUserManager $userManager,
        IURLGenerator $urlGenerator,
        IL10N $trans,
        LoggerInterface $logger,
        AppConfig $config,
        Crypt $crypt,
        IManager $shareManager,
        ISession $session,
        ITagManager $tagManager,
        ILockManager $lockManager,
        TimezoneService $timezoneService
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
        $this->tagManager = $tagManager;
        $this->lockManager = $lockManager;
        $this->timezoneService = $timezoneService;

        if ($this->config->getAdvanced()
            && \OC::$server->getAppManager()->isInstalled("files_sharing")) {
            $this->extraPermissions = new ExtraPermissions($AppName, $logger, $shareManager, $config);
        }

        $this->fileUtility = new FileUtility($AppName, $trans, $logger, $config, $shareManager, $session);
        $this->avatarManager = \OC::$server->getAvatarManager();
    }

    /**
     * Collecting the file parameters for the document service
     *
     * @param integer $fileId - file identifier
     * @param string $filePath - file path
     * @param string $shareToken - access token
     * @param string $directToken - direct token
     * @param bool $inframe - open in frame
     * @param bool $inviewer - open in viewer
     * @param bool $desktop - desktop label
     * @param string $guestName - nickname not logged user
     * @param bool $template - file is template
     * @param string $anchor - anchor for file content
     *
     * @return JSONResponse
     */
    #[NoAdminRequired]
    #[PublicPage]
    public function config($fileId, $filePath = null, $shareToken = null, $directToken = null, $inframe = false, $inviewer = false, $desktop = false, $guestName = null, $template = false, $anchor = null) {

        if (!empty($directToken)) {
            list($directData, $error) = $this->crypt->readHash($directToken);
            if ($directData === null) {
                $this->logger->error("Config for directEditor with empty or not correct hash: $error");
                return new JSONResponse(["error" => $this->trans->t("Not permitted")]);
            }
            if ($directData->action !== "direct") {
                $this->logger->error("Config for directEditor with other data");
                return new JSONResponse(["error" => $this->trans->t("Invalid request")]);
            }

            $fileId = $directData->fileId;
            $userId = $directData->userId;
            if ($this->userSession->isLoggedIn()
                && $userId === $this->userSession->getUser()->getUID()) {
                $redirectUrl = $this->urlGenerator->linkToRouteAbsolute(
                    $this->appName . ".editor.index",
                    [
                        "fileId" => $fileId,
                        "filePath" => $filePath
                    ]
                );
                return new JSONResponse(["redirectUrl" => $redirectUrl]);
            }

            $user = $this->userManager->get($userId);
            if (method_exists($this->userSession, 'setVolatileActiveUser')) {
                $this->userSession->setVolatileActiveUser($user);
            }
        } else {
            $user = $this->userSession->getUser();
            $userId = null;
            if (!empty($user)) {
                $userId = $user->getUID();
            }
        }

        list($file, $error, $share) = empty($shareToken) ? $this->getFile($userId, $fileId, $filePath, $template) : $this->fileUtility->getFileByToken($fileId, $shareToken);

        if (isset($error)) {
            $this->logger->error("Config: $fileId $error");
            return new JSONResponse(["error" => $error]);
        }

        $checkUserAllowGroups = $userId;
        if (!empty($share)) {
            $checkUserAllowGroups = $share->getSharedBy();
        }
        if (!$this->config->isUserAllowedToUse($checkUserAllowGroups)) {
            return new JSONResponse(["error" => $this->trans->t("Not permitted")]);
        }

        $fileName = $file->getName();
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $format = !empty($ext) && array_key_exists($ext, $this->config->formatsSetting()) ? $this->config->formatsSetting()[$ext] : null;
        if (!isset($format)) {
            $this->logger->info("Format is not supported for editing: $fileName");
            return new JSONResponse(["error" => $this->trans->t("Format is not supported")]);
        }

        $fileUrl = $this->getUrl($file, $user, $shareToken, null, $template);

        $key = $this->fileUtility->getKey($file, true);
        $key = DocumentService::generateRevisionId($key);

        $params = [
            "document" => [
                "fileType" => $ext,
                "key" => $key,
                "permissions" => [],
                "title" => $fileName,
                "url" => $fileUrl,
                "referenceData" => [
                    "fileKey" => (string)$file->getId(),
                    "instanceId" => $this->config->getSystemValue("instanceid", true),
                ],
            ],
            "documentType" => $format["type"],
            "editorConfig" => [
                "lang" => str_replace("_", "-", \OC::$server->getL10NFactory()->get("")->getLanguageCode()),
                "region" => str_replace("_", "-", \OC::$server->getL10NFactory()->get("")->getLocaleCode())
            ]
        ];

        $permissions_modifyFilter = $this->config->getSystemValue($this->config->_permissions_modifyFilter);
        if (isset($permissions_modifyFilter)) {
            $params["document"]["permissions"]["modifyFilter"] = $permissions_modifyFilter;
        }

        $canDownload = true;
        $restrictedEditing = false;
        $fileStorage = $file->getStorage();
        if ($fileStorage->instanceOfStorage("\OCA\Files_Sharing\SharedStorage") || !empty($shareToken)) {
            $shareId = empty($share) ? $fileStorage->getShareId() : $share->getId();
            $extraPermissions = null;
            if ($this->extraPermissions !== null) {
                $extraPermissions = $this->extraPermissions->getExtra($shareId);
            }

            if (!empty($extraPermissions)) {
                if (isset($format["review"]) && $format["review"]) {
                    $reviewPermission = ($extraPermissions["permissions"] & ExtraPermissions::REVIEW) === ExtraPermissions::REVIEW;
                    if ($reviewPermission) {
                        $restrictedEditing = true;
                        $params["document"]["permissions"]["review"] = true;
                    }
                }

                if (isset($format["comment"]) && $format["comment"]) {
                    $commentPermission = ($extraPermissions["permissions"] & ExtraPermissions::COMMENT) === ExtraPermissions::COMMENT;
                    if ($commentPermission) {
                        $restrictedEditing = true;
                        $params["document"]["permissions"]["comment"] = true;
                    }
                }

                if (isset($format["fillForms"]) && $format["fillForms"]) {
                    $fillFormsPermission = ($extraPermissions["permissions"] & ExtraPermissions::FILLFORMS) === ExtraPermissions::FILLFORMS;
                    if ($fillFormsPermission) {
                        $restrictedEditing = true;
                        $params["document"]["permissions"]["fillForms"] = true;
                    }
                }

                if (isset($format["modifyFilter"]) && $format["modifyFilter"]) {
                    $modifyFilter = ($extraPermissions["permissions"] & ExtraPermissions::MODIFYFILTER) === ExtraPermissions::MODIFYFILTER;
                    $params["document"]["permissions"]["modifyFilter"] = $modifyFilter;
                }
            }

            if (method_exists(IShare::class, "getAttributes")) {
                $share = empty($share) ? $fileStorage->getShare() : $share;
                $canDownload = FileUtility::canShareDownload($share);
            }
        }

        $isTempLock = false;
        if ($this->lockManager->isLockProviderAvailable()) {
            try {
                $locks = $this->lockManager->getLocks($file->getId());
                $lock = !empty($locks) ? $locks[0] : null;

                if ($lock !== null) {
                    $lockType = $lock->getType();
                    $lockOwner = $lock->getOwner();
                    if (($lockType === ILock::TYPE_APP) && $lockOwner !== $this->appName
                        || ($lockType === ILock::TYPE_USER || $lockType === ILock::TYPE_TOKEN) && $lockOwner !== $userId) {
                        $isTempLock = true;
                        $this->logger->debug("File " . $file->getId() . " is locked by $lockOwner");
                    }
                }
            } catch (PreConditionNotMetException | NoLockProviderException $e) {
            }
        }

        $canEdit = isset($format["edit"]) && $format["edit"];
        $canFillForms = isset($format["fillForms"]) && $format["fillForms"];
        $editable = !$template
                    && $file->isUpdateable()
                    && (empty($shareToken) || ($share->getPermissions() & Constants::PERMISSION_UPDATE) === Constants::PERMISSION_UPDATE)
                    && !$restrictedEditing;
        $params["document"]["permissions"]["edit"] = $editable && !$isTempLock;
        if (($editable || $restrictedEditing) && ($canEdit || $canFillForms) && !$isTempLock) {
            $ownerId = null;
            $owner = $file->getOwner();
            if (!empty($owner)) {
                $ownerId = $owner->getUID();
            }

            $canProtect = true;
            if ($this->config->getProtection() === "owner") {
                $canProtect = $ownerId === $userId;
            }
            $params["document"]["permissions"]["protect"] = $canProtect;

            if (isset($shareToken)) {
                $params["document"]["permissions"]["chat"] = false;
                $params["document"]["permissions"]["protect"] = false;
            }

            if ($canFillForms) {
                $params["document"]["permissions"]["fillForms"] = true;
                $params["canEdit"] = $canEdit && $editable;
            }

            $hashCallback = $this->crypt->getHash(["userId" => $userId, "ownerId" => $ownerId, "fileId" => $file->getId(), "filePath" => $filePath, "shareToken" => $shareToken, "action" => "track"]);
            $callback = $this->urlGenerator->linkToRouteAbsolute($this->appName . ".callback.track", ["doc" => $hashCallback]);

            if (!$this->config->useDemo() && !empty($this->config->getStorageUrl())) {
                $callback = str_replace($this->urlGenerator->getAbsoluteURL("/"), $this->config->getStorageUrl(), $callback);
            }

            $params["editorConfig"]["callbackUrl"] = $callback;
        } else {
            $params["editorConfig"]["mode"] = "view";

            if (isset($shareToken) && empty($userId) && !$this->config->getLiveViewOnShare()) {
                $params["editorConfig"]["coEditing"] = [
                    "mode" => "strict",
                    "change" => false
                ];
            }
        }

        if (!$template
            && $file->isUpdateable()
            && (empty($shareToken) || ($share->getPermissions() & Constants::PERMISSION_UPDATE) === Constants::PERMISSION_UPDATE)) {
            $params["document"]["permissions"]["changeHistory"] = true;
        }

        if (\OC::$server->getRequest()->isUserAgent([$this::USER_AGENT_MOBILE])) {
            $params["type"] = "mobile";
        }

        if (!empty($userId)) {
            $params["editorConfig"]["user"] = [
                "id" => $this->buildUserId($userId),
                "name" => $user->getDisplayName()
            ];
            $avatar = $this->avatarManager->getAvatar($userId);
            if ($avatar->exists() && $avatar->isCustomAvatar()) {
                $userAvatarUrl = $this->urlGenerator->getAbsoluteURL(
                    $this->urlGenerator->linkToRoute("core.avatar.getAvatar", [
                        "userId" => $userId,
                        "size" => 64,
                    ])
                );
                $params["editorConfig"]["user"]["image"] = $userAvatarUrl;
            }
        } elseif (!empty($guestName)) {
            $params["editorConfig"]["user"] = [
                "name" => $guestName
            ];
        }

        $folderLink = null;

        if (!empty($shareToken)) {
            if (method_exists($share, "getHideDownload") && $share->getHideDownload()) {
                $canDownload = false;
            }

            $node = $share->getNode();
            if ($node instanceof Folder) {
                $sharedFolder = $node;
                $folderPath = $sharedFolder->getRelativePath($file->getParent()->getPath());
                if (!empty($folderPath)) {
                    $linkAttr = [
                        "path" => $folderPath,
                        "scrollto" => $file->getName(),
                        "token" => $shareToken
                    ];
                    $folderLink = $this->urlGenerator->linkToRouteAbsolute("files_sharing.sharecontroller.showShare", $linkAttr);
                }
            }
        } elseif (!empty($userId)) {
            $userFolder = $this->root->getUserFolder($userId);
            $folderPath = $userFolder->getRelativePath($file->getParent()->getPath());
            if (!empty($folderPath)) {
                $linkAttr = [
                    "dir" => $folderPath,
                    "scrollto" => $file->getName()
                ];
                $folderLink = $this->urlGenerator->linkToRouteAbsolute("files.view.index", $linkAttr);
            }

            $createParam = [
                "dir" => "/"
            ];

            if (!empty($folderPath)) {
                $folder = $userFolder->get($folderPath);
                if (!empty($folder) && $folder->isCreatable()) {
                    $createParam["dir"] = $folderPath;
                }
            }

            switch ($params["documentType"]) {
                case "word":
                    $createName = $this->trans->t("New document") . ".docx";
                    break;
                case "cell":
                    $createName = $this->trans->t("New spreadsheet") . ".xlsx";
                    break;
                case "slide":
                    $createName = $this->trans->t("New presentation") . ".pptx";
                    break;
                case "pdf":
                    $createName = $this->trans->t("New PDF form") . ".pdf";
                    break;
            }

            if (!empty($createName)) {
                $createParam["name"] = $createName;

                $createUrl = $this->urlGenerator->linkToRouteAbsolute($this->appName . ".editor.create_new", $createParam);
                $params["editorConfig"]["createUrl"] = urldecode($createUrl);
            }

            $templatesList = TemplateManager::getGlobalTemplates($file->getMimeType());
            if (!empty($templatesList)) {
                $templates = [];
                foreach ($templatesList as $templateItem) {
                    $createParam["templateId"] = $templateItem->getId();
                    $createParam["name"] = $templateItem->getName();

                    array_push($templates, [
                        "image" => "",
                        "title" => $templateItem->getName(),
                        "url" => urldecode($this->urlGenerator->linkToRouteAbsolute($this->appName . ".editor.create_new", $createParam))
                    ]);
                }

                $params["editorConfig"]["templates"] = $templates;
            }

            if (!$template) {
                $params["document"]["info"]["favorite"] = $this->isFavorite($fileId, $userId);
            }
            $params["_file_path"] = $userFolder->getRelativePath($file->getPath());
        }

        $canGoBack = $folderLink !== null;
        if ($inviewer) {
            if ($canGoBack) {
                $params["editorConfig"]["customization"]["goback"] = [
                    "url" => $folderLink
                ];
            }
        } elseif (!$desktop
            && $inframe
            && ($this->config->getSameTab()
                || !empty($directToken)
                || $this->config->getEnableSharing() && empty($shareToken))) {
                $params["editorConfig"]["customization"]["close"]["visible"] = true;
        } elseif ($canGoBack) {
            $params["editorConfig"]["customization"]["goback"] = [
                "url" => $folderLink
            ];
        } elseif ($inframe && !empty($shareToken)) {
            $params["editorConfig"]["customization"]["close"]["visible"] = true;
        }

        if (!$canDownload || $this->config->getDisableDownload()) {
            $params["document"]["permissions"]["download"] = false;
            $params["document"]["permissions"]["print"] = false;
            $params["document"]["permissions"]["copy"] = false;
        }

        if ($inframe) {
            $params["_files_sharing"] = \OC::$server->getAppManager()->isInstalled("files_sharing");
        }

        $params = $this->setCustomization($params);

        $params = $this->setWatermark($params, !empty($shareToken), $userId, $file);

        if ($this->config->useDemo()) {
            $params["editorConfig"]["tenant"] = $this->config->getSystemValue("instanceid", true);
        }

        if ($anchor !== null) {
            try {
                $actionLink = json_decode($anchor, true);

                $params["editorConfig"]["actionLink"] = $actionLink;
            } catch (\Exception $e) {
                $this->logger->error("Config: $fileId decode $anchor", ["exception" => $e]);
            }
        }

        if (!empty($this->config->getDocumentServerUrl())) {
            $params["documentServerUrl"] = $this->config->getDocumentServerUrl();
        }

        if (!empty($this->config->getDocumentServerSecret())) {
            $now = time();
            $iat = $now;
            $exp = $now + $this->config->getJwtExpiration() * 60;
            $params["iat"] = $iat;
            $params["exp"] = $exp;
            $token = \Firebase\JWT\JWT::encode($params, $this->config->getDocumentServerSecret(), "HS256");
            $params["token"] = $token;
        }

        $this->logger->debug("Config is generated for: $fileId with key $key");

        return new JSONResponse($params);
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
        if (empty($userId)) {
            return [null, $this->trans->t("UserId is empty"), null];
        }

        if (empty($fileId)) {
            return [null, $this->trans->t("FileId is empty"), null];
        }

        try {
            $folder = !$template ? $this->root->getUserFolder($userId) : TemplateManager::getGlobalTemplateDir();
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
     * @param bool $changes - is required url to file changes
     * @param bool $template - file is template
     *
     * @return string
     */
    private function getUrl($file, $user = null, $shareToken = null, $changes = false, $template = false) {

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
        if ($changes) {
            $data["changes"] = true;
        }
        if ($template) {
            $data["template"] = true;
        }

        $hashUrl = $this->crypt->getHash($data);

        $fileUrl = $this->urlGenerator->linkToRouteAbsolute($this->appName . ".callback.download", ["doc" => $hashUrl]);

        if (!$this->config->useDemo() && !empty($this->config->getStorageUrl())) {
            $fileUrl = str_replace($this->urlGenerator->getAbsoluteURL("/"), $this->config->getStorageUrl(), $fileUrl);
        }

        return $fileUrl;
    }

    /**
     * Generate unique user identifier
     *
     * @param string $userId - current user identifier
     *
     * @return string
     */
    private function buildUserId($userId) {
        $instanceId = $this->config->getSystemValue("instanceid", true);
        $userId = $instanceId . "_" . $userId;
        return $userId;
    }

    /**
     * Set customization parameters
     *
     * @param array params - file parameters
     *
     * @return array
     */
    private function setCustomization($params) {
        //default is true
        if ($this->config->getCustomizationChat() === false) {
            $params["editorConfig"]["customization"]["chat"] = false;
        }

        //default is false
        if ($this->config->getCustomizationCompactHeader() === true) {
            $params["editorConfig"]["customization"]["compactHeader"] = true;
        }

        //default is false
        if ($this->config->getCustomizationFeedback() === true) {
            $params["editorConfig"]["customization"]["feedback"] = true;
        }

        //default is false
        if ($this->config->getCustomizationForcesave() === true) {
            $params["editorConfig"]["customization"]["forcesave"] = true;
        }

        //default is true
        if ($this->config->getCustomizationHelp() === false) {
            $params["editorConfig"]["customization"]["help"] = false;
        }

        //default is original
        $reviewDisplay = $this->config->getCustomizationReviewDisplay();
        if ($reviewDisplay !== "original") {
            $params["editorConfig"]["customization"]["reviewDisplay"] = $reviewDisplay;
        }

        $theme = $this->config->getCustomizationTheme();
        if (isset($theme)) {
            $params["editorConfig"]["customization"]["uiTheme"] = $theme;
        }

        //default is true
        if ($this->config->getCustomizationMacros() === false) {
            $params["editorConfig"]["customization"]["macros"] = false;
        }

        //default is true
        if ($this->config->getCustomizationPlugins() === false) {
            $params["editorConfig"]["customization"]["plugins"] = false;
        }

        /* from system config */

        $autosave = $this->config->getSystemValue($this->config->_customization_autosave);
        if (isset($autosave)) {
            $params["editorConfig"]["customization"]["autosave"] = $autosave;
        }

        $customer = $this->config->getSystemValue($this->config->_customization_customer);
        if (isset($customer)) {
            $params["editorConfig"]["customization"]["customer"] = $customer;
        }

        $loaderLogo = $this->config->getSystemValue($this->config->_customization_loaderLogo);
        if (isset($loaderLogo)) {
            $params["editorConfig"]["customization"]["loaderLogo"] = $loaderLogo;
        }

        $loaderName = $this->config->getSystemValue($this->config->_customization_loaderName);
        if (isset($loaderName)) {
            $params["editorConfig"]["customization"]["loaderName"] = $loaderName;
        }

        $logo = $this->config->getSystemValue($this->config->_customization_logo);
        if (isset($logo)) {
            $params["editorConfig"]["customization"]["logo"] = $logo;
        }

        $zoom = $this->config->getSystemValue($this->config->_customization_zoom);
        if (isset($zoom)) {
            $params["editorConfig"]["customization"]["zoom"] = $zoom;
        }

        return $params;
    }

    /**
     * Set watermark parameters
     *
     * @param array params - file parameters
     * @param bool isPublic - with access token
     * @param string userId - user identifier
     * @param string file - file
     *
     * @return array
     */
    private function setWatermark($params, $isPublic, $userId, $file) {
        $watermarkTemplate = $this->getWatermarkText(
            $isPublic,
            $userId,
            $file,
            $params["document"]["permissions"]["edit"] !== false,
            !array_key_exists("download", $params["document"]["permissions"]) || $params["document"]["permissions"]["download"] !== false
        );

        if ($watermarkTemplate !== false) {
            if (empty($userId)) {
                $timezone = $this->timezoneService->getDefaultTimezone();
            } else {
                $timezone = $this->timezoneService->getUserTimezone($userId) ?? $this->timezoneService->getDefaultTimezone();
            }
            $replacements = [
                "userId" => isset($userId) ? $userId : $this->trans->t('Anonymous'),
                "date" => (new \DateTime("now", new \DateTimeZone($timezone)))->format("Y-m-d H:i:s"),
                "themingName" => \OC::$server->getThemingDefaults()->getName()
            ];
            $watermarkTemplate = preg_replace_callback("/{(.+?)}/", function ($matches) use ($replacements) {
                return $replacements[$matches[1]];
            }, $watermarkTemplate);

            $params["document"]["options"] = [
                "watermark_on_draw" => [
                    "align" => 1,
                    "height" => 100,
                    "paragraphs" => array([
                        "align" => 2,
                        "runs" => array([
                            "fill" => [182, 182, 182],
                            "font-size" => 70,
                            "text" => $watermarkTemplate,
                        ])
                    ]),
                    "rotate" => -45,
                    "width" => 250,
                ]
            ];
        }

        return $params;
    }

    /**
     * Should watermark
     *
     * @param bool isPublic - with access token
     * @param string userId - user identifier
     * @param string file - file
     * @param bool canEdit - edit permission
     * @param bool canDownload - download permission
     *
     * @return bool|string
     */
    private function getWatermarkText($isPublic, $userId, $file, $canEdit, $canDownload) {
        $watermarkSettings = $this->config->getWatermarkSettings();
        if (!$watermarkSettings["enabled"]) {
            return false;
        }

        $watermarkText = $watermarkSettings["text"];
        $fileId = $file->getId();

        if ($isPublic) {
            if ($watermarkSettings["linkAll"]) {
                return $watermarkText;
            }
            if ($watermarkSettings["linkRead"] && !$canEdit) {
                return $watermarkText;
            }
            if ($watermarkSettings["linkSecure"] && !$canDownload) {
                return $watermarkText;
            }
            if ($watermarkSettings["linkTags"]) {
                $tags = $watermarkSettings["linkTagsList"];
                $fileTags = \OC::$server->getSystemTagObjectMapper()->getTagIdsForObjects([$fileId], "files")[$fileId];
                foreach ($fileTags as $tagId) {
                    if (in_array($tagId, $tags, true)) {
                        return $watermarkText;
                    }
                }
            }
        } else {
            if ($watermarkSettings["shareAll"]
                && ($file->getOwner() === null || $file->getOwner()->getUID() !== $userId)) {
                return $watermarkText;
            }
            if ($watermarkSettings["shareRead"] && !$canEdit) {
                return $watermarkText;
            }
        }
        if ($watermarkSettings["allGroups"]
            && $userId !== null) {
            $groups = $watermarkSettings["allGroupsList"];
            foreach ($groups as $group) {
                if (\OC::$server->getGroupManager()->isInGroup($userId, $group)) {
                    return $watermarkText;
                }
            }
        }
        if ($watermarkSettings["allTags"]) {
            $tags = $watermarkSettings["allTagsList"];
            $fileTags = \OC::$server->getSystemTagObjectMapper()->getTagIdsForObjects([$fileId], "files")[$fileId];
            foreach ($fileTags as $tagId) {
                if (in_array($tagId, $tags)) {
                    return $watermarkText;
                }
            }
        }

        return false;
    }

    /**
     * Check file favorite
     *
     * @param integer $fileId - file identifier
     * @param string $userId - user identifier
     *
     * @return bool
     */
    private function isFavorite($fileId, $userId = null) {
        $currentTags = $this->tagManager->load("files", [], false, $userId)->getTagsForObjects([$fileId]);
        if ($currentTags) {
            return in_array(ITags::TAG_FAVORITE, $currentTags[$fileId]);
        }

        return false;
    }
}
