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

namespace OCA\Onlyoffice;

use OC\Files\View;
use OC\Preview\Provider;

use OCP\AppFramework\QueryException;
use OCP\Files\FileInfo;
use OCP\Files\IRootFolder;
use OCP\IL10N;
use OCP\ILogger;
use OCP\Image;
use OCP\ISession;
use OCP\IURLGenerator;
use OCP\Share\IManager;

use OCA\Files_Sharing\External\Storage as SharingExternalStorage;
use OCA\Files_Versions\Versions\IVersionManager;

use OCA\Onlyoffice\AppConfig;
use OCA\Onlyoffice\Crypt;
use OCA\Onlyoffice\DocumentService;
use OCA\Onlyoffice\FileUtility;
use OCA\Onlyoffice\FileVersions;
use OCA\Onlyoffice\TemplateManager;

/**
 * Preview provider
 *
 * @package OCA\Onlyoffice
 */
class Preview extends Provider {

    /**
     * Application name
     *
     * @var string
     */
    private $appName;

    /**
     * Root folder
     *
     * @var IRootFolder
     */
    private $root;

    /**
     * Logger
     *
     * @var ILogger
     */
    private $logger;

    /**
     * l10n service
     *
     * @var IL10N
     */
    private $trans;

    /**
     * Application configuration
     *
     * @var AppConfig
     */
    private $config;

    /**
     * Url generator service
     *
     * @var IURLGenerator
     */
    private $urlGenerator;

    /**
     * Hash generator
     *
     * @var Crypt
     */
    private $crypt;

    /**
     * File version manager
     *
     * @var IVersionManager
    */
    private $versionManager;

    /**
     * File utility
     *
     * @var FileUtility
     */
    private $fileUtility;

    /**
     * Capabilities mimetype
     *
     * @var Array
     */
    public static $capabilities = [
        "text/csv",
        "application/msword",
        "application/vnd.ms-word.document.macroEnabled.12",
        "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
        "application/vnd.openxmlformats-officedocument.wordprocessingml.template",
        "application/epub+zip",
        "text/html",
        "application/vnd.oasis.opendocument.presentation",
        "application/vnd.oasis.opendocument.spreadsheet",
        "application/vnd.oasis.opendocument.text",
        "application/vnd.oasis.opendocument.presentation-template",
        "application/vnd.oasis.opendocument.spreadsheet-template",
        "application/vnd.oasis.opendocument.text-template",
        "application/pdf",
        "application/vnd.ms-powerpoint.template.macroEnabled.12",
        "application/vnd.openxmlformats-officedocument.presentationml.template",
        "application/vnd.ms-powerpoint.slideshow.macroEnabled.12",
        "application/vnd.openxmlformats-officedocument.presentationml.slideshow",
        "application/vnd.ms-powerpoint",
        "application/vnd.ms-powerpoint.presentation.macroEnabled.12",
        "application/vnd.openxmlformats-officedocument.presentationml.presentation",
        "text/rtf",
        "text/plain",
        "application/vnd.ms-excel",
        "application/vnd.ms-excel.sheet.macroEnabled.12",
        "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
        "application/vnd.ms-excel.template.macroEnabled.12",
        "application/vnd.openxmlformats-officedocument.spreadsheetml.template"
    ];

    /**
     * Converted thumbnail format
     */
    private const thumbExtension = "jpeg";

    /**
     * @param string $appName - application name
     * @param IRootFolder $root - root folder
     * @param ILogger $logger - logger
     * @param IL10N $trans - l10n service
     * @param AppConfig $config - application configuration
     * @param IURLGenerator $urlGenerator - url generator service
     * @param Crypt $crypt - hash generator
     * @param IManager $shareManager - share manager
     * @param ISession $session - session
     */
    public function __construct(string $appName,
                                    IRootFolder $root,
                                    ILogger $logger,
                                    IL10N $trans,
                                    AppConfig $config,
                                    IURLGenerator $urlGenerator,
                                    Crypt $crypt,
                                    IManager $shareManager,
                                    ISession $session
                                    ) {
        $this->appName = $appName;
        $this->root = $root;
        $this->logger = $logger;
        $this->trans = $trans;
        $this->config = $config;
        $this->urlGenerator = $urlGenerator;
        $this->crypt = $crypt;

        if (\OC::$server->getAppManager()->isInstalled("files_versions")) {
            try {
                $this->versionManager = \OC::$server->query(IVersionManager::class);
            } catch (QueryException $e) {
                $this->logger->logException($e, ["message" => "VersionManager init error", "app" => $this->appName]);
            }
        }

        $this->fileUtility = new FileUtility($appName, $trans, $logger, $config, $shareManager, $session);
    }

    /**
     * Return mime type
     */
    public static function getMimeTypeRegex() {
        $mimeTypeRegex = "";
        foreach (self::$capabilities as $format) {
            if (!empty($mimeTypeRegex)) {
                $mimeTypeRegex = $mimeTypeRegex . "|";
            }
            $mimeTypeRegex = $mimeTypeRegex . str_replace("/", "\/", $format);
        }
        $mimeTypeRegex = "/" . $mimeTypeRegex . "/";

        return $mimeTypeRegex;
    }

