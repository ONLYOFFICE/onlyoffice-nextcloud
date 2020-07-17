<?php
/**
 *
 * (c) Copyright Ascensio System SIA 2020
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
     * Check if folder is not exist
     *
     * @param string $userId - user id
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
     * @param string $user - user id
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
     * @param OCP\Files\FileInfo $fileInfo - file info
     * @param array $history - file history
     * @param string $changes - file changes
     * @param string $prevVersion - previous version for check
     */
    public static function saveHistory($fileInfo, $history, $changes, $prevVersion) {
        $logger = \OC::$server->getLogger();

        $owner = $fileInfo->getOwner();

        if ($owner === null) {
            return;
        }
        if (empty($history) || empty($changes)) {
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
    public static function deleteAllVersions($ownerId, $fileId) {
        $logger = \OC::$server->getLogger();

        $logger->debug("deleteAllVersions $ownerId $fileId", ["app" => self::$appName]);

        if ($ownerId === null || $fileId === null) {
            return;
        }

        list ($view, $path) = self::getView($ownerId, $fileId);
        if ($view === null) {
            return;
        }

        $view->unlink($path);
    }
}
