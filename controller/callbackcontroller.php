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

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\IUserSession;

use OCA\Onlyoffice\AppConfig;
use OCA\Onlyoffice\Crypt;
use OCA\Onlyoffice\DocumentService;

/**
 * Callback handler for the document server.
 * Download the file without authentication.
 * Save the file without authentication.
 */
class CallbackController extends Controller {

    /**
     * Root folder
     *
     * @var IRootFolder
     */
    private $root;

    /**
     * User session
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
     * l10n service
     *
     * @var IL10N
     */
    private $trans;

    /**
     * Logger
     *
     * @var OCP\ILogger
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
     * Status of the document
     *
     * @var Array
     */
    private $_trackerStatus = array(
        0 => "NotFound",
        1 => "Editing",
        2 => "MustSave",
        3 => "Corrupted",
        4 => "Closed"
    );

    /**
     * @param string $AppName - application name
     * @param IRequest $request - request object
     * @param IRootFolder $root - root folder
     * @param IUserSession $userSession - user session
     * @param IUserManager $userManager - user manager
     * @param IL10N $trans - l10n service
     * @param ILogger $logger - logger
     * @param OCA\Onlyoffice\AppConfig $config - application configuration
     * @param OCA\Onlyoffice\Crypt $crypt - hash generator
     */
    public function __construct($AppName, 
                                    IRequest $request,
                                    IRootFolder $root,
                                    IUserSession $userSession,
                                    IUserManager $userManager,
                                    IL10N $trans,
                                    ILogger $logger,
                                    AppConfig $config,
                                    Crypt $crypt
                                    ) {
        parent::__construct($AppName, $request);

        $this->root = $root;
        $this->userSession = $userSession;
        $this->userManager = $userManager;
        $this->trans = $trans;
        $this->logger = $logger;
        $this->config = $config;
        $this->crypt = $crypt;
    }


    /**
     * Downloading file by the document service
     *
     * @param string $doc - verification token with the file identifier
     *
     * @return DataDownloadResponse
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     * @CORS
     */
    public function download($doc) {

        list ($hashData, $error) = $this->crypt->ReadHash($doc);
        if ($hashData === NULL) {
            $this->logger->info("Download with empty or not correct hash: " . $error, array("app" => $this->appName));
            return new JSONResponse(["message" => $this->trans->t("Access deny")], Http::STATUS_FORBIDDEN);
        }
        if ($hashData->action !== "download") {
            $this->logger->info("Download with other action", array("app" => $this->appName));
            return new JSONResponse(["message" => $this->trans->t("Invalid request")], Http::STATUS_BAD_REQUEST);
        }

        $fileId = $hashData->fileId;
        $ownerId = $hashData->ownerId;

        $files = $this->root->getUserFolder($ownerId)->getById($fileId);
        if (empty($files)) {
            $this->logger->info("Files for download not found: " . $fileId, array("app" => $this->appName));
            return new JSONResponse(["message" => $this->trans->t("Files not found")], Http::STATUS_NOT_FOUND);
        }
        $file = $files[0];

        if (! $file instanceof File) {
            $this->logger->info("File for download not found: " . $fileId, array("app" => $this->appName));
            return new JSONResponse(["message" => $this->trans->t("File not found")], Http::STATUS_NOT_FOUND);
        }

        try {
            return new DataDownloadResponse($file->getContent(), $file->getName(), $file->getMimeType());
        } catch(\OCP\Files\NotPermittedException  $e) {
            $this->logger->info("Download Not permitted: " . $fileId . " " . $e->getMessage(), array("app" => $this->appName));
            return new JSONResponse(["message" => $this->trans->t("Not permitted")], Http::STATUS_FORBIDDEN);
        }
        return new JSONResponse(["message" => $this->trans->t("Download failed")], Http::STATUS_INTERNAL_SERVER_ERROR);
    }

