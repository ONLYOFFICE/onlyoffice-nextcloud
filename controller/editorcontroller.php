<?php
/**
 *
 * (c) Copyright Ascensio System Limited 2010-2018
 *
 * This program is freeware. You can redistribute it and/or modify it under the terms of the GNU
 * General Public License (GPL) version 3 as published by the Free Software Foundation (https://www.gnu.org/copyleft/gpl.html).
 * In accordance with Section 7(a) of the GNU GPL its Section 15 shall be amended to the effect that
 * Ascensio System SIA expressly excludes the warranty of non-infringement of any third-party rights.
 *
 * THIS PROGRAM IS DISTRIBUTED WITHOUT ANY WARRANTY; WITHOUT EVEN THE IMPLIED WARRANTY OF MERCHANTABILITY OR
 * FITNESS FOR A PARTICULAR PURPOSE. For more details, see GNU GPL at https://www.gnu.org/copyleft/gpl.html
 *
 * You can contact Ascensio System SIA by email at sales@onlyoffice.com
 *
 * The interactive user interfaces in modified source and object code versions of ONLYOFFICE must display
 * Appropriate Legal Notices, as required under Section 5 of the GNU GPL version 3.
 *
 * Pursuant to Section 7 § 3(b) of the GNU GPL you must retain the original ONLYOFFICE logo which contains
 * relevant author attributions when distributing the software. If the display of the logo in its graphic
 * form is not reasonably feasible for technical reasons, you must include the words "Powered by ONLYOFFICE"
 * in every copy of the program you distribute.
 * Pursuant to Section 7 § 3(e) we decline to grant you any rights under trademark law for use of our trademarks.
 *
 */

namespace OCA\Onlyoffice\Controller;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Controller;
use OCP\AutoloadNotAllowedException;
use OCP\Constants;
use OCP\Files\FileInfo;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IRequest;
use OCP\ISession;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCP\Share\IManager;

use OC\Files\Filesystem;

use OCA\Files\Helper;

