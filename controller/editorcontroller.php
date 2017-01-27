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

use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Controller;
use OCP\AutoloadNotAllowedException;
use OCP\Files\FileInfo;
use OCP\Files\IRootFolder;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUser;

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
     * Current user
     *
     * @var IUser
     */
    private $user;

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
     * @param string $AppName application name
     * @param IRequest $request request object
     * @param IRootFolder $root root folder
     * @param IUser $user current user
     * @param IURLGenerator $urlGenerator url generator service
     * @param IL10N $l10n l10n service
     * @param OCA\Onlyoffice\AppConfig $config application configuration
     * @param OCA\Onlyoffice\Crypt $crypt hash generator
     */
    public function __construct($AppName,
                                    IRequest $request,
                                    IRootFolder $root,
                                    IUser $user,
                                    IURLGenerator $urlGenerator,
                                    IL10N $trans,
                                    AppConfig $config,
                                    Crypt $crypt
                                    ) {
        parent::__construct($AppName, $request);

        $this->user = $user;
        $this->root = $root;
        $this->urlGenerator = $urlGenerator;
        $this->trans = $trans;
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

        $userId = $this->user->getUID();
        $userFolder = $this->root->getUserFolder($userId);
        $folder = $userFolder->get($dir);

        if ($folder === NULL) {
            return ["error" => $this->trans->t("The required folder was not found")];
        }
        if (!$folder->isCreatable()) {
            return ["error" => $this->trans->t("You don't have enough permission to create")];
        }

        $name = $userFolder->getNonExistingName($name);
        $filePath = $dir . DIRECTORY_SEPARATOR . $name;
        $ext = strtolower("." . pathinfo($filePath, PATHINFO_EXTENSION));
        $templatePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . "assets" . DIRECTORY_SEPARATOR . "new" . $ext;

        $template = file_get_contents($templatePath);
        if (!$template) {
            return ["error" => $this->trans->t("Template not found")];
        }

        $view = Filesystem::getView();
        if (!$view->file_put_contents($filePath, $template)) {
            return ["error" => $this->trans->t("Can't create file")];
        }

        $fileInfo = $view->getFileInfo($filePath);

        if ($fileInfo === false) {
            return ["error" => $this->trans->t("File not found")];
        }

        $result = Helper::formatFileInfo($fileInfo);
        return $result;
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
            return ["error" => $error];
        }

        $fileName = $file->getName();
        $ext = pathinfo($fileName, PATHINFO_EXTENSION);
        $format = $this->config->formats[$ext];
        if (!isset($format)) {
            return ["error" => $this->trans->t("Format do not supported")];
        }

        if(!isset($format["conv"]) || $format["conv"] !== TRUE) {
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
            return ["error" => $e->getMessage()];
        }

        $userId = $this->user->getUID();
        $folder = $file->getParent();
        if (!$folder->isCreatable()) {
            $folder = $this->root->getUserFolder($userId);
        }
        $pattern = "/^\\" . DIRECTORY_SEPARATOR . $userId . "\\" . DIRECTORY_SEPARATOR . "files/";
        $newFolderPath = preg_replace($pattern, "", $folder->getPath());

        $fileNameWithoutExt = substr($fileName, 0, strlen($fileName) - strlen($ext) - 1);
        $newFileName = $folder->getNonExistingName($fileNameWithoutExt . "." . $internalExtension);

        $newFilePath = $newFolderPath . DIRECTORY_SEPARATOR . $newFileName;
        
        if (($newData = file_get_contents($newFileUri)) === FALSE){
            return ["error" => $this->trans->t("Failed download converted file")];
        }

        $view = Filesystem::getView();
        if (!$view->file_put_contents($newFilePath, $newData)) {
            return ["error" => $this->trans->t("Can't create file")];
        }

        $fileInfo = $view->getFileInfo($newFilePath);

        if ($fileInfo === false) {
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
        $params = $this->getParam($fileId);

        $response = new TemplateResponse($this->appName, "editor", $params);

        $csp = new ContentSecurityPolicy();
        $csp->allowInlineScript(true);

        $documentServerUrl = $params["documentServerUrl"];
        if (isset($documentServerUrl) && !empty($documentServerUrl)) {
            $csp->addAllowedScriptDomain($documentServerUrl);
            $csp->addAllowedFrameDomain($documentServerUrl);
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
     */
    private function getParam($fileId) {
        list ($file, $error) = $this->getFile($fileId);

        if (isset($error)) {
            return ["error" => $error];
        }

        $fileName = $file->getName();
        $ext = pathinfo($fileName, PATHINFO_EXTENSION);
        $format = $this->config->formats[$ext];
        if (!isset($format)) {
            return ["error" => $this->trans->t("Format do not supported")];
        }

        $documentServerUrl = $this->config->GetDocumentServerUrl();

        if (empty($documentServerUrl)) {
            return ["error" => $this->trans->t("ONLYOFFICE app not configured. Please contact admin")];
        }

        $userId = $this->user->getUID();
        $ownerId = $file->getOwner()->getUID();
        try {
            $this->root->getUserFolder($ownerId);
        } catch (NoUserException $e) {
            $ownerId = $userId;
        }

        $fileId = $file->getId();
        $hashCallback = $this->crypt->GetHash(["fileId" => $fileId, "ownerId" => $ownerId, "action" => "track"]);
        $fileUrl = $this->getUrl($file);
        $key = $this->getKey($file);

        $canEdit = isset($format["edit"]) && $format["edit"];

        $params = [
            "documentServerUrl" => $documentServerUrl,

            "callback" => ($file->isUpdateable() && $canEdit ? $this->urlGenerator->linkToRouteAbsolute($this->appName . ".callback.track", ["doc" => $hashCallback]) : NULL),
            "fileName" => $fileName,
            "key" => DocumentService::GenerateRevisionId($key),
            "url" => $fileUrl,
            "userId" => $userId,
            "userName" => $this->user->getDisplayName(),
            "documentType" => $format["type"]
        ];

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
        if(empty($files)) {
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
            $ownerId = $this->user->getUID();
        }

        $key = $fileId . $file->getMtime();

        $ownerView = new View('/'.$ownerId.'/files');
        $filePath = $ownerView->getPath($fileId);
        $versions = "";
        try {
            $versions = Storage::getVersions($ownerId, $filePath);
        } catch (AutoloadNotAllowedException $e) {
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
        return $fileUrl;
    }
}