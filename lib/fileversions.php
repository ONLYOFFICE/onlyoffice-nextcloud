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

use OC\Files\Node\File;
use OC\Files\View;
use OC\User\Database;

use OCP\Files\FileInfo;
use OCP\IUser;

use OCA\Files_Sharing\External\Storage as SharingExternalStorage;

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
     * Split file path and version id
     *
     * @param string $pathVersion - version path
     *
     * @return array
     */
    public static function splitPathVersion($pathVersion) {
        $pos = strrpos($pathVersion, ".v");
        if ($pos === false) {
            return false;
        }
        $filePath = substr($pathVersion, 0, $pos);
        $versionId = substr($pathVersion, 2 + $pos - strlen($pathVersion));
        return [$filePath, $versionId];
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
     * @param string $fileId - file id
     * @param bool $createIfNotExist - create folder if not exist
     *
     * @return array
     */
    private static function getView($userId, $fileId, $createIfNotExist = false) {
        $view = new View("/" . $userId);

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
     * @param string $fileId - file id
     * @param string $versionId - file version
     * @param string $prevVersion - previous version for check
     *
     * @return array
     */
    public static function getHistoryData($ownerId, $fileId, $versionId, $prevVersion) {
        $logger = \OC::$server->getLogger();

        if ($ownerId === null || $fileId === null) {
            return null;
        }

        list ($view, $path) = self::getView($ownerId, $fileId);
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
            $logger->logException($e, ["message" => "getHistoryData: $fileId $versionId", "app" => self::$appName]);
            return null;
        }
    }

    /**
     * Check if changes is stored
     *
     * @param string $ownerId - file owner id
     * @param string $fileId - file id
     * @param string $versionId - file version
     *
     * @return bool
     */
    public static function hasChanges($ownerId, $fileId, $versionId) {
        if ($ownerId === null || $fileId === null) {
            return false;
        }

        list ($view, $path) = self::getView($ownerId, $fileId);
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
     * @param string $fileId - file id
     * @param string $versionId - file version
     *
     * @return File
     */
    public static function getChangesFile($ownerId, $fileId, $versionId) {
        if ($ownerId === null || $fileId === null) {
            return null;
        }

        list ($view, $path) = self::getView($ownerId, $fileId);
        if ($view === null) {
            return null;
        }

        $changesPath = $path . "/" . $versionId . self::$changesExt;
        if (!$view->file_exists($changesPath)) {
            return null;
        }

        $changesInfo = $view->getFileInfo($changesPath);
        $changes = new File($view->getRoot(), $view, $changesPath, $changesInfo);

        \OC::$server->getLogger()->debug("getChangesFile: $fileId for $ownerId get changes $changesPath", ["app" => self::$appName]);

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
        $logger = \OC::$server->getLogger();

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

        list ($view, $path) = self::getView($ownerId, $fileId, true);

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
            $logger->logException($e, ["message" => "saveHistory: save $fileId history error", "app" => self::$appName]);
        }
    }

    /**
     * Delete all versions of file
     *
     * @param string $ownerId - file owner id
     * @param string $fileId - file id
     */
    public static function deleteAllVersions($ownerId, $fileId = null) {
        $logger = \OC::$server->getLogger();

        $logger->debug("deleteAllVersions $ownerId $fileId", ["app" => self::$appName]);

        if ($ownerId === null) {
            return;
        }

        list ($view, $path) = self::getView($ownerId, $fileId);
        if ($view === null) {
            return;
        }

        $view->unlink($path);
    }

    /**
     * Delete changes and history
     *
     * @param string $ownerId - file owner id
     * @param string $fileId - file id
     * @param string $versionId - file version
    */
    public static function deleteVersion($ownerId, $fileId, $versionId) {
        $logger = \OC::$server->getLogger();

        $logger->debug("deleteVersion $fileId ($versionId)", ["app" => self::$appName]);

        if ($ownerId === null) {
            return;
        }
        if ($fileId === null || empty($versionId)) {
            return;
        }

        list ($view, $path) = self::getView($ownerId, $fileId);
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
        $logger = \OC::$server->getLogger();

        $userDatabase = new Database();
        $userIds = $userDatabase->getUsers();

        $view = new View("/");

        foreach ($userIds as $userId) {
            $path = $userId . "/" . self::$appName;

            if ($view->file_exists($path)) {
                $view->unlink($path);
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
        $logger = \OC::$server->getLogger();

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

        list ($view, $path) = self::getView($ownerId, $fileId, true);

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
            $logger->logException($e, ["message" => "saveAuthor: save $fileId author error", "app" => self::$appName]);
        }
    }

    /**
     * Get version author id and name
     *
     * @param string $ownerId - file owner id
     * @param string $fileId - file id
     * @param string $versionId - file version
     *
     * @return array
     */
    public static function getAuthor($ownerId, $fileId, $versionId) {
        if ($ownerId === null || $fileId === null) {
            return null;
        }

        list ($view, $path) = self::getView($ownerId, $fileId);
        if ($view === null) {
            return null;
        }

        $authorPath = $path . "/" . $versionId . self::$authorExt;
        if (!$view->file_exists($authorPath)) {
            return null;
        }

        $authorDataString = $view->file_get_contents($authorPath);
        $author = json_decode($authorDataString, true);

        \OC::$server->getLogger()->debug("getAuthor: $fileId v.$versionId for $ownerId get author $authorPath", ["app" => self::$appName]);

        return $author;
    }

    /**
     * Delete version author info
     *
     * @param string $ownerId - file owner id
     * @param string $fileId - file id
     * @param string $versionId - file version
    */
    public static function deleteAuthor($ownerId, $fileId, $versionId) {
        $logger = \OC::$server->getLogger();

        $logger->debug("deleteAuthor $fileId ($versionId)", ["app" => self::$appName]);

        if ($ownerId === null) {
            return;
        }
        if ($fileId === null || empty($versionId)) {
            return;
        }

        list ($view, $path) = self::getView($ownerId, $fileId);
        if ($view === null) {
            return null;
        }

        $authorPath = $path . "/" . $versionId . self::$authorExt;
        if ($view->file_exists($authorPath)) {
            $view->unlink($authorPath);
            $logger->debug("deleteAuthor $authorPath", ["app" => self::$appName]);
        }
    }
}