use OCA\Onlyoffice\AppConfig;
use OCA\Onlyoffice\Crypt;
use OCA\Onlyoffice\DocumentService;

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
     * @var OCA\Onlyoffice\AppConfig
     */
    private $config;

    /**
     * Hash generator
     *
     * @var OCA\Onlyoffice\Crypt
     */
    private $crypt;

    /**
     * Share manager
     *
     * @var IManager
     */
    private $shareManager;

    /**
     * Session
     *
     * @var ISession
     */
    private $session;

    /**
     * Mobile regex from https://github.com/ONLYOFFICE/CommunityServer/blob/v9.1.1/web/studio/ASC.Web.Studio/web.appsettings.config#L35
     */
    const USER_AGENT_MOBILE = "/android|avantgo|playbook|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od|ad)|iris|kindle|lge |maemo|midp|mmp|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\\/|plucker|pocket|psp|symbian|treo|up\\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i";

    /**
     * @param string $AppName - application name
     * @param IRequest $request - request object
     * @param IRootFolder $root - root folder
     * @param IUserSession $userSession - current user session
     * @param IURLGenerator $urlGenerator - url generator service
     * @param IL10N $trans - l10n service
     * @param ILogger $logger - logger
     * @param OCA\Onlyoffice\AppConfig $config - application configuration
     * @param OCA\Onlyoffice\Crypt $crypt - hash generator
     * @param IManager $shareManager - Share manager
     * @param IManager $ISession - Session
     */
    public function __construct($AppName,
                                    IRequest $request,
                                    IRootFolder $root,
                                    IUserSession $userSession,
                                    IURLGenerator $urlGenerator,
                                    IL10N $trans,
                                    ILogger $logger,
                                    AppConfig $config,
                                    Crypt $crypt,
                                    IManager $shareManager,
                                    ISession $session
                                    ) {
        parent::__construct($AppName, $request);

        $this->userSession = $userSession;
        $this->root = $root;
        $this->urlGenerator = $urlGenerator;
        $this->trans = $trans;
        $this->logger = $logger;
        $this->config = $config;
        $this->crypt = $crypt;
        $this->shareManager = $shareManager;
        $this->session = $session;
    }

    /**
     * Create new file in folder
     *
     * @param string $name - file name
     * @param string $dir - folder path
     *
     * @return array
     *
     * @NoAdminRequired
     */
    public function create($name, $dir) {
        $this->logger->debug("Create: " . $name, array("app" => $this->appName));

        $userId = $this->userSession->getUser()->getUID();
        $userFolder = $this->root->getUserFolder($userId);
        $folder = $userFolder->get($dir);

        if ($folder === NULL) {
            $this->logger->info("Folder for file creation was not found: " . $dir, array("app" => $this->appName));
            return ["error" => $this->trans->t("The required folder was not found")];
        }
        if (!$folder->isCreatable()) {
            $this->logger->info("Folder for file creation without permission: " . $dir, array("app" => $this->appName));
            return ["error" => $this->trans->t("You don't have enough permission to create")];
        }

        $name = $folder->getNonExistingName($name);
        $filePath = $dir . DIRECTORY_SEPARATOR . $name;
        $ext = strtolower("." . pathinfo($filePath, PATHINFO_EXTENSION));

        $lang = \OC::$server->getL10NFactory("")->get("")->getLanguageCode();

        $templatePath = $this->getTemplatePath($lang, $ext);
        if (!file_exists($templatePath)) {
            $lang = "en";
            $templatePath = $this->getTemplatePath($lang, $ext);
        }

        $template = file_get_contents($templatePath);
        if (!$template) {
            $this->logger->info("Template for file creation not found: " . $templatePath, array("app" => $this->appName));
            return ["error" => $this->trans->t("Template not found")];
        }

        $view = Filesystem::getView();
        if (!$view->file_put_contents($filePath, $template)) {
            $this->logger->error("Can't create file: " . $filePath, array("app" => $this->appName));
            return ["error" => $this->trans->t("Can't create file")];
        }

        $fileInfo = $view->getFileInfo($filePath);

        if ($fileInfo === false) {
            $this->logger->info("File not found: " . $filePath, array("app" => $this->appName));
            return ["error" => $this->trans->t("File not found")];
        }

        $result = Helper::formatFileInfo($fileInfo);
        return $result;
    }

    private function getTemplatePath($lang, $ext) {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . "assets" . DIRECTORY_SEPARATOR . $lang . DIRECTORY_SEPARATOR . "new" . $ext;
    }

    /**
     * Conversion file to Office Open XML format
     *
     * @param integer $fileId - file identifier
     *
     * @return array
     *
     * @NoAdminRequired
     */
    public function convert($fileId) {
        $this->logger->debug("Convert: " . $fileId, array("app" => $this->appName));

        list ($file, $error) = $this->getFile($fileId);

        if (isset($error)) {
            $this->logger->error("Convertion: " . $fileId . " " . $error, array("app" => $this->appName));
            return ["error" => $error];
        }

        $fileName = $file->getName();
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $format = $this->config->formats[$ext];
        if (!isset($format)) {
            $this->logger->info("Format for convertion not supported: " . $fileName, array("app" => $this->appName));
            return ["error" => $this->trans->t("Format is not supported")];
        }

        if (!isset($format["conv"]) || $format["conv"] !== TRUE) {
            $this->logger->debug("Conversion is not required: " . $fileName, array("app" => $this->appName));
            return ["error" => $this->trans->t("Conversion is not required")];
        }

        $internalExtension = "docx";
        switch ($format["type"]) {
            case "spreadsheet":
                $internalExtension = "xlsx";
                break;
            case "presentation":
                $internalExtension = "pptx";
                break;
        }

        $newFileUri;
        $documentService = new DocumentService($this->trans, $this->config);
        $key = $this->getKey($file);
        $fileId = $file->getId();
        $fileUrl = $this->getUrl($fileId);
        try {
            $newFileUri = $documentService->GetConvertedUri($fileUrl, $ext, $internalExtension, $key);
        } catch (\Exception $e) {
            $this->logger->error("GetConvertedUri: " . $fileId . " " . $e->getMessage(), array("app" => $this->appName));
            return ["error" => $e->getMessage()];
        }

        $userId = $this->userSession->getUser()->getUID();
        $folder = $file->getParent();
        if (!$folder->isCreatable()) {
            $folder = $this->root->getUserFolder($userId);
        }
        $pattern = "/^\\" . DIRECTORY_SEPARATOR . $userId . "\\" . DIRECTORY_SEPARATOR . "files/";
        $newFolderPath = preg_replace($pattern, "", $folder->getPath());

        $fileNameWithoutExt = substr($fileName, 0, strlen($fileName) - strlen($ext) - 1);
        $newFileName = $folder->getNonExistingName($fileNameWithoutExt . "." . $internalExtension);

        $newFilePath = $newFolderPath . DIRECTORY_SEPARATOR . $newFileName;

        if (($newData = $documentService->Request($newFileUri)) === FALSE) {
            $this->logger->error("Failed to download converted file: " . $newFileUri, array("app" => $this->appName));
            return ["error" => $this->trans->t("Failed to download converted file")];
        }

        $view = Filesystem::getView();
        if (!$view->file_put_contents($newFilePath, $newData)) {
            $this->logger->error("Can't create file after convertion: " . $newFilePath, array("app" => $this->appName));
            return ["error" => $this->trans->t("Can't create file")];
        }

        $fileInfo = $view->getFileInfo($newFilePath);

        if ($fileInfo === false) {
            $this->logger->info("File not found: " . $newFilePath, array("app" => $this->appName));
            return ["error" => $this->trans->t("File not found")];
        }

        $result = Helper::formatFileInfo($fileInfo);
        return $result;
    }

    /**
     * Print editor section
     *
     * @param integer $fileId - file identifier
     * @param string $token - access token
     *
     * @return TemplateResponse
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     */
    public function index($fileId, $token = NULL) {
        $this->logger->debug("Open: " . $fileId, array("app" => $this->appName));

        $documentServerUrl = $this->config->GetDocumentServerUrl();

        if (empty($documentServerUrl)) {
            $this->logger->error("documentServerUrl is empty", array("app" => $this->appName));
            return ["error" => $this->trans->t("ONLYOFFICE app is not configured. Please contact admin")];
        }

        $params = [
            "documentServerUrl" => $documentServerUrl,
            "fileId" => $fileId,
            "token" => $token
        ];

        $response = new TemplateResponse($this->appName, "editor", $params);

        $csp = new ContentSecurityPolicy();
        $csp->allowInlineScript(true);

        if (preg_match("/^https?:\/\//i", $documentServerUrl)) {
            $csp->addAllowedScriptDomain($documentServerUrl);
            $csp->addAllowedFrameDomain($documentServerUrl);
        } else {
            $csp->addAllowedFrameDomain($this->urlGenerator->getAbsoluteURL("/"));
        }
        $response->setContentSecurityPolicy($csp);

        return $response;
    }

    /**
     * Print public editor section
     *
     * @param string $token - access token
     *
     * @return TemplateResponse
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     */
    public function PublicPage($token) {
        return $this->index(0, $token);
    }

    /**
     * Collecting the file parameters for the document service
     *
     * @param integer $fileId - file identifier
     * @param string $token - access token
     *
     * @return array
     *
     * @NoAdminRequired
     * @PublicPage
     */
    public function config($fileId, $token = NULL) {

        list ($file, $error) = empty($token) ? $this->getFile($fileId) : $this->getFileByToken($fileId, $token);

        if (isset($error)) {
            $this->logger->error("Config: " . $fileId . " " . $error, array("app" => $this->appName));
            return ["error" => $error];
        }

        $fileName = $file->getName();
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $format = $this->config->formats[$ext];
        if (!isset($format)) {
            $this->logger->info("Format is not supported for editing: " . $fileName, array("app" => $this->appName));
            return ["error" => $this->trans->t("Format is not supported")];
        }

        $fileId = $file->getId();
        $fileUrl = $this->getUrl($fileId, $token);
        $key = $this->getKey($file);

        $params = [
            "document" => [
                "fileType" => $ext,
                "key" => DocumentService::GenerateRevisionId($key),
                "title" => $fileName,
                "url" => $fileUrl,
            ],
            "documentType" => $format["type"],
            "editorConfig" => [
                "lang" => str_replace("_", "-", \OC::$server->getL10NFactory("")->get("")->getLanguageCode())
            ]
        ];

        if (\OC::$server->getRequest()->isUserAgent([$this::USER_AGENT_MOBILE])) {
            $params["type"] = "mobile";
        }

        $canEdit = isset($format["edit"]) && $format["edit"];
        $editable = $file->isUpdateable()
                    && (empty($token) || ($this->getShare($token)[0]->getPermissions() & Constants::PERMISSION_UPDATE) === Constants::PERMISSION_UPDATE);
        if ($editable && $canEdit) {
            $hashCallback = $this->crypt->GetHash(["fileId" => $fileId, "ownerId" => $file->getOwner()->getUID(), "token" => $token, "action" => "track"]);
            $callback = $this->urlGenerator->linkToRouteAbsolute($this->appName . ".callback.track", ["doc" => $hashCallback]);

            if (!empty($this->config->GetStorageUrl())) {
                $callback = str_replace($this->urlGenerator->getAbsoluteURL("/"), $this->config->GetStorageUrl(), $callback);
            }

            $params["editorConfig"]["callbackUrl"] = $callback;
        } else {
            $params["editorConfig"]["mode"] = "view";
        }

        $user = $this->userSession->getUser();
        $userId = NULL;
        if (!empty($user)) {
            $userId = $user->getUID();
        }

        if (!empty($userId)) {
            $params["editorConfig"]["user"] = [
                "id" => $userId,
                "name" => $user->getDisplayName()
            ];

            $userFolder = $this->root->getUserFolder($userId);
            $folderPath = $userFolder->getRelativePath($file->getParent()->getPath());
            $linkAttr = NULL;
            if (!empty($folderPath)) {
                $linkAttr = [
                    "dir" => $folderPath,
                    "scrollto" => $file->getName()
                ];
            }
            $folderLink = $this->urlGenerator->linkToRouteAbsolute("files.view.index", $linkAttr);

            $params["editorConfig"]["customization"]["goback"] = [
                "url"  => $folderLink
            ];
        }

        $params = $this->setCustomization($params);

        if (!empty($this->config->GetDocumentServerSecret())) {
            $token = \Firebase\JWT\JWT::encode($params, $this->config->GetDocumentServerSecret());
            $params["token"] = $token;
        }

        $this->logger->debug("Config is generated for: " . $fileId . " with key " . $key, array("app" => $this->appName));

        return $params;
    }

    /**
     * Getting file by identifier
     *
     * @param integer $fileId - file identifier
     *
     * @return array
     */
    private function getFile($fileId) {
        if (empty($fileId)) {
            return [NULL, $this->trans->t("FileId is empty")];
        }

        $files = $this->root->getById($fileId);
        if (empty($files)) {
            return [NULL, $this->trans->t("File not found")];
        }
        $file = $files[0];

        if (!$file->isReadable()) {
            return [NULL, $this->trans->t("You do not have enough permissions to view the file")];
        }
        return [$file, NULL];
    }

    /**
     * Getting file by token
     *
     * @param integer $fileId - file identifier
     * @param string $token - access token
     *
     * @return array
     */
    private function getFileByToken($fileId, $token) {
        list ($share, $error) = $this->getShare($token);

        if (isset($error)) {
            return [NULL, $error];
        }

        if (($share->getPermissions() & Constants::PERMISSION_READ) === 0) {
            return [NULL, $this->trans->t("You do not have enough permissions to view the file")];
        }

        $node = $share->getNode();

        if ($node instanceof Folder) {
            $file = $node->getById($fileId)[0];
        } else {
            $file = $node;
        }

        return [$file, NULL];
    }

    /**
     * Getting share by token
     *
     * @param string $token - access token
     *
     * @return array
     */
    private function getShare($token) {
        if (empty($token)) {
            return [NULL, $this->trans->t("FileId is empty")];
        }

        $share = $this->shareManager->getShareByToken($token);
        if ($share === NULL || $share === false) {
            return [NULL, $this->trans->t("You do not have enough permissions to view the file")];
        }

        if ($share->getPassword() 
            && (!$this->session->exists("public_link_authenticated")
                || $this->session->get("public_link_authenticated") !== (string) $share->getId())) {
            return [NULL, $this->trans->t("You do not have enough permissions to view the file")];
        }

        return [$share, NULL];
    }

    /**
     * Generate unique document identifier
     *
     * @param File $file - file
     *
     * @return string
     */
    private function getKey($file) {
        $fileId = $file->getId();

        $key = $fileId . "_" . $file->getMtime();

        return $key;
    }

    /**
     * Generate secure link to download document
     *
     * @param integer $fileId - file identifier
     * @param string $token - access token
     *
     * @return string
     */
    private function getUrl($fileId, $token = NULL) {

        $user = $this->userSession->getUser();
        $userId = NULL;
        if (!empty($user)) {
            $userId = $user->getUID();
        }

        $hashUrl = $this->crypt->GetHash(["fileId" => $fileId, "userId" => $userId, "token" => $token, "action" => "download"]);

        $fileUrl = $this->urlGenerator->linkToRouteAbsolute($this->appName . ".callback.download", ["doc" => $hashUrl]);

        if (!empty($this->config->GetStorageUrl())) {
            $fileUrl = str_replace($this->urlGenerator->getAbsoluteURL("/"), $this->config->GetStorageUrl(), $fileUrl);
        }

        return $fileUrl;
    }

    /**
     * Set customization parameters
     *
     * @param array params - file parameters
     *
     * @return array
     */
    private function setCustomization($params) {
        $customer = $this->config->getSystemValue($this->config->_customization_customer);
        if (isset($customer)) {
            $params["editorConfig"]["customization"]["customer"] = $customer;
        }

        $feedback = $this->config->getSystemValue($this->config->_customization_feedback);
        if (isset($feedback)) {
            $params["editorConfig"]["customization"]["feedback"] = $feedback;
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

        return $params;
    }
}