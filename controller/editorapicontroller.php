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

use OCP\AppFramework\OCSController;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\QueryException;
use OCP\Constants;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IRequest;
use OCP\ISession;
use OCP\ITags;
use OCP\ITagManager;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Share\IManager;

use OCA\Files_Versions\Versions\IVersionManager;
use OCA\FilesLock\Service\LockService;
use OCA\FilesLock\Exceptions\LockNotFoundException;

use OCA\Onlyoffice\AppConfig;
use OCA\Onlyoffice\Crypt;
use OCA\Onlyoffice\DocumentService;
use OCA\Onlyoffice\FileUtility;
use OCA\Onlyoffice\TemplateManager;

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
     * Tag manager
     *
     * @var ITagManager
    */
    private $tagManager;

    /**
     * Mobile regex from https://github.com/ONLYOFFICE/CommunityServer/blob/v9.1.1/web/studio/ASC.Web.Studio/web.appsettings.config#L35
     */
    const USER_AGENT_MOBILE = "/android|avantgo|playbook|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od|ad)|iris|kindle|lge |maemo|midp|mmp|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\\/|plucker|pocket|psp|symbian|treo|up\\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i";

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
     * @param ISession $ISession - Session
     * @param ITagManager $tagManager - Tag manager
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
                                    ITagManager $tagManager
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
     * Collecting the file parameters for the document service
     *
     * @param integer $fileId - file identifier
     * @param string $filePath - file path
     * @param string $shareToken - access token
     * @param string $directToken - direct token
     * @param integer $version - file version
     * @param bool $inframe - open in frame
     * @param bool $desktop - desktop label
     * @param string $guestName - nickname not logged user
     * @param bool $template - file is template
     * @param string $anchor - anchor for file content
     *
     * @return JSONResponse
     *
     * @NoAdminRequired
     * @PublicPage
     */
    public function config($fileId, $filePath = null, $shareToken = null, $directToken = null, $version = 0, $inframe = false, $desktop = false, $guestName = null, $template = false, $anchor = null) {

        if (!empty($directToken)) {
            list ($directData, $error) = $this->crypt->ReadHash($directToken);
            if ($directData === null) {
                $this->logger->error("Config for directEditor with empty or not correct hash: $error", ["app" => $this->appName]);
                return new JSONResponse(["error" => $this->trans->t("Not permitted")]);
            }
            if ($directData->action !== "direct") {
                $this->logger->error("Config for directEditor with other data", ["app" => $this->appName]);
                return new JSONResponse(["error" => $this->trans->t("Invalid request")]);
            }

            $fileId = $directData->fileId;
            $userId = $directData->userId;
            if ($this->userSession->isLoggedIn()
                && $userId === $this->userSession->getUser()->getUID()) {
                $redirectUrl = $this->urlGenerator->linkToRouteAbsolute($this->appName . ".editor.index",
                    [
                        "fileId" => $fileId,
                        "filePath" => $filePath
                    ]);
                return new JSONResponse(["redirectUrl" => $redirectUrl]);
            }

            $user = $this->userManager->get($userId);
        } else {
            $user = $this->userSession->getUser();
            $userId = null;
            if (!empty($user)) {
                $userId = $user->getUID();
            }
        }

        list ($file, $error, $share) = empty($shareToken) ? $this->getFile($userId, $fileId, $filePath, $template) : $this->fileUtility->getFileByToken($fileId, $shareToken);

        if (isset($error)) {
            $this->logger->error("Config: $fileId $error", ["app" => $this->appName]);
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
        $format = !empty($ext) ? $this->config->FormatsSetting()[$ext] : null;
        if (!isset($format)) {
            $this->logger->info("Format is not supported for editing: $fileName", ["app" => $this->appName]);
            return new JSONResponse(["error" => $this->trans->t("Format is not supported")]);
        }

        $fileUrl = $this->getUrl($file, $user, $shareToken, $version, null, $template);

        $key = null;
        if ($version > 0
            && $this->versionManager !== null) {
            $owner = $file->getFileInfo()->getOwner();
            if ($owner !== null) {
                $versions = array_reverse($this->versionManager->getVersionsForFile($owner, $file->getFileInfo()));

                if ($version <= count($versions)) {
                    $fileVersion = array_values($versions)[$version - 1];

                    $key = $this->fileUtility->getVersionKey($fileVersion);
                }
            }
        }
        if ($key === null) {
            $key = $this->fileUtility->getKey($file, true);
        }
        $key = DocumentService::GenerateRevisionId($key);

        $params = [
            "document" => [
                "fileType" => $ext,
                "key" => $key,
                "permissions" => [],
                "title" => $fileName,
                "url" => $fileUrl,
            ],
            "documentType" => $format["type"],
            "editorConfig" => [
                "lang" => str_replace("_", "-", \OC::$server->getL10NFactory("")->get("")->getLanguageCode()),
                "region" => str_replace("_", "-", \OC::$server->getL10NFactory("")->findLocale())
            ]
        ];

        $permissions_modifyFilter = $this->config->GetSystemValue($this->config->_permissions_modifyFilter);
        if (isset($permissions_modifyFilter)) {
            $params["document"]["permissions"]["modifyFilter"] = $permissions_modifyFilter;
        }

        $isTempLock = false;
        if ($version < 1
            && \OC::$server->getAppManager()->isInstalled("files_lock")) {
            try {
                $lockService = \OC::$server->get(LockService::class);
                $lock = $lockService->getLockFromFileId($file->getId());
                $lockOwner = $lock->getUserId();
                if ($userId !== $lockOwner) {
                    $isTempLock = true;
                    $this->logger->debug("File" . $file->getId() . "is locked by $lockOwner", ["app" => $this->appName]);
                }
            } catch (LockNotFoundException $e) {}
        }

        $canEdit = isset($format["edit"]) && $format["edit"];
        $editable = $version < 1
                    && !$template
                    && $file->isUpdateable()
                    && !$isTempLock
                    && (empty($shareToken) || ($share->getPermissions() & Constants::PERMISSION_UPDATE) === Constants::PERMISSION_UPDATE);
        $params["document"]["permissions"]["edit"] = $editable;
        if ($editable && $canEdit) {
            $hashCallback = $this->crypt->GetHash(["userId" => $userId, "fileId" => $file->getId(), "filePath" => $filePath, "shareToken" => $shareToken, "action" => "track"]);
            $callback = $this->urlGenerator->linkToRouteAbsolute($this->appName . ".callback.track", ["doc" => $hashCallback]);

            if (!empty($this->config->GetStorageUrl())) {
                $callback = str_replace($this->urlGenerator->getAbsoluteURL("/"), $this->config->GetStorageUrl(), $callback);
            }

            $params["editorConfig"]["callbackUrl"] = $callback;
        } else {
            $params["editorConfig"]["mode"] = "view";
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
        } else if (!empty($guestName)) {
            $params["editorConfig"]["user"] = [
                "name" => $guestName
            ];
        }

        $folderLink = null;

        if (!empty($shareToken)) {
            if (method_exists($share, "getHideDownload") && $share->getHideDownload()) {
                $params["document"]["permissions"]["download"] = false;
                $params["document"]["permissions"]["print"] = false;
                $params["document"]["permissions"]["copy"] = false;
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
        } else if (!empty($userId)) {
            $userFolder = $this->root->getUserFolder($userId);
            $folderPath = $userFolder->getRelativePath($file->getParent()->getPath());
            if (!empty($folderPath)) {
                $linkAttr = [
                    "dir" => $folderPath,
                    "scrollto" => $file->getName()
                ];
                $folderLink = $this->urlGenerator->linkToRouteAbsolute("files.view.index", $linkAttr);
            }

            switch ($params["documentType"]) {
                case "word":
                    $createName = $this->trans->t("Document") . ".docx";
                    break;
                case "cell":
                    $createName = $this->trans->t("Spreadsheet") . ".xlsx";
                    break;
                case "slide":
                    $createName = $this->trans->t("Presentation") . ".pptx";
                    break;
            }

            $createParam = [
                "dir" => "/",
                "name" => $createName
            ];

            if (!empty($folderPath)) {
                $folder = $userFolder->get($folderPath);
                if (!empty($folder) && $folder->isCreatable()) {
                    $createParam["dir"] = $folderPath;
                }
            }

            $createUrl = $this->urlGenerator->linkToRouteAbsolute($this->appName . ".editor.create_new", $createParam);
            $params["editorConfig"]["createUrl"] = urldecode($createUrl);

            $templatesList = TemplateManager::GetGlobalTemplates($file->getMimeType());
            if (!empty($templatesList)) {
                $templates = [];
                foreach($templatesList as $templateItem) {
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

        if ($folderLink !== null
            && $this->config->GetSystemValue($this->config->_customization_goback) !== false) {
            $params["editorConfig"]["customization"]["goback"] = [
                "url"  => $folderLink
            ];

            if (!$desktop) {
                if ($this->config->GetSameTab()) {
                    $params["editorConfig"]["customization"]["goback"]["blank"] = false;
                }

                if ($inframe === true || !empty($directToken)) {
                    $params["editorConfig"]["customization"]["goback"]["requestClose"] = true;
                }
            }
        }

        if ($inframe === true) {
            $params["_files_sharing"] = \OC::$server->getAppManager()->isInstalled("files_sharing");
        }

        $params = $this->setCustomization($params);

        $params = $this->setWatermark($params, !empty($shareToken), $userId, $file);

        if ($this->config->UseDemo()) {
            $params["editorConfig"]["tenant"] = $this->config->GetSystemValue("instanceid", true);
        }

        if ($anchor !== null) {
            try {
                $actionLink = json_decode($anchor, true);

                $params["editorConfig"]["actionLink"] = $actionLink;
            } catch (\Exception $e) {
                $this->logger->logException($e, ["message" => "Config: $fileId decode $anchor", "app" => $this->appName]);
            }
        }

        if (!empty($this->config->GetDocumentServerSecret())) {
            $token = \Firebase\JWT\JWT::encode($params, $this->config->GetDocumentServerSecret());
            $params["token"] = $token;
        }

        $this->logger->debug("Config is generated for: $fileId ($version) with key $key", ["app" => $this->appName]);

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
     * Set customization parameters
     *
     * @param array params - file parameters
     *
     * @return array
     */
    private function setCustomization($params) {
        //default is true
        if ($this->config->GetCustomizationChat() === false) {
            $params["editorConfig"]["customization"]["chat"] = false;
        }

        //default is false
        if ($this->config->GetCustomizationCompactHeader() === true) {
            $params["editorConfig"]["customization"]["compactHeader"] = true;
        }

        //default is false
        if ($this->config->GetCustomizationFeedback() === true) {
            $params["editorConfig"]["customization"]["feedback"] = true;
        }

        //default is false
        if ($this->config->GetCustomizationForcesave() === true) {
            $params["editorConfig"]["customization"]["forcesave"] = true;
        }

        //default is true
        if ($this->config->GetCustomizationHelp() === false) {
            $params["editorConfig"]["customization"]["help"] = false;
        }

        //default is original
        $reviewDisplay = $this->config->GetCustomizationReviewDisplay();
        if ($reviewDisplay !== "original") {
            $params["editorConfig"]["customization"]["reviewDisplay"] = $reviewDisplay;
        }

        //default is false
        if ($this->config->GetCustomizationToolbarNoTabs() === true) {
            $params["editorConfig"]["customization"]["toolbarNoTabs"] = true;
        }


        /* from system config */

        $autosave = $this->config->GetSystemValue($this->config->_customization_autosave);
        if (isset($autosave)) {
            $params["editorConfig"]["customization"]["autosave"] = $autosave;
        }

        $customer = $this->config->GetSystemValue($this->config->_customization_customer);
        if (isset($customer)) {
            $params["editorConfig"]["customization"]["customer"] = $customer;
        }

        $loaderLogo = $this->config->GetSystemValue($this->config->_customization_loaderLogo);
        if (isset($loaderLogo)) {
            $params["editorConfig"]["customization"]["loaderLogo"] = $loaderLogo;
        }

        $loaderName = $this->config->GetSystemValue($this->config->_customization_loaderName);
        if (isset($loaderName)) {
            $params["editorConfig"]["customization"]["loaderName"] = $loaderName;
        }

        $logo = $this->config->GetSystemValue($this->config->_customization_logo);
        if (isset($logo)) {
            $params["editorConfig"]["customization"]["logo"] = $logo;
        }

        $zoom = $this->config->GetSystemValue($this->config->_customization_zoom);
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
        $watermarkTemplate = $this->getWatermarkText($isPublic, $userId, $file,
            $params["document"]["permissions"]["edit"] !== false,
            !array_key_exists("download", $params["document"]["permissions"]) || $params["document"]["permissions"]["download"] !== false);

        if ($watermarkTemplate !== false) {
            $replacements = [
                "userId" => $userId,
                "date" => (new \DateTime())->format("Y-m-d H:i:s"),
                "themingName" => \OC::$server->getThemingDefaults()->getName()
            ];
            $watermarkTemplate = preg_replace_callback("/{(.+?)}/", function ($matches) use ($replacements)
                {
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
        $watermarkSettings = $this->config->GetWatermarkSettings();
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
        if ($watermarkSettings["allGroups"]) {
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
                if (in_array($tagId, $tags, true)) {
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