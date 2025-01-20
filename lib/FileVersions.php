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

namespace OCA\Onlyoffice;

use OC\Files\Node\File;
use OC\Files\View;
use OC\User\Database;
use OCA\Files_Sharing\External\Storage as SharingExternalStorage;
use OCP\Files\FileInfo;
use OCP\Files\IRootFolder;
use OCP\IUser;

/**
 * File versions
 *
 * @package OCA\Onlyoffice
 */
class FileVersions {

    /**
     * Application name
     *
     * @var string
     */
    private static $appName = "onlyoffice";

    /**
     * Changes file extension
     *
     * @var string
     */
    private static $changesExt = ".zip";

    /**
     * History file extension
     *
     * @var string
     */
    private static $historyExt = ".json";

    /**
     * File name contain author
     *
     * @var string
     */
    private static $authorExt = "_author.json";

    /**
     * Groupfolder name
     *
     * @var string
     */
    private static $groupFolderName = "__groupfolders";

    /**
     * Split file path and version id
     *
     * @param string $pathVersion - version path
     *
     * @return array
     */
    public static function splitPathVersion($pathVersion) {
        if (empty($pathVersion)) {
            return false;
        }
        if (preg_match("/(.+)\.v(\d+)$/", $pathVersion, $matches)) {
            return [$matches[1], $matches[2]];
        }
        return false;
    }

    /**
     * Check if folder is not exist
     *
     * @param View $view - view
     * @param string $path - folder path
     * @param bool $createIfNotExist - create folder if not exist
     *
     * @return bool
     */
    private static function checkFolderExist($view, $path, $createIfNotExist = false) {
        if ($view->is_dir($path)) {
            return true;
        }
        if (!$createIfNotExist) {
            return false;
        }
        $view->mkdir($path);
        return true;
    }

    /**
     * Get view and path for changes
     *
     * @param string $userId - user id
     * @param FileInfo $fileInfo - file info
     * @param bool $createIfNotExist - create folder if not exist
     *
     * @return array
     */
    private static function getView($userId, $fileInfo, $createIfNotExist = false) {
        $fileId = null;
        if ($fileInfo !== null) {
            $fileId = $fileInfo->getId();
            if ($fileInfo->getStorage()->instanceOfStorage(\OCA\GroupFolders\Mount\GroupFolderStorage::class)) {
                $view = new View("/" . self::$groupFolderName);
            } else {
                $view = new View("/" . $userId);
            }
        } else {
            $view = new View("/" . $userId);
        }

        $path = self::$appName;
        if (!self::checkFolderExist($view, $path, $createIfNotExist)) {
            return [null, null];
        }

        if ($fileId === null) {
            return [$view, $path];
        }

        $path = $path . "/" . $fileId;
        if (!self::checkFolderExist($view, $path, $createIfNotExist)) {
            return [null, null];
        }

        return [$view, $path];
    }

    /**
     * Get changes from stored to history object
     *
     * @param string $ownerId - file owner id
     * @param FileInfo $fileInfo - file info
     * @param string $versionId - file version
     * @param string $prevVersion - previous version for check
     *
     * @return array
     */
    public static function getHistoryData($ownerId, $fileInfo, $versionId, $prevVersion) {
        $logger = \OCP\Log\logger('onlyoffice');

        if ($ownerId === null || $fileInfo === null) {
            return null;
        }

        $fileId = $fileInfo->getId();
        list($view, $path) = self::getView($ownerId, $fileInfo);
        if ($view === null) {
            return null;
        }

        $historyPath = $path . "/" . $versionId . self::$historyExt;
        if (!$view->file_exists($historyPath)) {
            return null;
        }

        $historyDataString = $view->file_get_contents($historyPath);

        try {
            $historyData = json_decode($historyDataString, true);

            if ($historyData["prev"] !== $prevVersion) {
                $logger->debug("getHistoryData: previous $prevVersion != " . $historyData["prev"], ["app" => self::$appName]);

                $view->unlink($historyPath);
                $logger->debug("getHistoryData: delete $historyPath", ["app" => self::$appName]);

                $changesPath = $path . "/" . $versionId . self::$changesExt;
                if ($view->file_exists($changesPath)) {
                    $view->unlink($changesPath);
                    $logger->debug("getHistoryData: delete $changesPath", ["app" => self::$appName]);
                }
                return null;
            }

            return $historyData;
        } catch (\Exception $e) {
            $logger->error("getHistoryData: $fileId $versionId", ['exception' => $e]);
            return null;
        }
    }

