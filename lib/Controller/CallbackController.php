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

use OCA\Files_Versions\Versions\IVersionManager;
use OCA\Onlyoffice\AppConfig;
use OCA\Onlyoffice\Crypt;
use OCA\Onlyoffice\DocumentService;
use OCA\Onlyoffice\Events\DocumentUnsavedEvent;
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
use OCP\EventDispatcher\IEventDispatcher;
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
     * Status of the document
     */
    private const TRACKERSTATUS_EDITING = 1;
    private const TRACKERSTATUS_MUSTSAVE = 2;
    private const TRACKERSTATUS_CORRUPTED = 3;
    private const TRACKERSTATUS_CLOSED = 4;
    private const TRACKERSTATUS_FORCESAVE = 6;
    private const TRACKERSTATUS_CORRUPTEDFORCESAVE = 7;

    public function __construct(
        string $appName,
        IRequest $request,
        private readonly IRootFolder $root,
        private readonly IUserSession $userSession,
        private readonly IUserManager $userManager,
        private readonly IL10N $trans,
        private readonly LoggerInterface $logger,
        private readonly AppConfig $appConfig,
        private readonly Crypt $crypt,
        private readonly IManager $shareManager,
        private readonly ILockManager $lockManager,
        private readonly IEventDispatcher $eventDispatcher,
        private readonly ?IVersionManager $versionManager,
        private readonly DocumentService $documentService,
        private readonly KeyManager $keyManager
    ) {
        parent::__construct($appName, $request);
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
    public function download(string $doc): StreamResponse|JSONResponse {

        [$hashData, $error] = $this->crypt->readHash($doc);
        if ($hashData === null) {
            $this->logger->error("Download with empty or not correct hash: $error");
            return new JSONResponse(["message" => $this->trans->t("Access denied")], Http::STATUS_FORBIDDEN);
        }
        if ($hashData->action !== "download") {
            $this->logger->error("Download with other action");
            return new JSONResponse(["message" => $this->trans->t("Invalid request")], Http::STATUS_BAD_REQUEST);
        }

        $fileId = $hashData->fileId;
        $version = $hashData->version ?? 0;
        $changes = $hashData->changes ?? false;
        $template = $hashData->template ?? false;
        $filePath = $hashData->filePath ?? "";
        $this->logger->debug("Download: $fileId ($version)" . ($changes ? " changes" : ""));

        if (!empty($this->appConfig->getDocumentServerSecret())) {
            $header = $this->request->getHeader($this->appConfig->jwtHeader());
            if (empty($header)) {
                $this->logger->error("Download without jwt");
                return new JSONResponse(["message" => $this->trans->t("Access denied")], Http::STATUS_FORBIDDEN);
            }

            $header = substr((string) $header, strlen("Bearer "));

            try {
                $decodedHeader = \Firebase\JWT\JWT::decode($header, new \Firebase\JWT\Key($this->appConfig->getDocumentServerSecret(), "HS256"));
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

        $shareToken = $hashData->shareToken ?? null;
        [$file, $error, $share] = empty($shareToken) ? $this->getFile($userId, $fileId, $filePath, $changes ? null : $version, $template) : $this->getFileByToken($fileId, $shareToken, $changes ? null : $version);

        if (isset($error)) {
            return $error;
        }

        $canDownload = true;

        $fileStorage = $file->getStorage();
        if ($fileStorage->instanceOfStorage(\OCA\Files_Sharing\SharedStorage::class) || !empty($shareToken)) {
            $share = empty($share) ? $fileStorage->getShare() : $share;
            $canDownload = FileUtility::canShareDownload($share);
            if (!$canDownload && !empty($this->appConfig->getDocumentServerSecret())) {
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
            if (!$this->versionManager instanceof IVersionManager) {
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
            if (!$changesFile instanceof \OC\Files\Node\File) {
                $this->logger->error("Download: changes $fileId ($version) was not found");
                return new JSONResponse(["message" => $this->trans->t("Files not found")], Http::STATUS_NOT_FOUND);
            }

            $file = $changesFile;
        }

        try {
            $handle = $file->fopen('rb');
            if ($handle !== false && $handle !== null) {
                $response = new StreamResponse($handle);
                $response->addHeader('Content-Disposition', 'attachment; filename="' . rawurldecode((string) $file->getName()) . '"');
                $response->addHeader('Content-Type', $file->getMimeType());
                return $response;
            }
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
    public function emptyfile(string $doc): DataDownloadResponse|JSONResponse {
        $this->logger->debug("Download empty");

        [$hashData, $error] = $this->crypt->readHash($doc);
        if ($hashData === null) {
            $this->logger->error("Download empty with empty or not correct hash: $error");
            return new JSONResponse(["message" => $this->trans->t("Access denied")], Http::STATUS_FORBIDDEN);
        }
        if ($hashData->action !== "empty") {
            $this->logger->error("Download empty with other action");
            return new JSONResponse(["message" => $this->trans->t("Invalid request")], Http::STATUS_BAD_REQUEST);
        }

        if (!empty($this->appConfig->getDocumentServerSecret())) {
            $header = $this->request->getHeader($this->appConfig->jwtHeader());
            if (empty($header)) {
                $this->logger->error("Download empty without jwt");
                return new JSONResponse(["message" => $this->trans->t("Access denied")], Http::STATUS_FORBIDDEN);
            }

            $header = substr((string) $header, strlen("Bearer "));

            try {
                $decodedHeader = \Firebase\JWT\JWT::decode($header, new \Firebase\JWT\Key($this->appConfig->getDocumentServerSecret(), "HS256"));
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
     * @param string $doc verification token with the file identifier
     * @param string $key the edited document identifier
     * @param int $status the edited status
     * @param array $actions the array of action
     * @param array $users the list of the identifiers of the users
     * @param string $changesurl link to file changes
     * @param string $filetype extension of the document that is downloaded from the link specified with the url parameter
     * @param mixed $forcesavetype the type of force save action
     * @param array $history file history
     * @param string $url the link to the edited document to be saved
     * @param string $token request signature
     *
     * @return JSONResponse
     */
    #[CORS]
    #[NoAdminRequired]
    #[NoCSRFRequired]
    #[PublicPage]
    public function track(
        string $doc,
        string $key,
        int $status,
        array $actions = [],
        array $users = [],
        string $changesurl = "",
        string $filetype = "",
        ?int $forcesavetype = null,
        array $history = [],
        string $url = "",
        string $token = ""
    ): JSONResponse {

        [$hashData, $error] = $this->crypt->readHash($doc);
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

        if (!empty($this->appConfig->getDocumentServerSecret())) {
            if (!empty($token)) {
                try {
                    $payload = \Firebase\JWT\JWT::decode($token, new \Firebase\JWT\Key($this->appConfig->getDocumentServerSecret(), "HS256"));
                } catch (\UnexpectedValueException $e) {
                    $this->logger->error("Track with invalid jwt in body", ["exception" => $e]);
                    return new JSONResponse(["message" => $this->trans->t("Access denied")], Http::STATUS_FORBIDDEN);
                }
            } else {
                $header = $this->request->getHeader($this->appConfig->jwtHeader());
                if (empty($header)) {
                    $this->logger->error("Track without jwt");
                    return new JSONResponse(["message" => $this->trans->t("Access denied")], Http::STATUS_FORBIDDEN);
                }

                $header = substr((string) $header, strlen("Bearer "));

                try {
                    $decodedHeader = \Firebase\JWT\JWT::decode($header, new \Firebase\JWT\Key($this->appConfig->getDocumentServerSecret(), "HS256"));

                    $payload = $decodedHeader->payload;
                } catch (\UnexpectedValueException $e) {
                    $this->logger->error("Track with invalid jwt", ["exception" => $e]);
                    return new JSONResponse(["message" => $this->trans->t("Access denied")], Http::STATUS_FORBIDDEN);
                }
            }

            $users = $payload->users ?? null;
            $key = $payload->key;
            $status = $payload->status;
            $url = $payload->url ?? null;
        }

        $shareToken = $hashData->shareToken ?? null;
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

        [$file, $error, $share] = empty($shareToken) ? $this->getFile($userId, $fileId, $filePath) : $this->getFileByToken($fileId, $shareToken);

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
                    $url = $this->appConfig->replaceDocumentServerUrlToInternal($url);

                    $prevVersion = $file->getFileInfo()->getMtime();
                    $fileName = $file->getName();
                    $curExt = strtolower(pathinfo((string) $fileName, PATHINFO_EXTENSION));
                    $downloadExt = $filetype;

                    if ($downloadExt !== $curExt) {
                        $key = DocumentService::generateRevisionId($fileId . $url);

                        try {
                            $this->logger->debug("Converted from $downloadExt to $curExt");
                            $url = $this->documentService->getConvertedUri($url, $downloadExt, $curExt, $key);
                        } catch (\Exception $e) {
                            $this->logger->error("Converted on save error", ["exception" => $e]);
                            return new JSONResponse(["message" => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
                        }
                    }

                    $newData = $this->documentService->request($url);

                    $prevIsForcesave = $this->keyManager->wasForcesave($fileId);

                    if (RemoteInstance::isRemoteFile($file)) {
                        $isLock = RemoteInstance::lockRemoteKey($file, $isForcesave, null);
                        if ($isForcesave && !$isLock) {
                            break;
                        }
                    } else {
                        $this->keyManager->lock($fileId, $isForcesave);
                    }

                    $this->logger->debug("Track put content " . $file->getPath());

                    $retryOperation = function () use ($file, $newData): void {
                        $this->retryOperation(fn() => $file->putContent($newData));
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
                        $this->keyManager->lock($fileId, false);
                        $this->keyManager->setForcesave($fileId, $isForcesave);
                    }

                    if (!$isForcesave
                        && !$prevIsForcesave
                        && $this->versionManager instanceof IVersionManager
                        && $this->appConfig->getVersionHistory()) {
                        $changes = null;
                        if (!empty($changesurl)) {
                            $changesurl = $this->appConfig->replaceDocumentServerUrlToInternal($changesurl);
                            try {
                                $changes = $this->documentService->request($changesurl);
                            } catch (\Exception $e) {
                                $this->logger->error("Failed to download changes", ["exception" => $e]);
                            }
                        }
                        FileVersions::saveHistory($file->getFileInfo(), $history, $changes, $prevVersion);
                    }

                    if (!empty($user) && $this->appConfig->getVersionHistory()) {
                        FileVersions::saveAuthor($file->getFileInfo(), $user);
                    }

                    $result = 0;
                } catch (\Exception $e) {
                    $this->logger->error("Track: $fileId status $status error", ["exception" => $e]);
                    // if ($status === self::TRACKERSTATUS_MUSTSAVE) {
                    //     $this->eventDispatcher->dispatchTyped(new DocumentUnsavedEvent($userId, $fileId, $file->getName()));
                    // }
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
     */
    private function getFile(
        ?string $userId,
        ?int $fileId,
        string $filePath = "",
        int $version = 0,
        bool $template = false
    ): array {
        if (empty($fileId)) {
            return [null, new JSONResponse(["message" => $this->trans->t("FileId is empty")], Http::STATUS_BAD_REQUEST), null];
        }

        try {
            $folder = $template ? TemplateManager::getGlobalTemplateDir() : $this->root->getUserFolder($userId);
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

        if ($version > 0 && $this->versionManager instanceof IVersionManager) {
            $owner = $file->getOwner();

            if ($owner !== null) {
                if ($owner->getUID() !== $userId) {
                    [$file, $error, $share] = $this->getFile($owner->getUID(), $file->getId());

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
     */
    private function getFileByToken(int $fileId, string $shareToken, int $version = 0): array {
        [$share, $error] = $this->getShare($shareToken);

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
                $this->logger->error("getFileByToken Files not found: $fileId");
                return [null, new JSONResponse(["message" => $this->trans->t("File not found")], Http::STATUS_NOT_FOUND), null];
            }
            $file = $files[0];
        } else {
            $file = $node;
        }

        if ($version > 0 && $this->versionManager instanceof IVersionManager) {
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
     */
    private function getShare(?string $shareToken): array {
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
    private function parseUserId(string $userId) {
        $instanceId = $this->appConfig->getSystemValue("instanceid", true);
        $instanceId .= "_";

        if (str_starts_with($userId, $instanceId)) {
            return substr($userId, strlen($instanceId));
        }

        return $userId;
    }

    /**
     * Lock file by lock provider if exists
     *
     * @param File $file - file
     */
    private function lock(File $file): void {
        if (!$this->lockManager->isLockProviderAvailable()) {
            return;
        }

        $fileId = $file->getId();

        try {
            if (empty($this->lockManager->getLocks($fileId))) {
                $this->lockManager->lock(new LockContext($file, ILock::TYPE_APP, $this->appName));

                $this->logger->debug("$this->appName has locked file $fileId");
            }
        } catch (PreConditionNotMetException | OwnerLockedException | NoLockProviderException) {
        }
    }

    /**
     * Unlock file by lock provider if exists
     *
     * @param File $file - file
     */
    private function unlock(File $file): void {
        if (!$this->lockManager->isLockProviderAvailable()) {
            return;
        }

        $fileId = $file->getId();

        try {
            $this->lockManager->unlock(new LockContext($file, ILock::TYPE_APP, $this->appName));

            $this->logger->debug("$this->appName has unlocked file $fileId");
        } catch (PreConditionNotMetException | NoLockProviderException) {
        }
    }

    /**
     * Retry operation if a LockedException occurred
     * Other exceptions will still be thrown
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