    /**
     * Downloading empty file by the document service
     *
     * @param string $doc - verification token with the file identifier
     *
     * @return OCA\Onlyoffice\DownloadResponse
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     * @CORS
     */
    public function emptyfile($doc) {

        list ($hashData, $error) = $this->crypt->ReadHash($doc);
        if ($hashData === NULL) {
            $this->logger->info("Download empty with empty or not correct hash: " . $error, array("app" => $this->appName));
            return new JSONResponse(["message" => $this->trans->t("Access deny")], Http::STATUS_FORBIDDEN);
        }
        if ($hashData->action !== "empty") {
            $this->logger->info("Download empty with other action", array("app" => $this->appName));
            return new JSONResponse(["message" => $this->trans->t("Invalid request")], Http::STATUS_BAD_REQUEST);
        }

        $templatePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . "assets" . DIRECTORY_SEPARATOR . "en" . DIRECTORY_SEPARATOR . "new.docx";

        $template = file_get_contents($templatePath);
        if (!$template) {
            $this->logger->info("Template for download empty not found: " . $templatePath, array("app" => $this->appName));
            return new JSONResponse(["message" => $this->trans->t("File not found")], Http::STATUS_NOT_FOUND);
        }

        try {
            return new DataDownloadResponse($template, "new.docx", "application/vnd.openxmlformats-officedocument.wordprocessingml.document");
        } catch(\OCP\Files\NotPermittedException  $e) {
            $this->logger->info("Download Not permitted: " . $fileId . " " . $e->getMessage(), array("app" => $this->appName));
            return new JSONResponse(["message" => $this->trans->t("Not permitted")], Http::STATUS_FORBIDDEN);
        }
        return new JSONResponse(["message" => $this->trans->t("Download failed")], Http::STATUS_INTERNAL_SERVER_ERROR);
    }

    /**
     * Handle request from the document server with the document status information
     *
     * @param string $doc - verification token with the file identifier
     * @param array $users - the list of the identifiers of the users
     * @param string $key - the edited document identifier
     * @param string $url - the link to the edited document to be saved
     *
     * @return array
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     * @CORS
     */
    public function track($doc, $users, $key, $status, $url) {

        list ($hashData, $error) = $this->crypt->ReadHash($doc);
        if ($hashData === NULL) {
            $this->logger->info("Track with empty or not correct hash: " . $error, array("app" => $this->appName));
            return new JSONResponse(["message" => $this->trans->t("Access deny")], Http::STATUS_FORBIDDEN);
        }
        if ($hashData->action !== "track") {
            $this->logger->info("Track with other action", array("app" => $this->appName));
            return new JSONResponse(["message" => $this->trans->t("Invalid request")], Http::STATUS_BAD_REQUEST);
        }

        $trackerStatus = $this->_trackerStatus[$status];

        $error = 1;
        switch ($trackerStatus) {
            case "MustSave":
            case "Corrupted":

                $fileId = $hashData->fileId;
                $ownerId = $hashData->ownerId;

                $files = $this->root->getUserFolder($ownerId)->getById($fileId);
                if (empty($files)) {
                    $this->logger->info("Files for track not found: " . $fileId, array("app" => $this->appName));
                    return new JSONResponse(["message" => $this->trans->t("Files not found")], Http::STATUS_NOT_FOUND);
                }
                $file = $files[0];

                if (! $file instanceof File) {
                    $this->logger->info("File for track not found: " . $fileId, array("app" => $this->appName));
                    return new JSONResponse(["message" => $this->trans->t("File not found")], Http::STATUS_NOT_FOUND);
                }

                $fileName = $file->getName();
                $curExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                $downloadExt = strtolower(pathinfo($url, PATHINFO_EXTENSION));

                if ($downloadExt !== $curExt) {
                    $documentService = new DocumentService($this->trans, $this->config);
                    $key =  DocumentService::GenerateRevisionId($fileId . $url);

                    try {
                        $newFileUri;
                        $documentService->GetConvertedUri($url, $downloadExt, $curExt, $key, FALSE, $newFileUri);
                        $url = $newFileUri;
                    } catch (\Exception $e) {
                        $this->logger->error("GetConvertedUri in track: " . $url . " " . $e->getMessage(), array("app" => $this->appName));
                        return new JSONResponse(["message" => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
                    }
                }

                if (!empty($this->config->GetDocumentServerInternalUrl(true))) {
                    $from = $this->config->GetDocumentServerUrl();

                    if (!preg_match("/^https?:\/\//i", $from)) {
                        $parsedUrl = parse_url($url);
                        $from = $parsedUrl["scheme"] . "://" . $parsedUrl["host"] . (array_key_exists("port", $parsedUrl) ? (":" . $parsedUrl["port"]) : "") . "/";
                    }

                    $this->logger->debug("Replace in track from " . $from . " to " . $this->config->GetDocumentServerInternalUrl(true));
                    $url = str_replace($from, $this->config->GetDocumentServerInternalUrl(true), $url);
                }

                if (($newData = file_get_contents($url))) {

                    $this->userSession->setUser($this->userManager->get($users[0]));

                    $file->putContent($newData);
                    $error = 0;
                }
                break;

            case "Editing":
            case "Closed":
                $error = 0;
                break;
        }

        return new JSONResponse(["error" => $error], ($error === 0 ? Http::STATUS_OK : Http::STATUS_BAD_REQUEST));
    }
}