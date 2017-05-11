<?php
/**
 *
 * (c) Copyright Ascensio System Limited 2010-2017
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

use OCP\App;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Controller;
use OCP\AutoloadNotAllowedException;
use OCP\Files\FileInfo;
use OCP\Files\IRootFolder;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserSession;

use OC\Files\Filesystem;
use OC\Files\View;
use OC\User\NoUserException;

use OCA\Files\Helper;
use OCA\Files_Versions\Storage;

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
     * @param string $AppName - application name
     * @param IRequest $request - request object
     * @param IRootFolder $root - root folder
     * @param IUserSession $userSession - current user session
     * @param IURLGenerator $urlGenerator - url generator service
     * @param IL10N $trans - l10n service
     * @param ILogger $logger - logger
     * @param OCA\Onlyoffice\AppConfig $config - application configuration
     * @param OCA\Onlyoffice\Crypt $crypt - hash generator
     */
    public function __construct($AppName,
                                    IRequest $request,
                                    IRootFolder $root,
                                    IUserSession $userSession,
                                    IURLGenerator $urlGenerator,
                                    IL10N $trans,
                                    ILogger $logger,
                                    AppConfig $config,
                                    Crypt $crypt
                                    ) {
        parent::__construct($AppName, $request);

        $this->userSession = $userSession;
        $this->root = $root;
        $this->urlGenerator = $urlGenerator;
        $this->trans = $trans;
        $this->logger = $logger;
        $this->config = $config;
        $this->crypt = $crypt;
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

        $name = $userFolder->getNonExistingName($name);
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
        list ($file, $error) = $this->getFile($fileId);

        if (isset($error)) {
            $this->logger->error("Convertion: " . $fileId . " " . $error, array("app" => $this->appName));
            return ["error" => $error];
        }

        $fileName = $file->getName();
        $ext = pathinfo($fileName, PATHINFO_EXTENSION);
        $format = $this->config->formats[$ext];
        if (!isset($format)) {
            $this->logger->info("Format for convertion not supported: " . $fileName, array("app" => $this->appName));
            return ["error" => $this->trans->t("Format do not supported")];
        }

        if (!isset($format["conv"]) || $format["conv"] !== TRUE) {
            $this->logger->debug("Conversion not required: " . $fileName, array("app" => $this->appName));
            return ["error" => $this->trans->t("Conversion not required")];
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
        $fileUrl = $this->getUrl($file);
        try {
            $documentService->GetConvertedUri($fileUrl, $ext, $internalExtension, $key, FALSE, $newFileUri);
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

        if (($newData = file_get_contents($newFileUri)) === FALSE) {
            $this->logger->error("Failed download converted file: " . $newFileUri, array("app" => $this->appName));
            return ["error" => $this->trans->t("Failed download converted file")];
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
     * 
     * @return TemplateResponse
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function index($fileId) {
        $documentServerUrl = $this->config->GetDocumentServerUrl();

        if (empty($documentServerUrl)) {
            $this->logger->error("documentServerUrl is empty", array("app" => $this->appName));
            return ["error" => $this->trans->t("ONLYOFFICE app not configured. Please contact admin")];
        }

        $params = [
            "documentServerUrl" => $documentServerUrl,
            "fileId" => $fileId
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
     * Collecting the file parameters for the document service
     *
     * @param integer $fileId - file identifier
     *
     * @return array
     *
     * @NoAdminRequired
     */
    public function config($fileId) {
        list ($file, $error) = $this->getFile($fileId);

        if (isset($error)) {
            $this->logger->error("Convertion: " . $fileId . " " . $error, array("app" => $this->appName));
            return ["error" => $error];
        }

        $fileName = $file->getName();
        $ext = pathinfo($fileName, PATHINFO_EXTENSION);
        $format = $this->config->formats[$ext];
        if (!isset($format)) {
            $this->logger->info("Format do not supported for editing: " . $fileName, array("app" => $this->appName));
            return ["error" => $this->trans->t("Format do not supported")];
        }

        $userId = $this->userSession->getUser()->getUID();
        $ownerId = $file->getOwner()->getUID();
        $folderPath = NULL;
        try {
            $userFolder = $this->root->getUserFolder($ownerId);
            $folderPath = $userFolder->getRelativePath($file->getParent()->getPath());
        } catch (NoUserException $e) {
            $ownerId = $userId;
        }

        $fileId = $file->getId();
        $hashCallback = $this->crypt->GetHash(["fileId" => $fileId, "ownerId" => $ownerId, "action" => "track"]);
        $fileUrl = $this->getUrl($file);
        $key = $this->getKey($file);

        $canEdit = isset($format["edit"]) && $format["edit"];
        $callback = ($file->isUpdateable() && $canEdit ? $this->urlGenerator->linkToRouteAbsolute($this->appName . ".callback.track", ["doc" => $hashCallback]) : NULL);

        if (!empty($this->config->GetStorageUrl())) {
            $callback = str_replace($this->urlGenerator->getAbsoluteURL("/"), $this->config->GetStorageUrl(), $callback);
        }

        $params = [
            "document" => [
                "fileType" => pathinfo($fileName, PATHINFO_EXTENSION),
                "key" => DocumentService::GenerateRevisionId($key),
                "title" => $fileName,
                "url" => $fileUrl,
            ],
            "documentType" => $format["type"],
            "editorConfig" => [
                "callbackUrl" => $callback,
                "lang" => \OC::$server->getL10NFactory("")->get("")->getLanguageCode(),
                "mode" => ($callback === NULL ? "view" : "edit"),
                "user" => [
                    "id" => $userId,
                    "name" => $this->userSession->getUser()->getDisplayName()
                ]
            ]
        ];

        if (!empty($folderPath)) {
            $args = [
                "dir" => $folderPath,
                "scrollto" => $file->getName()
            ];

            $params["editorConfig"]["customization"] = [
                    "goback" => [
                        "url" =>  $this->urlGenerator->linkToRouteAbsolute("files.view.index", $args)
                    ]
                ];
        }

        if (!empty($this->config->GetDocumentServerSecret())) {
            $token = \Firebase\JWT\JWT::encode($params, $this->config->GetDocumentServerSecret());
            $params["token"] = $token;
        }

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
     * Generate unique document identifier
     *
     * @param \OCP\Files\File - file
     *
     * @return string
     */
    private function getKey($file) {
        $fileId = $file->getId();

        $ownerId = $file->getOwner()->getUID();
        try {
            $this->root->getUserFolder($ownerId);
        } catch (NoUserException $e) {
            $ownerId = $this->userSession->getUser()->getUID();
        }

        $key = $fileId . $file->getMtime();

        $ownerView = new View("/" . $ownerId . "/files");
        $filePath = $ownerView->getPath($fileId);
        $versions = [];
        if (App::isEnabled("files_versions")) {
            $versions = Storage::getVersions($ownerId, $filePath);
        }

        $countVersions = count($versions);
        if ($countVersions > 0) {
            $key = $key . $countVersions;
        }
        return $key;
    }

    /**
     * Generate secure link to download document
     *
     * @param \OCP\Files\File - file
     *
     * @return string
     */
    private function getUrl($file) {
        $fileId = $file->getId();

        $ownerId = $file->getOwner()->getUID();
        try {
            $this->root->getUserFolder($ownerId);
        } catch (NoUserException $e) {
            $ownerId = $userId;
        }

        $hashUrl = $this->crypt->GetHash(["fileId" => $fileId, "ownerId" => $ownerId, "action" => "download"]);

        $fileUrl = $this->urlGenerator->linkToRouteAbsolute($this->appName . ".callback.download", ["doc" => $hashUrl]);

        if (!empty($this->config->GetStorageUrl())) {
            $fileUrl = str_replace($this->urlGenerator->getAbsoluteURL("/"), $this->config->GetStorageUrl(), $fileUrl);
        }

        return $fileUrl;
    }
}