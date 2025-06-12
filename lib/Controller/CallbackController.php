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

use OCA\Files_Versions\Versions\IVersionManager;
use OCA\Onlyoffice\AppConfig;
use OCA\Onlyoffice\Crypt;
use OCA\Onlyoffice\DocumentService;
use OCA\Onlyoffice\FileVersions;
use OCA\Onlyoffice\FileUtility;
use OCA\Onlyoffice\KeyManager;
use OCA\Onlyoffice\RemoteInstance;
use OCA\Onlyoffice\TemplateManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\StreamResponse;
use OCP\AppFramework\Http\Attribute\CORS;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\QueryException;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\Lock\ILock;
use OCP\Files\Lock\ILockManager;
use OCP\Files\Lock\LockContext;
use OCP\Files\Lock\NoLockProviderException;
use OCP\Files\Lock\OwnerLockedException;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Lock\LockedException;
use OCP\PreConditionNotMetException;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager;
use Psr\Log\LoggerInterface;

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
    private const TRACKERSTATUS_EDITING = 1;
    private const TRACKERSTATUS_MUSTSAVE = 2;
    private const TRACKERSTATUS_CORRUPTED = 3;
    private const TRACKERSTATUS_CLOSED = 4;
    private const TRACKERSTATUS_FORCESAVE = 6;
    private const TRACKERSTATUS_CORRUPTEDFORCESAVE = 7;

    /**
     * @param string $AppName - application name
     * @param IRequest $request - request object
     * @param IRootFolder $root - root folder
     * @param IUserSession $userSession - current user session
     * @param IUserManager $userManager - user manager
     * @param IL10N $trans - l10n service
     * @param LoggerInterface $logger - logger
     * @param AppConfig $config - application configuration
     * @param Crypt $crypt - hash generator
     * @param IManager $shareManager - Share manager
     * @param ILockManager $lockManager - Lock manager
     */
    public function __construct(
        $AppName,
        IRequest $request,
        IRootFolder $root,
        IUserSession $userSession,
        IUserManager $userManager,
        IL10N $trans,
        LoggerInterface $logger,
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
                $this->logger->error("VersionManager init error", ["exception" => $e]);
            }
        }
    }


    /**
     * Downloading file by the document service
     *
     * @param string $doc - verification token with the file identifier
     *
     * @return StreamResponse|JSONResponse
     */
    #[CORS]
    #[NoAdminRequired]
    #[NoCSRFRequired]
    #[PublicPage]
    public function download($doc) {

        list($hashData, $error) = $this->crypt->readHash($doc);
        if ($hashData === null) {
            $this->logger->error("Download with empty or not correct hash: $error");
            return new JSONResponse(["message" => $this->trans->t("Access denied")], Http::STATUS_FORBIDDEN);
        }
        if ($hashData->action !== "download") {
            $this->logger->error("Download with other action");
            return new JSONResponse(["message" => $this->trans->t("Invalid request")], Http::STATUS_BAD_REQUEST);
        }

        $fileId = $hashData->fileId;
        $version = isset($hashData->version) ? $hashData->version : null;
        $changes = isset($hashData->changes) ? $hashData->changes : false;
        $template = isset($hashData->template) ? $hashData->template : false;
        $filePath = $hashData->filePath ?? null;
        $this->logger->debug("Download: $fileId ($version)" . ($changes ? " changes" : ""));

        if (!empty($this->config->getDocumentServerSecret())) {
            $header = \OC::$server->getRequest()->getHeader($this->config->jwtHeader());
            if (empty($header)) {
                $this->logger->error("Download without jwt");
                return new JSONResponse(["message" => $this->trans->t("Access denied")], Http::STATUS_FORBIDDEN);
            }

            $header = substr($header, strlen("Bearer "));

            try {
                $decodedHeader = \Firebase\JWT\JWT::decode($header, new \Firebase\JWT\Key($this->config->getDocumentServerSecret(), "HS256"));
            } catch (\UnexpectedValueException $e) {
                $this->logger->error("Download with invalid jwt", ["exception" => $e]);
                return new JSONResponse(["message" => $this->trans->t("Access denied")], Http::STATUS_FORBIDDEN);
            }
        }

        $userId = null;
        $user = null;
        if ($this->userSession->isLoggedIn()) {
            $this->logger->debug("Download: by $userId instead of " . $hashData->userId);
        }

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
        list($file, $error, $share) = empty($shareToken) ? $this->getFile($userId, $fileId, $filePath, $changes ? null : $version, $template) : $this->getFileByToken($fileId, $shareToken, $changes ? null : $version);

        if (isset($error)) {
            return $error;
        }

        $canDownload = true;

        $fileStorage = $file->getStorage();
        if ($fileStorage->instanceOfStorage("\OCA\Files_Sharing\SharedStorage") || !empty($shareToken)) {
            $share = empty($share) ? $fileStorage->getShare() : $share;
            $canDownload = FileUtility::canShareDownload($share);
            if (!$canDownload && !empty($this->config->getDocumentServerSecret())) {
                $canDownload = true;
            }
        }

        if ((!empty($user) && !$file->isReadable()) || !$canDownload) {
            if ($this->userSession->getUser()?->getUID() != $userId) {
                $this->logger->error("Download error: expected $userId instead of " . $this->userSession->getUser()?->getUID());
            }
            $this->logger->error("Download without access right");
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
                $this->logger->error("Download changes: versionManager is null");
                return new JSONResponse(["message" => $this->trans->t("Invalid request")], Http::STATUS_BAD_REQUEST);
            }

            $owner = $file->getFileInfo()->getOwner();
            if ($owner === null) {
                $this->logger->error("Download: changes owner of $fileId was not found");
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

            $changesFile = FileVersions::getChangesFile($owner->getUID(), $file->getFileInfo(), $versionId);
            if ($changesFile === null) {
                $this->logger->error("Download: changes $fileId ($version) was not found");
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
            $this->logger->error("Download Not permitted: $fileId ($version)", ["exception" => $e]);
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
     */
    #[CORS]
    #[NoAdminRequired]
    #[NoCSRFRequired]
    #[PublicPage]
    public function emptyfile($doc) {
        $this->logger->debug("Download empty");

        list($hashData, $error) = $this->crypt->readHash($doc);
        if ($hashData === null) {
            $this->logger->error("Download empty with empty or not correct hash: $error");
            return new JSONResponse(["message" => $this->trans->t("Access denied")], Http::STATUS_FORBIDDEN);
        }
        if ($hashData->action !== "empty") {
            $this->logger->error("Download empty with other action");
            return new JSONResponse(["message" => $this->trans->t("Invalid request")], Http::STATUS_BAD_REQUEST);
        }

        if (!empty($this->config->getDocumentServerSecret())) {
            $header = \OC::$server->getRequest()->getHeader($this->config->jwtHeader());
            if (empty($header)) {
                $this->logger->error("Download empty without jwt");
                return new JSONResponse(["message" => $this->trans->t("Access denied")], Http::STATUS_FORBIDDEN);
            }

            $header = substr($header, strlen("Bearer "));

            try {
                $decodedHeader = \Firebase\JWT\JWT::decode($header, new \Firebase\JWT\Key($this->config->getDocumentServerSecret(), "HS256"));
            } catch (\UnexpectedValueException $e) {
                $this->logger->error("Download empty with invalid jwt", ["exception" => $e]);
                return new JSONResponse(["message" => $this->trans->t("Access denied")], Http::STATUS_FORBIDDEN);
            }
        }

        $templatePath = TemplateManager::getEmptyTemplatePath("default", ".docx");

        $template = file_get_contents($templatePath);
        if (!$template) {
            $this->logger->info("Template for download empty not found: $templatePath");
            return new JSONResponse(["message" => $this->trans->t("File not found")], Http::STATUS_NOT_FOUND);
        }

        try {
            return new DataDownloadResponse($template, "new.docx", "application/vnd.openxmlformats-officedocument.wordprocessingml.document");
        } catch (NotPermittedException  $e) {
            $this->logger->error("Download Not permitted", ["exception" => $e]);
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
     */
    #[CORS]
    #[NoAdminRequired]
    #[NoCSRFRequired]
    #[PublicPage]
    public function track($doc, $users, $key, $status, $url, $token, $history, $changesurl, $forcesavetype, $actions, $filetype) {

        list($hashData, $error) = $this->crypt->readHash($doc);
        if ($hashData === null) {
            $this->logger->error("Track with empty or not correct hash: $error");
            return new JSONResponse(["message" => $this->trans->t("Access denied")], Http::STATUS_FORBIDDEN);
        }
        if ($hashData->action !== "track") {
            $this->logger->error("Track with other action");
            return new JSONResponse(["message" => $this->trans->t("Invalid request")], Http::STATUS_BAD_REQUEST);
        }

        $fileId = $hashData->fileId;
        $this->logger->debug("Track: $fileId status $status");

        if (!empty($this->config->getDocumentServerSecret())) {
            if (!empty($token)) {
                try {
                    $payload = \Firebase\JWT\JWT::decode($token, new \Firebase\JWT\Key($this->config->getDocumentServerSecret(), "HS256"));
                } catch (\UnexpectedValueException $e) {
                    $this->logger->error("Track with invalid jwt in body", ["exception" => $e]);
                    return new JSONResponse(["message" => $this->trans->t("Access denied")], Http::STATUS_FORBIDDEN);
                }
            } else {
                $header = \OC::$server->getRequest()->getHeader($this->config->jwtHeader());
                if (empty($header)) {
                    $this->logger->error("Track without jwt");
                    return new JSONResponse(["message" => $this->trans->t("Access denied")], Http::STATUS_FORBIDDEN);
                }

                $header = substr($header, strlen("Bearer "));

                try {
                    $decodedHeader = \Firebase\JWT\JWT::decode($header, new \Firebase\JWT\Key($this->config->getDocumentServerSecret(), "HS256"));

                    $payload = $decodedHeader->payload;
                } catch (\UnexpectedValueException $e) {
                    $this->logger->error("Track with invalid jwt", ["exception" => $e]);
                    return new JSONResponse(["message" => $this->trans->t("Access denied")], Http::STATUS_FORBIDDEN);
                }
            }

            $users = isset($payload->users) ? $payload->users : null;
            $key = $payload->key;
            $status = $payload->status;
            $url = isset($payload->url) ? $payload->url : null;
        }

        $shareToken = isset($hashData->shareToken) ? $hashData->shareToken : null;
        $filePath = $hashData->filePath;

        \OC_Util::tearDownFS();

        $isForcesave = $status === self::TRACKERSTATUS_FORCESAVE || $status === self::TRACKERSTATUS_CORRUPTEDFORCESAVE;

        $callbackUserId = $hashData->userId;

        $userId = null;
        if (!empty($users)
            && $status !== self::TRACKERSTATUS_EDITING) {
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
                $this->logger->error("Track without token: $fileId status $status");
                return new JSONResponse(["message" => $this->trans->t("Access denied")], Http::STATUS_FORBIDDEN);
            }

            $this->logger->debug("Track $fileId by token for $userId");
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

        if (!empty($userId) && empty($shareToken)) {
            \OC_Util::setupFS($userId);
        }

        list($file, $error, $share) = empty($shareToken) ? $this->getFile($userId, $fileId, $filePath) : $this->getFileByToken($fileId, $shareToken);

        if (isset($error)) {
            $this->logger->error("track error $fileId " . json_encode($error->getData()));
            return $error;
        }

        $result = 1;
        switch ($status) {
            case self::TRACKERSTATUS_MUSTSAVE:
            case self::TRACKERSTATUS_CORRUPTED:
            case self::TRACKERSTATUS_FORCESAVE:
            case self::TRACKERSTATUS_CORRUPTEDFORCESAVE:
                if (empty($url)) {
                    $this->logger->error("Track without url: $fileId status $status");
                    return new JSONResponse(["message" => "Url not found"], Http::STATUS_BAD_REQUEST);
                }

                try {
                    $url = $this->config->replaceDocumentServerUrlToInternal($url);

                    $prevVersion = $file->getFileInfo()->getMtime();
                    $fileName = $file->getName();
                    $curExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                    $downloadExt = $filetype;

                    $documentService = new DocumentService($this->trans, $this->config);
                    if ($downloadExt !== $curExt) {
                        $key = DocumentService::generateRevisionId($fileId . $url);

                        try {
                            $this->logger->debug("Converted from $downloadExt to $curExt");
                            $url = $documentService->getConvertedUri($url, $downloadExt, $curExt, $key);
                        } catch (\Exception $e) {
                            $this->logger->error("Converted on save error", ["exception" => $e]);
                            return new JSONResponse(["message" => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
                        }
                    }

                    $newData = $documentService->request($url);

                    $prevIsForcesave = KeyManager::wasForcesave($fileId);

                    if (RemoteInstance::isRemoteFile($file)) {
                        $isLock = RemoteInstance::lockRemoteKey($file, $isForcesave, null);
                        if ($isForcesave && !$isLock) {
                            break;
                        }
                    } else {
                        KeyManager::lock($fileId, $isForcesave);
                    }

                    $this->logger->debug("Track put content " . $file->getPath());

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
                        && $this->config->getVersionHistory()) {
                        $changes = null;
                        if (!empty($changesurl)) {
                            $changesurl = $this->config->replaceDocumentServerUrlToInternal($changesurl);
                            try {
                                $changes = $documentService->request($changesurl);
                            } catch (\Exception $e) {
                                $this->logger->error("Failed to download changes", ["exception" => $e]);
                            }
                        }
                        FileVersions::saveHistory($file->getFileInfo(), $history, $changes, $prevVersion);
                    }

                    if (!empty($user) && $this->config->getVersionHistory()) {
                        FileVersions::saveAuthor($file->getFileInfo(), $user);
                    }

                    $result = 0;
                } catch (\Exception $e) {
                    $this->logger->error("Track: $fileId status $status error", ["exception" => $e]);
                }
                break;

            case self::TRACKERSTATUS_EDITING:
                $this->lock($file);

                $result = 0;
                break;
            case self::TRACKERSTATUS_CLOSED:
                $this->unlock($file);

                $result = 0;
                break;
        }

        $this->logger->debug("Track: $fileId status $status result $result");

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
            return [null, new JSONResponse(["message" => $this->trans->t("FileId is empty")], Http::STATUS_BAD_REQUEST), null];
        }

        try {
            $folder = !$template ? $this->root->getUserFolder($userId) : TemplateManager::getGlobalTemplateDir();
            $files = $folder->getById($fileId);
        } catch (\Exception $e) {
            $this->logger->error("getFile: $fileId", ["exception" => $e]);
            return [null, new JSONResponse(["message" => $this->trans->t("Invalid request")], Http::STATUS_BAD_REQUEST), null];
        }

        if (empty($files)) {
            $this->logger->error("Files not found: $fileId");
            return [null, new JSONResponse(["message" => $this->trans->t("Files not found")], Http::STATUS_NOT_FOUND), null];
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
            $this->logger->error("File not found: $fileId");
            return [null, new JSONResponse(["message" => $this->trans->t("File not found")], Http::STATUS_NOT_FOUND), null];
        }

        if ($version > 0 && $this->versionManager !== null) {
            $owner = $file->getFileInfo()->getOwner();

            if ($owner !== null) {
                if ($owner->getUID() !== $userId) {
                    list($file, $error, $share) = $this->getFile($owner->getUID(), $file->getId());

                    if (isset($error)) {
                        return [null, $error, null];
                    }
                }

                $versions = FileVersions::processVersionsArray($this->versionManager->getVersionsForFile($owner, $file));
                if ($version <= count($versions)) {
                    $fileVersion = array_values($versions)[$version - 1];
                    $file = $this->versionManager->getVersionFile($owner, $file->getFileInfo(), $fileVersion->getRevisionId());
                }
            }
        }

        return [$file, null, null];
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
        list($share, $error) = $this->getShare($shareToken);

        if (isset($error)) {
            return [null, $error, null];
        }

        try {
            $node = $share->getNode();
        } catch (NotFoundException $e) {
            $this->logger->error("getFileByToken error", ["exception" => $e]);
            return [null, new JSONResponse(["message" => $this->trans->t("File not found")], Http::STATUS_NOT_FOUND), null];
        }

        if ($node instanceof Folder) {
            try {
                $files = $node->getById($fileId);
            } catch (\Exception $e) {
                $this->logger->error("getFileByToken: $fileId", ["exception" => $e]);
                return [null, new JSONResponse(["message" => $this->trans->t("Invalid request")], Http::STATUS_NOT_FOUND), null];
            }

            if (empty($files)) {
                return [null, new JSONResponse(["message" => $this->trans->t("File not found")], Http::STATUS_NOT_FOUND), null];
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

        return [$file, null, $share];
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
            $this->logger->error("getShare error", ["exception" => $e]);
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
        $instanceId = $this->config->getSystemValue("instanceid", true);
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

                $this->logger->debug("$this->appName has locked file $fileId");
            }
        } catch (PreConditionNotMetException | OwnerLockedException | NoLockProviderException $e) {
        }
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

            $this->logger->debug("$this->appName has unlocked file $fileId");
        } catch (PreConditionNotMetException | NoLockProviderException $e) {
        }
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