    /**
     * Check if changes is stored
     *
     * @param string $ownerId - file owner id
     * @param FileInfo $fileInfo - file info
     * @param string $versionId - file version
     *
     * @return bool
     */
    public static function hasChanges($ownerId, $fileInfo, $versionId) {
        if ($ownerId === null || $fileInfo === null) {
            return false;
        }

        list($view, $path) = self::getView($ownerId, $fileInfo);
        if ($view === null) {
            return false;
        }

        $changesPath = $path . "/" . $versionId . self::$changesExt;
        return $view->file_exists($changesPath);
    }

    /**
     * Get changes file
     *
     * @param string $ownerId - file owner id
     * @param FileInfo $fileInfo - file info
     * @param string $versionId - file version
     *
     * @return File
     */
    public static function getChangesFile($ownerId, $fileInfo, $versionId) {
        if ($ownerId === null || $fileInfo === null) {
            return null;
        }
        $fileId = $fileInfo->getId();

        list($view, $path) = self::getView($ownerId, $fileInfo);
        if ($view === null) {
            return null;
        }

        $changesPath = $path . "/" . $versionId . self::$changesExt;
        if (!$view->file_exists($changesPath)) {
            return null;
        }

        $changesInfo = $view->getFileInfo($changesPath);
        $rootView = \OCP\Server::get(View::class);
        $root = \OCP\Server::get(IRootFolder::class);

        $changes = new File($root, $rootView, $view->getAbsolutePath($changesPath), $changesInfo);
        \OCP\Log\logger('onlyoffice')->debug("getChangesFile: $fileId for $ownerId get changes $changesPath", ["app" => self::$appName]);

        return $changes;
    }

    /**
     * Save history to storage
     *
     * @param FileInfo $fileInfo - file info
     * @param array $history - file history
     * @param string $changes - file changes
     * @param string $prevVersion - previous version for check
     */
    public static function saveHistory($fileInfo, $history, $changes, $prevVersion) {
        $logger = \OCP\Log\logger('onlyoffice');

        if ($fileInfo === null) {
            return;
        }

        $owner = $fileInfo->getOwner();
        if ($owner === null) {
            return;
        }

        if (empty($history) || empty($changes)) {
            return;
        }

        if ($fileInfo->getStorage()->instanceOfStorage(SharingExternalStorage::class)) {
            return;
        }

        $ownerId = $owner->getUID();
        $fileId = $fileInfo->getId();
        $versionId = $fileInfo->getMtime();

        list($view, $path) = self::getView($ownerId, $fileInfo, true);

        try {
            $changesPath = $path . "/" . $versionId . self::$changesExt;
            $view->touch($changesPath);
            $view->file_put_contents($changesPath, $changes);

            $history["prev"] = $prevVersion;
            $historyPath = $path . "/" . $versionId . self::$historyExt;
            $view->touch($historyPath);
            $view->file_put_contents($historyPath, json_encode($history));

            $logger->debug("saveHistory: $fileId for $ownerId stored changes $changesPath history $historyPath", ["app" => self::$appName]);
        } catch (\Exception $e) {
            $logger->error("saveHistory: save $fileId history error", ['exception' => $e]);
        }
    }

    /**
     * Delete all versions of file
     *
     * @param string $ownerId - file owner id
     * @param FileInfo $fileInfo - file info
     */
    public static function deleteAllVersions($ownerId, $fileInfo = null) {
        $logger = \OCP\Log\logger('onlyoffice');
        $fileId = null;
        if ($fileInfo !== null) {
            $fileId = $fileInfo->getId();
        }

        $logger->debug("deleteAllVersions $ownerId $fileId", ["app" => self::$appName]);

        if ($ownerId === null) {
            return;
        }

        list($view, $path) = self::getView($ownerId, $fileInfo);
        if ($view === null) {
            return;
        }

        $view->unlink($path);
    }

    /**
     * Delete changes and history
     *
     * @param string $ownerId - file owner id
     * @param FileInfo $fileInfo - file info
     * @param string $versionId - file version
     */
    public static function deleteVersion($ownerId, $fileInfo, $versionId) {
        if ($ownerId === null) {
            return;
        }
        if ($fileInfo === null || empty($versionId)) {
            return;
        }

        $logger = \OCP\Log\logger('onlyoffice');
        $fileId = $fileInfo->getId();
        $logger->debug("deleteVersion $fileId ($versionId)", ["app" => self::$appName]);

        list($view, $path) = self::getView($ownerId, $fileInfo);
        if ($view === null) {
            return null;
        }

        $historyPath = $path . "/" . $versionId . self::$historyExt;
        if ($view->file_exists($historyPath)) {
            $view->unlink($historyPath);
            $logger->debug("deleteVersion $historyPath", ["app" => self::$appName]);
        }

        $changesPath = $path . "/" . $versionId . self::$changesExt;
        if ($view->file_exists($changesPath)) {
            $view->unlink($changesPath);
            $logger->debug("deleteVersion $changesPath", ["app" => self::$appName]);
        }
    }

