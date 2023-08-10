<?php
/**
 *
 * (c) Copyright Ascensio System SIA 2023
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
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\StreamResponse;
use OCP\AppFramework\QueryException;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Files\Lock\ILock;
use OCP\Files\Lock\ILockManager;
use OCP\Files\Lock\LockContext;
use OCP\Files\Lock\NoLockProviderException;
use OCP\Files\Lock\OwnerLockedException;
use OCP\PreConditionNotMetException;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Lock\LockedException;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager;

use OCA\Files_Versions\Versions\IVersionManager;

use OCA\Onlyoffice\AppConfig;
use OCA\Onlyoffice\Crypt;
use OCA\Onlyoffice\DocumentService;
use OCA\Onlyoffice\FileVersions;
use OCA\Onlyoffice\KeyManager;
use OCA\Onlyoffice\RemoteInstance;
use OCA\Onlyoffice\TemplateManager;

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
     * Share manager
     *
     * @var IManager
     */
    private $shareManager;

    /**
     * File version manager
     *
     * @var IVersionManager
     */
    private $versionManager;

    /**
     * Lock manager
     *
     * @var ILockManager
    */
    private $lockManager;

    /**
     * Status of the document
     */
    private const TrackerStatus_Editing = 1;
    private const TrackerStatus_MustSave = 2;
    private const TrackerStatus_Corrupted = 3;
    private const TrackerStatus_Closed = 4;
    private const TrackerStatus_ForceSave = 6;
    private const TrackerStatus_CorruptedForceSave = 7;

    /**
     * @param string $AppName - application name
     * @param IRequest $request - request object
     * @param IRootFolder $root - root folder
     * @param IUserSession $userSession - current user session
     * @param IUserManager $userManager - user manager
     * @param IL10N $trans - l10n service
     * @param ILogger $logger - logger
     * @param AppConfig $config - application configuration
     * @param Crypt $crypt - hash generator
     * @param IManager $shareManager - Share manager
     * @param ILockManager $lockManager - Lock manager
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
                                    IManager $shareManager,
                                    ILockManager $lockManager
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
        $this->lockManager = $lockManager;

        if (\OC::$server->getAppManager()->isInstalled("files_versions")) {
            try {
                $this->versionManager = \OC::$server->query(IVersionManager::class);
            } catch (QueryException $e) {
                $this->logger->logException($e, ["message" => "VersionManager init error", "app" => $this->appName]);
            }
        }
    }


    /**
     * Downloading file by the document service
     *
     * @param string $doc - verification token with the file identifier
     *
     * @return StreamResponse|JSONResponse
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     * @CORS
     */
    public function download($doc) {

        list ($hashData, $error) = $this->crypt->ReadHash($doc);
        if ($hashData === null) {
            $this->logger->error("Download with empty or not correct hash: $error", ["app" => $this->appName]);
            return new JSONResponse(["message" => $this->trans->t("Access denied")], Http::STATUS_FORBIDDEN);
        }
        if ($hashData->action !== "download") {
            $this->logger->error("Download with other action", ["app" => $this->appName]);
            return new JSONResponse(["message" => $this->trans->t("Invalid request")], Http::STATUS_BAD_REQUEST);
        }

        $fileId = $hashData->fileId;
        $version = isset($hashData->version) ? $hashData->version : null;
        $changes = isset($hashData->changes) ? $hashData->changes : false;
        $template = isset($hashData->template) ? $hashData->template : false;
        $this->logger->debug("Download: $fileId ($version)" . ($changes ? " changes" : ""), ["app" => $this->appName]);

        if (!empty($this->config->GetDocumentServerSecret())) {
            $header = \OC::$server->getRequest()->getHeader($this->config->JwtHeader());
            if (empty($header)) {
                $this->logger->error("Download without jwt", ["app" => $this->appName]);
                return new JSONResponse(["message" => $this->trans->t("Access denied")], Http::STATUS_FORBIDDEN);
            }

            $header = substr($header, strlen("Bearer "));

            try {
                $decodedHeader = \Firebase\JWT\JWT::decode($header, new \Firebase\JWT\Key($this->config->GetDocumentServerSecret(), "HS256"));
            } catch (\UnexpectedValueException $e) {
                $this->logger->logException($e, ["message" => "Download with invalid jwt", "app" => $this->appName]);
                return new JSONResponse(["message" => $this->trans->t("Access denied")], Http::STATUS_FORBIDDEN);
            }
        }

        $userId = null;
        $user = null;
        \OC_Util::tearDownFS();

        if (isset($hashData->userId)) {
            $userId = $hashData->userId;

            $user = $this->userManager->get($userId);
            if (!empty($user)) {
                \OC_User::setUserId($userId);
                \OC_Util::setupFS($userId);
            }
        }

        $shareToken = isset($hashData->shareToken) ? $hashData->shareToken : null;
        list ($file, $error) = empty($shareToken) ? $this->getFile($userId, $fileId, null, $changes ? null : $version, $template) : $this->getFileByToken($fileId, $shareToken, $changes ? null : $version);

        if (isset($error)) {
            return $error;
        }

        if (!empty($user) && !$file->isReadable()) {
            $this->logger->error("Download without access right", ["app" => $this->appName]);
            return new JSONResponse(["message" => $this->trans->t("Access denied")], Http::STATUS_FORBIDDEN);
        }

        if (empty($user)) {
            $owner = $file->getFileInfo()->getOwner();
            if ($owner !== null) {
                \OC_Util::setupFS($owner->getUID());
            }
        }

        if ($changes) {
            if ($this->versionManager === null) {
                $this->logger->error("Download changes: versionManager is null", ["app" => $this->appName]);
                return new JSONResponse(["message" => $this->trans->t("Invalid request")], Http::STATUS_BAD_REQUEST);
            }

            $owner = $file->getFileInfo()->getOwner();
            if ($owner === null) {
                $this->logger->error("Download: changes owner of $fileId was not found", ["app" => $this->appName]);
                return new JSONResponse(["message" => $this->trans->t("Files not found")], Http::STATUS_NOT_FOUND);
            }

            $versions = FileVersions::processVersionsArray($this->versionManager->getVersionsForFile($owner, $file));

            $versionId = null;
            if ($version > count($versions)) {
                $versionId = $file->getFileInfo()->getMtime();
            } else {
                $fileVersion = array_values($versions)[$version - 1];

                $versionId = $fileVersion->getRevisionId();
            }

            $changesFile = FileVersions::getChangesFile($owner->getUID(), $fileId, $versionId);
            if ($changesFile === null) {
                $this->logger->error("Download: changes $fileId ($version) was not found", ["app" => $this->appName]);
                return new JSONResponse(["message" => $this->trans->t("Files not found")], Http::STATUS_NOT_FOUND);
            }

            $file = $changesFile;
        }

        try {
            $response = new StreamResponse($file->fopen('rb'));
            $response->addHeader('Content-Disposition', 'attachment; filename="' . rawurldecode($file->getName()) . '"');
            $response->addHeader('Content-Type', $file->getMimeType());
            return $response;
        } catch (NotPermittedException  $e) {
            $this->logger->logException($e, ["message" => "Download Not permitted: $fileId ($version)", "app" => $this->appName]);
            return new JSONResponse(["message" => $this->trans->t("Not permitted")], Http::STATUS_FORBIDDEN);
        }
        return new JSONResponse(["message" => $this->trans->t("Download failed")], Http::STATUS_INTERNAL_SERVER_ERROR);
    }

    /**
     * Downloading empty file by the document service
     *
     * @param string $doc - verification token with the file identifier
     *
     * @return DataDownloadResponse|JSONResponse
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     * @CORS
     */
    public function emptyfile($doc) {
        $this->logger->debug("Download empty", ["app" => $this->appName]);

        list ($hashData, $error) = $this->crypt->ReadHash($doc);
        if ($hashData === null) {
            $this->logger->error("Download empty with empty or not correct hash: $error", ["app" => $this->appName]);
            return new JSONResponse(["message" => $this->trans->t("Access denied")], Http::STATUS_FORBIDDEN);
        }
        if ($hashData->action !== "empty") {
            $this->logger->error("Download empty with other action", ["app" => $this->appName]);
            return new JSONResponse(["message" => $this->trans->t("Invalid request")], Http::STATUS_BAD_REQUEST);
        }

        if (!empty($this->config->GetDocumentServerSecret())) {
            $header = \OC::$server->getRequest()->getHeader($this->config->JwtHeader());
            if (empty($header)) {
                $this->logger->error("Download empty without jwt", ["app" => $this->appName]);
                return new JSONResponse(["message" => $this->trans->t("Access denied")], Http::STATUS_FORBIDDEN);
            }

            $header = substr($header, strlen("Bearer "));

            try {
                $decodedHeader = \Firebase\JWT\JWT::decode($header, new \Firebase\JWT\Key($this->config->GetDocumentServerSecret(), "HS256"));
            } catch (\UnexpectedValueException $e) {
                $this->logger->logException($e, ["message" => "Download empty with invalid jwt", "app" => $this->appName]);
                return new JSONResponse(["message" => $this->trans->t("Access denied")], Http::STATUS_FORBIDDEN);
            }
        }

        $templatePath = TemplateManager::GetEmptyTemplatePath("en", ".docx");

        $template = file_get_contents($templatePath);
        if (!$template) {
            $this->logger->info("Template for download empty not found: $templatePath", ["app" => $this->appName]);
            return new JSONResponse(["message" => $this->trans->t("File not found")], Http::STATUS_NOT_FOUND);
        }

        try {
            return new DataDownloadResponse($template, "new.docx", "application/vnd.openxmlformats-officedocument.wordprocessingml.document");
        } catch (NotPermittedException  $e) {
            $this->logger->logException($e, ["message" => "Download Not permitted", "app" => $this->appName]);
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
     * @param array $history - file history
     * @param string $changesurl - link to file changes
     * @param integer $forcesavetype - the type of force save action
     * @param array $actions - the array of action
     * @param string $filetype - extension of the document that is downloaded from the link specified with the url parameter
     *
     * @return array
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     * @CORS
     */
    public function track($doc, $users, $key, $status, $url, $token, $history, $changesurl, $forcesavetype, $actions, $filetype) {

        list ($hashData, $error) = $this->crypt->ReadHash($doc);
        if ($hashData === null) {
            $this->logger->error("Track with empty or not correct hash: $error", ["app" => $this->appName]);
            return new JSONResponse(["message" => $this->trans->t("Access denied")], Http::STATUS_FORBIDDEN);
        }
        if ($hashData->action !== "track") {
            $this->logger->error("Track with other action", ["app" => $this->appName]);
            return new JSONResponse(["message" => $this->trans->t("Invalid request")], Http::STATUS_BAD_REQUEST);
        }

        $fileId = $hashData->fileId;
        $this->logger->debug("Track: $fileId status $status", ["app" => $this->appName]);

        if (!empty($this->config->GetDocumentServerSecret())) {
            if (!empty($token)) {
                try {
                    $payload = \Firebase\JWT\JWT::decode($token, new \Firebase\JWT\Key($this->config->GetDocumentServerSecret(), "HS256"));
                } catch (\UnexpectedValueException $e) {
                    $this->logger->logException($e, ["message" => "Track with invalid jwt in body", "app" => $this->appName]);
                    return new JSONResponse(["message" => $this->trans->t("Access denied")], Http::STATUS_FORBIDDEN);
                }
            } else {
                $header = \OC::$server->getRequest()->getHeader($this->config->JwtHeader());
                if (empty($header)) {
                    $this->logger->error("Track without jwt", ["app" => $this->appName]);
                    return new JSONResponse(["message" => $this->trans->t("Access denied")], Http::STATUS_FORBIDDEN);
                }

                $header = substr($header, strlen("Bearer "));

                try {
                    $decodedHeader = \Firebase\JWT\JWT::decode($header, new \Firebase\JWT\Key($this->config->GetDocumentServerSecret(), "HS256"));

                    $payload = $decodedHeader->payload;
                } catch (\UnexpectedValueException $e) {
                    $this->logger->logException($e, ["message" => "Track with invalid jwt", "app" => $this->appName]);
                    return new JSONResponse(["message" => $this->trans->t("Access denied")], Http::STATUS_FORBIDDEN);
                }
            }

            $users = isset($payload->users) ? $payload->users : null;
            $key = $payload->key;
            $status = $payload->status;
            $url = isset($payload->url) ? $payload->url : null;
        }

        $shareToken = isset($hashData->shareToken) ? $hashData->shareToken : null;
        $filePath = null;

        \OC_Util::tearDownFS();

        $isForcesave = $status === self::TrackerStatus_ForceSave || $status === self::TrackerStatus_CorruptedForceSave;

        $callbackUserId = $hashData->userId;

        $userId = null;
        if (!empty($users)
            && $status !== self::TrackerStatus_Editing) {
            // author of the latest changes
            $userId = $this->parseUserId($users[0]);
        } else {
            $userId = $callbackUserId;
        }

        if ($isForcesave
            && $forcesavetype === 1
            && !empty($actions)) {
            // the user who clicked Save
            $userId = $this->parseUserId($actions[0]["userid"]);
        }

        $user = $this->userManager->get($userId);
        if (!empty($user)) {
            \OC_User::setUserId($userId);
        } else {
            if (empty($shareToken)) {
                $this->logger->error("Track without token: $fileId status $status", ["app" => $this->appName]);
                return new JSONResponse(["message" => $this->trans->t("Access denied")], Http::STATUS_FORBIDDEN);
            }

            $this->logger->debug("Track $fileId by token for $userId", ["app" => $this->appName]);
        }

        // owner of file from the callback link
        $ownerId = $hashData->userId;
        $owner = $this->userManager->get($ownerId);

        if (!empty($owner)) {
            $userId = $ownerId;
        } else {
            $callbackUser = $this->userManager->get($callbackUserId);

            if (!empty($callbackUser)) {
                // author of the callback link
                $userId = $callbackUserId;

                // path for author of the callback link
                $filePath = $hashData->filePath;
            }
        }

        if (!empty($userId)) {
            \OC_Util::setupFS($userId);
        }

        list ($file, $error) = empty($shareToken) ? $this->getFile($userId, $fileId, $filePath) : $this->getFileByToken($fileId, $shareToken);

        if (isset($error)) {
            $this->logger->error("track error $fileId " . json_encode($error->getData()),  ["app" => $this->appName]);
            return $error;
        }

        $result = 1;
        switch ($status) {
            case self::TrackerStatus_MustSave:
            case self::TrackerStatus_Corrupted:
            case self::TrackerStatus_ForceSave:
            case self::TrackerStatus_CorruptedForceSave:
                if (empty($url)) {
                    $this->logger->error("Track without url: $fileId status $status", ["app" => $this->appName]);
                    return new JSONResponse(["message" => "Url not found"], Http::STATUS_BAD_REQUEST);
                }

                try {
                    $url = $this->config->ReplaceDocumentServerUrlToInternal($url);

                    $prevVersion = $file->getFileInfo()->getMtime();
                    $fileName = $file->getName();
                    $curExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                    $downloadExt = $filetype;

                    $documentService = new DocumentService($this->trans, $this->config);
                    if ($downloadExt !== $curExt) {
                        $key =  DocumentService::GenerateRevisionId($fileId . $url);

                        try {
                            $this->logger->debug("Converted from $downloadExt to $curExt", ["app" => $this->appName]);
                            $url = $documentService->GetConvertedUri($url, $downloadExt, $curExt, $key);
                        } catch (\Exception $e) {
                            $this->logger->logException($e, ["message" => "Converted on save error", "app" => $this->appName]);
                            return new JSONResponse(["message" => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
                        }
                    }

                    $newData = $documentService->Request($url);

                    $prevIsForcesave = KeyManager::wasForcesave($fileId);

                    if (RemoteInstance::isRemoteFile($file)) {
                        $isLock = RemoteInstance::lockRemoteKey($file, $isForcesave, null);
                        if ($isForcesave && !$isLock) {
                            break;
                        }
                    } else {
                        KeyManager::lock($fileId, $isForcesave);
                    }

                    $this->logger->debug("Track put content " . $file->getPath(), ["app" => $this->appName]);

                    $retryOperation = function () use ($file, $newData) {
                        $this->retryOperation(function () use ($file, $newData) {
                            return $file->putContent($newData);
                        });
                    };

                    try {
                        $lockContext = new LockContext($file, ILock::TYPE_APP, $this->appName);
                        $this->lockManager->runInScope($lockContext, $retryOperation);
                    } catch (NoLockProviderException $e) {
                        $retryOperation();
                    }

                    if (!$isForcesave) {
                        $this->unlock($file);
                    }

                    if (RemoteInstance::isRemoteFile($file)) {
                        if ($isForcesave) {
                            RemoteInstance::lockRemoteKey($file, false, $isForcesave);
                        }
                    } else {
                        KeyManager::lock($fileId, false);
                        KeyManager::setForcesave($fileId, $isForcesave);
                    }

                    if (!$isForcesave
                        && !$prevIsForcesave
                        && $this->versionManager !== null
                        && $this->config->GetVersionHistory()) {
                        $changes = null;
                        if (!empty($changesurl)) {
                            $changesurl = $this->config->ReplaceDocumentServerUrlToInternal($changesurl);
                            $changes = $documentService->Request($changesurl);
                        }
                        FileVersions::saveHistory($file->getFileInfo(), $history, $changes, $prevVersion);
                    }

                    if (!empty($user) && $this->config->GetVersionHistory()) {
                        FileVersions::saveAuthor($file->getFileInfo(), $user);
                    }

                    $result = 0;
                } catch (\Exception $e) {
                    $this->logger->logException($e, ["message" => "Track: $fileId status $status error", "app" => $this->appName]);
                }
                break;

            case self::TrackerStatus_Editing:
                $this->lock($file);

                $result = 0;
                break;
            case self::TrackerStatus_Closed:
                $this->unlock($file);

                $result = 0;
                break;
        }

        $this->logger->debug("Track: $fileId status $status result $result", ["app" => $this->appName]);

        return new JSONResponse(["error" => $result], Http::STATUS_OK);
    }


    /**
     * Getting file by identifier
     *
     * @param string $userId - user identifier
     * @param integer $fileId - file identifier
     * @param string $filePath - file path
     * @param integer $version - file version
     * @param bool $template - file is template
     *
     * @return array
     */
    private function getFile($userId, $fileId, $filePath = null, $version = 0, $template = false) {
        if (empty($fileId)) {
            return [null, new JSONResponse(["message" => $this->trans->t("FileId is empty")], Http::STATUS_BAD_REQUEST)];
        }

        try {
            $folder = !$template ? $this->root->getUserFolder($userId) : TemplateManager::GetGlobalTemplateDir();
            $files = $folder->getById($fileId);
        } catch (\Exception $e) {
            $this->logger->logException($e, ["message" => "getFile: $fileId", "app" => $this->appName]);
            return [null, new JSONResponse(["message" => $this->trans->t("Invalid request")], Http::STATUS_BAD_REQUEST)];
        }

        if (empty($files)) {
            $this->logger->error("Files not found: $fileId", ["app" => $this->appName]);
            return [null, new JSONResponse(["message" => $this->trans->t("Files not found")], Http::STATUS_NOT_FOUND)];
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

        if (!($file instanceof File)) {
            $this->logger->error("File not found: $fileId", ["app" => $this->appName]);
            return [null, new JSONResponse(["message" => $this->trans->t("File not found")], Http::STATUS_NOT_FOUND)];
        }

        if ($version > 0 && $this->versionManager !== null) {
            $owner = $file->getFileInfo()->getOwner();

            if ($owner !== null) {
                if ($owner->getUID() !== $userId) {
                    list ($file, $error) = $this->getFile($owner->getUID(), $file->getId());

                    if (isset($error)) {
                        return [null, $error];
                    }
                }

                $versions = FileVersions::processVersionsArray($this->versionManager->getVersionsForFile($owner, $file));
                if ($version <= count($versions)) {
                    $fileVersion = array_values($versions)[$version - 1];
                    $file = $this->versionManager->getVersionFile($owner, $file->getFileInfo(), $fileVersion->getRevisionId());
                }
            }
        }

        return [$file, null];
    }

    /**
     * Getting file by token
     *
     * @param integer $fileId - file identifier
     * @param string $shareToken - access token
     * @param integer $version - file version
     *
     * @return array
     */
    private function getFileByToken($fileId, $shareToken, $version = 0) {
        list ($share, $error) = $this->getShare($shareToken);

        if (isset($error)) {
            return [null, $error];
        }

        try {
            $node = $share->getNode();
        } catch (NotFoundException $e) {
            $this->logger->logException($e, ["message" => "getFileByToken error", "app" => $this->appName]);
            return [null, new JSONResponse(["message" => $this->trans->t("File not found")], Http::STATUS_NOT_FOUND)];
        }

        if ($node instanceof Folder) {
            try {
                $files = $node->getById($fileId);
            } catch (\Exception $e) {
                $this->logger->logException($e, ["message" => "getFileByToken: $fileId", "app" => $this->appName]);
                return [null, new JSONResponse(["message" => $this->trans->t("Invalid request")], Http::STATUS_NOT_FOUND)];
            }

            if (empty($files)) {
                return [null, new JSONResponse(["message" => $this->trans->t("File not found")], Http::STATUS_NOT_FOUND)];
            }
            $file = $files[0];
        } else {
            $file = $node;
        }

        if ($version > 0 && $this->versionManager !== null) {
            $owner = $file->getFileInfo()->getOwner();

            if ($owner !== null) {
                $versions = FileVersions::processVersionsArray($this->versionManager->getVersionsForFile($owner, $file));
                if ($version <= count($versions)) {
                    $fileVersion = array_values($versions)[$version - 1];
                    $file = $this->versionManager->getVersionFile($owner, $file->getFileInfo(), $fileVersion->getRevisionId());
                }
            }
        }

        return [$file, null];
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
            return [null, new JSONResponse(["message" => $this->trans->t("FileId is empty")], Http::STATUS_BAD_REQUEST)];
        }

        $share = null;
        try {
            $share = $this->shareManager->getShareByToken($shareToken);
        } catch (ShareNotFound $e) {
            $this->logger->logException($e, ["message" => "getShare error", "app" => $this->appName]);
            $share = null;
        }

        if ($share === null || $share === false) {
            return [null, new JSONResponse(["message" => $this->trans->t("You do not have enough permissions to view the file")], Http::STATUS_FORBIDDEN)];
        }

        return [$share, null];
    }

    /**
     * Parse user identifier for current instance
     *
     * @param string $userId - unique user identifier
     *
     * @return string
     */
    private function parseUserId($userId) {
        $instanceId = $this->config->GetSystemValue("instanceid", true);
        $instanceId = $instanceId . "_";

        if (substr($userId, 0, strlen($instanceId)) === $instanceId) {
            return substr($userId, strlen($instanceId));
        }

        return $userId;
    }

    /**
     * Lock file by lock provider if exists
     *
     * @param File $file - file
     */
    private function lock($file) {
        if (!$this->lockManager->isLockProviderAvailable()) {
            return;
        }

        $fileId = $file->getId();

        try {
            if (empty($this->lockManager->getLocks($fileId))) {
                $this->lockManager->lock(new LockContext($file, ILock::TYPE_APP, $this->appName));

                $this->logger->debug("$this->appName has locked file $fileId", ["app" => $this->appName]);
            }
        } catch (PreConditionNotMetException | OwnerLockedException | NoLockProviderException $e) {}
    }

    /**
     * Unlock file by lock provider if exists
     *
     * @param File $file - file
     */
    private function unlock($file) {
        if (!$this->lockManager->isLockProviderAvailable()) {
            return;
        }

        $fileId = $file->getId();

        try {
            $this->lockManager->unlock(new LockContext($file, ILock::TYPE_APP, $this->appName));

            $this->logger->debug("$this->appName has unlocked file $fileId", ["app" => $this->appName]);
        } catch (PreConditionNotMetException | NoLockProviderException $e) {}
    }

    /**
     * Retry operation if a LockedException occurred
     * Other exceptions will still be thrown
     *
     * @param callable $operation
     *
     * @throws LockedException
     */
    private function retryOperation(callable $operation) {
        $i = 0;
        while (true) {
            try {
                return $operation();
            } catch (LockedException $e) {
                if (++$i === 4) {
                    throw $e;
                }
            }
            usleep(500000);
        }
    }
}