    /**
     * Return mime type
     */
    public function getMimeType() {
        $m = self::getMimeTypeRegex();
        return $m;
    }

    /**
     * The method checks if the file can be converted
     *
     * @param FileInfo $fileInfo - File
     *
     * @return bool
     */
    public function isAvailable(FileInfo $fileInfo) {
        if ($this->config->GetPreview() !== true) {
            return false;
        }
        if (!$fileInfo 
            || $fileInfo->getSize() === 0
            || $fileInfo->getSize() > $this->config->GetLimitThumbSize()) {
            return false;
        }
        if (!in_array($fileInfo->getMimetype(), self::$capabilities, true)) {
            return false;
        }
        if ($fileInfo->getStorage()->instanceOfStorage(SharingExternalStorage::class)) {
            return false;
        }
        return true;
    }

    /**
     * The method is generated thumbnail for file and returned image object
     *
     * @param string $path - Path of file
     * @param int $maxX - The maximum X size of the thumbnail
     * @param int $maxY - The maximum Y size of the thumbnail
     * @param bool $scalingup - Disable/Enable upscaling of previews
     * @param View $view - view
     *
     * @return Image|bool false if no preview was generated
     */
    public function getThumbnail($path, $maxX, $maxY, $scalingup, $view) {
        $this->logger->debug("getThumbnail $path $maxX $maxY", ["app" => $this->appName]);

        list ($fileUrl, $extension, $key) = $this->getFileParam($path, $view);
        if ($fileUrl === null || $extension === null || $key === null) {
            return false;
        }

        $imageUrl = null;
        $documentService = new DocumentService($this->trans, $this->config);
        try {
            $imageUrl = $documentService->GetConvertedUri($fileUrl, $extension, self::thumbExtension, $key);
        } catch (\Exception $e) {
            $this->logger->logException($e, ["message" => "GetConvertedUri: from $extension to " . self::thumbExtension, "app" => $this->appName]);
            return false;
        }

        try {
            $thumbnail = $documentService->Request($imageUrl);
        } catch (\Exception $e) {
            $this->logger->logException($e, ["message" => "Failed to download thumbnail", "app" => $this->appName]);
            return false;
        }

        $image = new Image();
        $image->loadFromData($thumbnail);

        if ($image->valid()) {
            $image->scaleDownToFit($maxX, $maxY);
            return $image;
        }

        return false;
    }

    /**
     * Generate secure link to download document
     *
     * @param File $file - file
     * @param IUser $user - user with access
     * @param int $version - file version
     * @param bool $template - file is template
     *
     * @return string
     */
    private function getUrl($file, $user = null, $version = 0, $template = false) {

        $data = [
            "action" => "download",
            "fileId" => $file->getId()
        ];

        $userId = null;
        if (!empty($user)) {
            $userId = $user->getUID();
            $data["userId"] = $userId;
        }
        if ($version > 0) {
            $data["version"] = $version;
        }
        if ($template) {
            $data["template"] = true;
        }

        $hashUrl = $this->crypt->GetHash($data);

        $fileUrl = $this->urlGenerator->linkToRouteAbsolute($this->appName . ".callback.download", ["doc" => $hashUrl]);

        if (!empty($this->config->GetStorageUrl())) {
            $fileUrl = str_replace($this->urlGenerator->getAbsoluteURL("/"), $this->config->GetStorageUrl(), $fileUrl);
        }

        return $fileUrl;
    }

    /**
     * Generate array with file parameters
     *
     * @param string $path - Path of file
     * @param View $view - view
     *
     * @return array
     */
    private function getFileParam($path, $view) {
        $fileInfo = $view->getFileInfo($path);

        if (!$fileInfo || $fileInfo->getSize() === 0) {
            return [null, null, null];
        }

        $owner = $fileInfo->getOwner();

        $key = null;
        $versionNum = 0;
        $template = false;
        if (FileVersions::splitPathVersion($path) !== false) {
            if ($this->versionManager === null || $owner === null) {
                return [null, null, null];
            }

            $versionFolder = new View("/" . $owner->getUID() . "/files_versions");
            $absolutePath = $fileInfo->getPath();
            $relativePath = $versionFolder->getRelativePath($absolutePath);

            list ($filePath, $fileVersion) = FileVersions::splitPathVersion($relativePath);

            $sourceFile = $this->root->getUserFolder($owner->getUID())->get($filePath);

            $fileInfo = $sourceFile->getFileInfo();

            $versions = array_reverse($this->versionManager->getVersionsForFile($owner, $fileInfo));

            foreach ($versions as $version) {
                $versionNum = $versionNum + 1;

                $versionId = $version->getRevisionId();
                if (strcmp($versionId, $fileVersion) === 0) {
                    $key = $this->fileUtility->getVersionKey($version);
                    $key = DocumentService::GenerateRevisionId($key);

                    break;
                }
            }
        } else {
            $key = $this->fileUtility->getKey($fileInfo);
            $key = DocumentService::GenerateRevisionId($key);
        }

        if (TemplateManager::IsTemplate($fileInfo->getId())) {
            $template = true;
        }

        $fileUrl = $this->getUrl($fileInfo, $owner, $versionNum, $template);

        $fileExtension = $fileInfo->getExtension();

        return [$fileUrl, $fileExtension, $key];
    }
}