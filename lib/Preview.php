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

namespace OCA\Onlyoffice;

use OC\Files\View;
use OCA\Files_Sharing\External\Storage as SharingExternalStorage;
use OCA\Files_Versions\Versions\IVersionManager;
use OCP\AppFramework\QueryException;
use OCP\Files\File;
use OCP\IImage;
use OCP\Files\FileInfo;
use OCP\Files\IRootFolder;
use OCP\IL10N;
use OCP\Image;
use OCP\ISession;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Preview\IProviderV2;
use OCP\Share\IManager;
use Psr\Log\LoggerInterface;

/**
 * Preview provider
 *
 * @package OCA\Onlyoffice
 */
class Preview implements IProviderV2 {

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
     * @var LoggerInterface
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
        "application/vnd.openxmlformats-officedocument.wordprocessingml.document.docxf",
        "application/vnd.openxmlformats-officedocument.wordprocessingml.document.oform",
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
    private const THUMBEXTENSION = "jpeg";

    /**
     * @param string $appName - application name
     * @param IRootFolder $root - root folder
     * @param LoggerInterface $logger - logger
     * @param IL10N $trans - l10n service
     * @param AppConfig $config - application configuration
     * @param IURLGenerator $urlGenerator - url generator service
     * @param Crypt $crypt - hash generator
     * @param IManager $shareManager - share manager
     * @param ISession $session - session
     */
    public function __construct(
        string $appName,
        IRootFolder $root,
        LoggerInterface $logger,
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
                $this->logger->error("VersionManager init error", ["exception" => $e]);
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
    public function getMimeType(): string {
        $m = self::getMimeTypeRegex();
        return $m;
    }

    /**
     * The method checks if the file can be converted
     *
     * @param FileInfo $file - File
     *
     * @return bool
     */
    public function isAvailable(FileInfo $file): bool {
        if ($this->config->getPreview() !== true) {
            return false;
        }
        if (!$file
            || $file->getSize() === 0
            || $file->getSize() > $this->config->getLimitThumbSize()) {
            return false;
        }
        if (!in_array($file->getMimetype(), self::$capabilities, true)) {
            return false;
        }
        if ($file->getStorage()->instanceOfStorage(SharingExternalStorage::class)) {
            return false;
        }
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function getThumbnail(File $file, int $maxX, int $maxY): ?IImage {
        $this->logger->debug("getThumbnail {$file->getId()} $maxX $maxY");

        [$fileUrl, $extension, $key] = $this->getFileParam($file);
        if ($fileUrl === null || $extension === null || $key === null) {
            return null;
        }

        $imageUrl = null;
        $documentService = new DocumentService($this->trans, $this->config);
        try {
            $imageUrl = $documentService->getConvertedUri($fileUrl, $extension, self::THUMBEXTENSION, $key);
        } catch (\Exception $e) {
            $this->logger->error("getConvertedUri: from $extension to " . self::THUMBEXTENSION, ["exception" => $e]);
            return null;
        }

        try {
            $thumbnail = $documentService->request($imageUrl);
        } catch (\Exception $e) {
            $this->logger->error("Failed to download thumbnail", ["exception" => $e]);
            return null;
        }

        $image = new Image();
        $image->loadFromData($thumbnail);

        if ($image->valid()) {
            $image->scaleDownToFit($maxX, $maxY);
            return $image;
        }

        return null;
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
    private function getUrl(File $file, ?IUser $user, int $version = 0, bool $template = false): string {

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

        $hashUrl = $this->crypt->getHash($data);

        $fileUrl = $this->urlGenerator->linkToRouteAbsolute($this->appName . ".callback.download", ["doc" => $hashUrl]);

        if (!$this->config->useDemo() && !empty($this->config->getStorageUrl())) {
            $fileUrl = str_replace($this->urlGenerator->getAbsoluteURL("/"), $this->config->getStorageUrl(), $fileUrl);
        }

        return $fileUrl;
    }

    /**
     * Generate array with file parameters
     *
     * @param File $file - file
     *
     * @return array
     */
    private function getFileParam(File $file): array {
        if ($file->getType() !== FileInfo::TYPE_FILE || $file->getSize() === 0) {
            return [null, null, null];
        }

        $owner = $file->getOwner();

        $key = null;
        $versionNum = 0;
        $template = false;
        if (FileVersions::splitPathVersion($file->getPath()) !== false) {
            if ($this->versionManager === null || $owner === null) {
                return [null, null, null];
            }

            $versionFolder = new View("/" . $owner->getUID() . "/files_versions");
            $absolutePath = $file->getPath();
            $relativePath = $versionFolder->getRelativePath($absolutePath);

            [$filePath, $fileVersion] = FileVersions::splitPathVersion($relativePath);
            if ($filePath === null) {
                return [null, null, null];
            }

            $file = $this->root->getUserFolder($owner->getUID())->get($filePath);

            $versions = FileVersions::processVersionsArray($this->versionManager->getVersionsForFile($owner, $file));

            foreach ($versions as $version) {
                $versionNum = $versionNum + 1;

                $versionId = $version->getRevisionId();
                if (strcmp($versionId, $fileVersion) === 0) {
                    $key = $this->fileUtility->getVersionKey($version);
                    $key = DocumentService::generateRevisionId($key);

                    break;
                }
            }
        } else {
            $key = $this->fileUtility->getKey($file);
            $key = DocumentService::generateRevisionId($key);
        }

        if (TemplateManager::isTemplate($file->getId())) {
            $template = true;
        }

        $fileUrl = $this->getUrl($file, $owner, $versionNum, $template);

        $fileExtension = $file->getExtension();

        return [$fileUrl, $fileExtension, "thumb_$key"];
    }
}
