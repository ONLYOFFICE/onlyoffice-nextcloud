<?php
/**
 *
 * (c) Copyright Ascensio System SIA 2019
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

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\Constants;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager;

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
     * Share manager
     *
     * @var OCP\Share\IManager
     */
    private $shareManager;

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
     * @param IManager $shareManager - Share manager
     */
    public function __construct($AppName, 
                                    IRequest $request,
                                    IRootFolder $root,
                                    IUserSession $userSession,
                                    IUserManager $userManager,
                                    IL10N $trans,
                                    ILogger $logger,
                                    AppConfig $config,
                                    Crypt $crypt,
                                    IManager $shareManager
                                    ) {
        parent::__construct($AppName, $request);

        $this->root = $root;
        $this->userSession = $userSession;
        $this->userManager = $userManager;
        $this->trans = $trans;
        $this->logger = $logger;
        $this->config = $config;
        $this->crypt = $crypt;
        $this->shareManager = $shareManager;
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
            $this->logger->error("Download with empty or not correct hash: $error", array("app" => $this->appName));
            return new JSONResponse(["message" => $this->trans->t("Access denied")], Http::STATUS_FORBIDDEN);
        }
        if ($hashData->action !== "download") {
            $this->logger->error("Download with other action", array("app" => $this->appName));
            return new JSONResponse(["message" => $this->trans->t("Invalid request")], Http::STATUS_BAD_REQUEST);
        }

        $fileId = $hashData->fileId;
        $this->logger->debug("Download: $fileId", array("app" => $this->appName));

        if (!$this->userSession->isLoggedIn()) {
            if (!empty($this->config->GetDocumentServerSecret())) {
                $header = \OC::$server->getRequest()->getHeader($this->config->JwtHeader());
                if (empty($header)) {
                    $this->logger->error("Download without jwt", array("app" => $this->appName));
                    return new JSONResponse(["message" => $this->trans->t("Access denied")], Http::STATUS_FORBIDDEN);
                }

                $header = substr($header, strlen("Bearer "));

                try {
                    $decodedHeader = \Firebase\JWT\JWT::decode($header, $this->config->GetDocumentServerSecret(), array("HS256"));
                } catch (\UnexpectedValueException $e) {
                    $this->logger->error("Download with invalid jwt: " . $e->getMessage(), array("app" => $this->appName));
                    return new JSONResponse(["message" => $this->trans->t("Access denied")], Http::STATUS_FORBIDDEN);
                }
            }
        }

        if ($this->userSession->isLoggedIn()) {
            $userId = $this->userSession->getUser()->getUID();
        } else {
            $userId = $hashData->ownerId;

            if (empty($this->userManager->get($userId))) {
                $userId = $hashData->userId;
            }

            \OC_Util::tearDownFS();
            if (!empty($userId)) {
                \OC_Util::setupFS($userId);
            }
        }

        $shareToken = isset($hashData->shareToken) ? $hashData->shareToken : NULL;
        list ($file, $error) = empty($shareToken) ? $this->getFile($userId, $fileId) : $this->getFileByToken($fileId, $shareToken);

        if (isset($error)) {
            return $error;
        }

        if ($this->userSession->isLoggedIn() && !$file->isReadable()) {
            $this->logger->error("Download without access right", array("app" => $this->appName));
            return new JSONResponse(["message" => $this->trans->t("Access denied")], Http::STATUS_FORBIDDEN);
        }

        try {
            return new DataDownloadResponse($file->getContent(), $file->getName(), $file->getMimeType());
        } catch (NotPermittedException  $e) {
            $this->logger->error("Download Not permitted: $fileId " . $e->getMessage(), array("app" => $this->appName));
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
        $this->logger->debug("Download empty", array("app" => $this->appName));

        list ($hashData, $error) = $this->crypt->ReadHash($doc);
        if ($hashData === NULL) {
            $this->logger->error("Download empty with empty or not correct hash: $error", array("app" => $this->appName));
            return new JSONResponse(["message" => $this->trans->t("Access denied")], Http::STATUS_FORBIDDEN);
        }
        if ($hashData->action !== "empty") {
            $this->logger->error("Download empty with other action", array("app" => $this->appName));
            return new JSONResponse(["message" => $this->trans->t("Invalid request")], Http::STATUS_BAD_REQUEST);
        }

        if (!empty($this->config->GetDocumentServerSecret())) {
            $header = \OC::$server->getRequest()->getHeader($this->config->JwtHeader());
            if (empty($header)) {
                $this->logger->error("Download empty without jwt", array("app" => $this->appName));
                return new JSONResponse(["message" => $this->trans->t("Access denied")], Http::STATUS_FORBIDDEN);
            }

            $header = substr($header, strlen("Bearer "));

            try {
                $decodedHeader = \Firebase\JWT\JWT::decode($header, $this->config->GetDocumentServerSecret(), array("HS256"));
            } catch (\UnexpectedValueException $e) {
                $this->logger->error("Download empty with invalid jwt: " . $e->getMessage(), array("app" => $this->appName));
                return new JSONResponse(["message" => $this->trans->t("Access denied")], Http::STATUS_FORBIDDEN);
            }
        }

        $templatePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . "assets" . DIRECTORY_SEPARATOR . "en" . DIRECTORY_SEPARATOR . "new.docx";

        $template = file_get_contents($templatePath);
        if (!$template) {
            $this->logger->info("Template for download empty not found: $templatePath", array("app" => $this->appName));
            return new JSONResponse(["message" => $this->trans->t("File not found")], Http::STATUS_NOT_FOUND);
        }

        try {
            return new DataDownloadResponse($template, "new.docx", "application/vnd.openxmlformats-officedocument.wordprocessingml.document");
        } catch (NotPermittedException  $e) {
            $this->logger->error("Download Not permitted: $fileId " . $e->getMessage(), array("app" => $this->appName));
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
     * @param integer $status - the edited status
     * @param string $url - the link to the edited document to be saved
     * @param string $token - request signature
     *
     * @return array
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     * @CORS
     */
    public function track($doc, $users, $key, $status, $url, $token) {

        list ($hashData, $error) = $this->crypt->ReadHash($doc);
        if ($hashData === NULL) {
            $this->logger->error("Track with empty or not correct hash: $error", array("app" => $this->appName));
            return new JSONResponse(["message" => $this->trans->t("Access denied")], Http::STATUS_FORBIDDEN);
        }
        if ($hashData->action !== "track") {
            $this->logger->error("Track with other action", array("app" => $this->appName));
            return new JSONResponse(["message" => $this->trans->t("Invalid request")], Http::STATUS_BAD_REQUEST);
        }

        $fileId = $hashData->fileId;
        $this->logger->debug("Track: $fileId status $status", array("app" => $this->appName));

        if (!empty($this->config->GetDocumentServerSecret())) {
            if (!empty($token)) {
                try {
                    $payload = \Firebase\JWT\JWT::decode($token, $this->config->GetDocumentServerSecret(), array("HS256"));
                } catch (\UnexpectedValueException $e) {
                    $this->logger->error("Track with invalid jwt in body: " . $e->getMessage(), array("app" => $this->appName));
                    return new JSONResponse(["message" => $this->trans->t("Access denied")], Http::STATUS_FORBIDDEN);
                }
            } else {
                $header = \OC::$server->getRequest()->getHeader($this->config->JwtHeader());
                if (empty($header)) {
                    $this->logger->error("Track without jwt", array("app" => $this->appName));
                    return new JSONResponse(["message" => $this->trans->t("Access denied")], Http::STATUS_FORBIDDEN);
                }

                $header = substr($header, strlen("Bearer "));

                try {
                    $decodedHeader = \Firebase\JWT\JWT::decode($header, $this->config->GetDocumentServerSecret(), array("HS256"));

                    $payload = $decodedHeader->payload;
                } catch (\UnexpectedValueException $e) {
                    $this->logger->error("Track with invalid jwt: " . $e->getMessage(), array("app" => $this->appName));
                    return new JSONResponse(["message" => $this->trans->t("Access denied")], Http::STATUS_FORBIDDEN);
                }
            }

            $users = isset($payload->users) ? $payload->users : NULL;
            $key = $payload->key;
            $status = $payload->status;
            $url = isset($payload->url) ? $payload->url : NULL;
        }

        $trackerStatus = $this->_trackerStatus[$status];

        $result = 1;
        switch ($trackerStatus) {
            case "MustSave":
            case "Corrupted":
                if (empty($url)) {
                    $this->logger->error("Track without url: $fileId status $trackerStatus", array("app" => $this->appName));
                    return new JSONResponse(["message" => "Url not found"], Http::STATUS_BAD_REQUEST);
                }

                try {
                    $shareToken = isset($hashData->shareToken) ? $hashData->shareToken : NULL;

                    $userId = $users[0];
                    $user = $this->userManager->get($userId);
                    if (!empty($user)) {
                        $this->userSession->setUser($user);
                    } else {
                        if (empty($shareToken)) {
                            $this->logger->error("Track without access: $fileId status $trackerStatus", array("app" => $this->appName));
                            return new JSONResponse(["message" => "User and token is empty"], Http::STATUS_BAD_REQUEST);
                        }

                        $this->logger->debug("Track by anonymous $userId", array("app" => $this->appName));
                    }

                    $ownerId = $hashData->ownerId;
                    if (!empty($this->userManager->get($ownerId))) {
                        $userId = $ownerId;
                    }

                    \OC_Util::tearDownFS();
                    if (!empty($userId)) {
                        // Set the current user as it is used by the files_version storage to obtain the user
                        // if no owner has been found e.g. for global external storages
                        \OC_User::setUserId($ownerId);
                        \OC_Util::setupFS($userId);
                    }

                    list ($file, $error) = empty($shareToken) ? $this->getFile($userId, $fileId) : $this->getFileByToken($fileId, $shareToken);

                    if (isset($error)) {
                        $this->logger->error("track error$fileId" ." " . json_encode($error->getData()),  array("app" => $this->appName));
                        return $error;
                    }

                    $url = $this->config->ReplaceDocumentServerUrlToInternal($url);

                    $fileName = $file->getName();
                    $curExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                    $downloadExt = strtolower(pathinfo($url, PATHINFO_EXTENSION));

                    $documentService = new DocumentService($this->trans, $this->config);
                    if ($downloadExt !== $curExt) {
                        $key =  DocumentService::GenerateRevisionId($fileId . $url);

                        try {
                            $this->logger->debug("Converted from $downloadExt to $curExt", array("app" => $this->appName));
                            $url = $documentService->GetConvertedUri($url, $downloadExt, $curExt, $key);
                        } catch (\Exception $e) {
                            $this->logger->error("Converted on save error: " . $e->getMessage(), array("app" => $this->appName));
                            return new JSONResponse(["message" => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
                        }
                    }

                    $newData = $documentService->Request($url);

                    $this->logger->debug("Track put content " . $file->getPath(), array("app" => $this->appName));
                    $file->putContent($newData);
                    $result = 0;
                } catch (\Exception $e) {
                    $this->logger->error("Track $trackerStatus error: " . $e->getMessage(), array("app" => $this->appName));
                }
                break;

            case "Editing":
            case "Closed":
                $result = 0;
                break;
        }

        $this->logger->debug("Track: $fileId status $status result $result", array("app" => $this->appName));

        return new JSONResponse(["error" => $result], Http::STATUS_OK);
    }


    /**
     * Getting file by identifier
     *
     * @param string $userId - user identifier
     * @param integer $fileId - file identifier
     *
     * @return array
     */
    private function getFile($userId, $fileId) {
        if (empty($fileId)) {
            return [NULL, new JSONResponse(["message" => $this->trans->t("FileId is empty")], Http::STATUS_BAD_REQUEST)];
        }

        try {
            $files = $this->root->getUserFolder($userId)->getById($fileId);
        } catch (\Exception $e) {
            $this->logger->error("getFile: $fileId " . $e->getMessage(), array("app" => $this->appName));
            return [NULL, new JSONResponse(["message" => $this->trans->t("Invalid request")], Http::STATUS_BAD_REQUEST)];
        }

        if (empty($files)) {
            $this->logger->error("Files not found: $fileId", array("app" => $this->appName));
            return [NULL, new JSONResponse(["message" => $this->trans->t("Files not found")], Http::STATUS_NOT_FOUND)];
        }
        $file = $files[0];

        if (!($file instanceof File)) {
            $this->logger->error("File not found: $fileId", array("app" => $this->appName));
            return [NULL, new JSONResponse(["message" => $this->trans->t("File not found")], Http::STATUS_NOT_FOUND)];
        }

        return [$file, NULL];
    }

    /**
     * Getting file by token
     *
     * @param integer $fileId - file identifier
     * @param string $shareToken - access token
     *
     * @return array
     */
    private function getFileByToken($fileId, $shareToken) {
        list ($share, $error) = $this->getShare($shareToken);

        if (isset($error)) {
            return [NULL, $error];
        }

        try {
            $node = $share->getNode();
        } catch (NotFoundException $e) {
            $this->logger->error("getFileByToken error: " . $e->getMessage(), array("app" => $this->appName));
            return [NULL, new JSONResponse(["message" => $this->trans->t("File not found")], Http::STATUS_NOT_FOUND)];
        }

        if ($node instanceof Folder) {
            try {
                $files = $node->getById($fileId);
            } catch (\Exception $e) {
                $this->logger->error("getFileByToken: $fileId " . $e->getMessage(), array("app" => $this->appName));
                return [NULL, new JSONResponse(["message" => $this->trans->t("Invalid request")], Http::STATUS_NOT_FOUND)];
            }

            if (empty($files)) {
                return [NULL, new JSONResponse(["message" => $this->trans->t("File not found")], Http::STATUS_NOT_FOUND)];
            }
            $file = $files[0];
        } else {
            $file = $node;
        }

        return [$file, NULL];
    }

    /**
     * Getting share by token
     *
     * @param string $shareToken - access token
     *
     * @return array
     */
    private function getShare($shareToken) {
        if (empty($shareToken)) {
            return [NULL, new JSONResponse(["message" => $this->trans->t("FileId is empty")], Http::STATUS_BAD_REQUEST)];
        }

        $share;
        try {
            $share = $this->shareManager->getShareByToken($shareToken);
        } catch (ShareNotFound $e) {
            $this->logger->error("getShare error: " . $e->getMessage(), array("app" => $this->appName));
            $share = NULL;
        }

        if ($share === NULL || $share === false) {
            return [NULL, new JSONResponse(["message" => $this->trans->t("You do not have enough permissions to view the file")], Http::STATUS_FORBIDDEN)];
        }

        return [$share, NULL];
    }
}