    /**
     * Clear all version history
     */
    public static function clearHistory() {
        $logger = \OCP\Log\logger('onlyoffice');

        $userDatabase = new Database();
        $userIds = $userDatabase->getUsers();

        $view = new View("/");
        $groupFolderView = new View("/" . self::$groupFolderName);

        foreach ($userIds as $userId) {
            $path = $userId . "/" . self::$appName;

            if ($view->file_exists($path)) {
                $view->unlink($path);
            }

            if ($groupFolderView->file_exists($path)) {
                $groupFolderView->unlink($path);
            }
        }

        $logger->debug("clear all history", ["app" => self::$appName]);
    }

    /**
     * Save file author
     *
     * @param FileInfo $fileInfo - file info
     * @param IUser $author - version author
     */
    public static function saveAuthor($fileInfo, $author) {
        $logger = \OCP\Log\logger('onlyoffice');

        if ($fileInfo === null || $author === null) {
            return;
        }

        $owner = $fileInfo->getOwner();
        if ($owner === null) {
            return;
        }

        if ($fileInfo->getStorage()->instanceOfStorage(SharingExternalStorage::class)) {
            return;
        }

        $ownerId = $owner->getUID();
        $fileId = $fileInfo->getId();
        $versionId = $fileInfo->getMtime();

        list($view, $path) = self::getView($ownerId, $fileInfo, true);

        try {
            $authorPath = $path . "/" . $versionId . self::$authorExt;
            $view->touch($authorPath);

            $authorData = [
                "id" => $author->getUID(),
                "name" => $author->getDisplayName()
            ];
            $view->file_put_contents($authorPath, json_encode($authorData));

            $logger->debug("saveAuthor: $fileId for $ownerId stored author $authorPath", ["app" => self::$appName]);
        } catch (\Exception $e) {
            $logger->error("saveAuthor: save $fileId author error", ['exception' => $e]);
        }
    }

    /**
     * Get version author id and name
     *
     * @param string $ownerId - file owner id
     * @param FileInfo $fileInfo - file info
     * @param string $versionId - file version
     *
     * @return array
     */
    public static function getAuthor($ownerId, $fileInfo, $versionId) {
        if ($ownerId === null || $fileInfo === null) {
            return null;
        }

        $fileId = $fileInfo->getId();
        list($view, $path) = self::getView($ownerId, $fileInfo);
        if ($view === null) {
            return null;
        }

        $authorPath = $path . "/" . $versionId . self::$authorExt;
        if (!$view->file_exists($authorPath)) {
            return null;
        }

        $authorDataString = $view->file_get_contents($authorPath);
        $author = json_decode($authorDataString, true);

        \OCP\Log\logger('onlyoffice')->debug("getAuthor: $fileId v.$versionId for $ownerId get author $authorPath", ["app" => self::$appName]);

        return $author;
    }

    /**
     * Delete version author info
     *
     * @param string $ownerId - file owner id
     * @param FileInfo $fileInfo - file info
     * @param string $versionId - file version
     */
    public static function deleteAuthor($ownerId, $fileInfo, $versionId) {
        $logger = \OCP\Log\logger('onlyoffice');

        $fileId = $fileInfo->getId();

        $logger->debug("deleteAuthor $fileId ($versionId)", ["app" => self::$appName]);

        if ($ownerId === null) {
            return;
        }
        if ($fileInfo === null || empty($versionId)) {
            return;
        }

        list($view, $path) = self::getView($ownerId, $fileInfo);
        if ($view === null) {
            return null;
        }

        $authorPath = $path . "/" . $versionId . self::$authorExt;
        if ($view->file_exists($authorPath)) {
            $view->unlink($authorPath);
            $logger->debug("deleteAuthor $authorPath", ["app" => self::$appName]);
        }
    }

    /**
     * Get version compare with files_versions
     */
    public static function getFilesVersionAppInfoCompareResult() {
        $filesVersionAppInfo = \OC::$server->getAppManager()->getAppInfo("files_versions");
        return \version_compare($filesVersionAppInfo["version"], "1.19");
    }

    /**
     * Reverese or not versions array
     *
     * @param array $versions - versions array
     */
    public static function processVersionsArray($versions) {
        if (self::getFilesVersionAppInfoCompareResult() === -1) {
            return array_reverse($versions);
        } else {
            foreach ($versions as $key => $version) {
                if ($version->getRevisionId() === $version->getSourceFile()->getMTime()) {
                    array_splice($versions, $key, 1);
                    break;
                }
            }

            return $versions;
        }
    }
}
