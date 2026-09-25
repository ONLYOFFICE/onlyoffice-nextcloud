<?php
/*
 * Copyright (C) Ascensio System SIA, 2009-2026
 *
 * This program is a free software product. You can redistribute it and/or
 * modify it under the terms of the GNU Affero General Public License (AGPL)
 * version 3 as published by the Free Software Foundation, together with the
 * additional terms provided in the LICENSE file.
 *
 * This program is distributed WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. For
 * details, see the GNU AGPL at: https://www.gnu.org/licenses/agpl-3.0.html
 *
 * You can contact Ascensio System SIA by email at info@onlyoffice.com
 * or by postal mail at 20A-6 Ernesta Birznieka-Upisha Street, Riga,
 * LV-1050, Latvia, European Union.
 *
 * The interactive user interfaces in modified versions of the Program
 * are required to display Appropriate Legal Notices in accordance with
 * Section 5 of the GNU AGPL version 3.
 *
 * No trademark rights are granted under this License.
 *
 * All non-code elements of the Product, including illustrations,
 * icon sets, and technical writing content, are licensed under the
 * Creative Commons Attribution-ShareAlike 4.0 International License:
 * https://creativecommons.org/licenses/by-sa/4.0/legalcode
 *
 * This license applies only to such non-code elements and does not
 * modify or replace the licensing terms applicable to the Program's
 * source code, which remains licensed under the GNU Affero General
 * Public License v3.
 *
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace OCA\Onlyoffice\Cron;

use OC\Files\View;
use OCA\Files_Versions\Versions\IVersionManager;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJob;
use OCP\BackgroundJob\TimedJob;
use OCP\Files\File;
use OCP\Files\FileInfo;
use OCP\Files\IRootFolder;
use OCP\IDBConnection;
use OCP\IUser;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Garbage collection background job for the version history storage
 *
 * The stored changes, history and author files are normally removed in
 * reaction to events (file deletion, version expiration), but several
 * deletion paths never emit those events: deleting a folder does not
 * dispatch per-file NodeDeletedEvent, version expiration cannot always
 * resolve the file and removing a whole group folder is silent. This job
 * reconciles the history storage with the existing files and versions.
 *
 * A history folder is only removed once the source file it belongs to no
 * longer has any entry in the file cache, which is layout independent and
 * true only when the file has been permanently deleted: a file that still
 * exists - even on a currently unavailable external mount or after an
 * ownership transfer - keeps its file cache row, so its history is never
 * removed by mistake.
 */
class HistoryCleanup extends TimedJob {

    /**
     * Groupfolder name
     */
    private static string $groupFolderName = "__groupfolders";

    /**
     * Pattern of the stored version file names (changes, history and author)
     */
    private static string $versionFilePattern = "/^(\d+)(\.zip|\.json|_author\.json)$/";

    /**
     * Count of removed history folders
     */
    private int $deletedFolders = 0;

    /**
     * Count of removed version files
     */
    private int $deletedFiles = 0;

    public function __construct(
        ITimeFactory $time,
        private readonly string $appName,
        private readonly IUserManager $userManager,
        private readonly IRootFolder $rootFolder,
        private readonly IDBConnection $connection,
        private readonly LoggerInterface $logger,
        private readonly ?IVersionManager $versionManager
    ) {
        parent::__construct($time);
        $this->setInterval(60 * 60 * 24 * 7);
        $this->setTimeSensitivity(IJob::TIME_INSENSITIVE);
    }

    /**
     * Makes the history garbage collection
     *
     * @param array $argument unused argument
     */
    protected function run($argument): void {
        $this->deletedFolders = 0;
        $this->deletedFiles = 0;

        $this->userManager->callForSeenUsers(function (IUser $user): void {
            try {
                $this->cleanupUserHistory($user);
            } catch (Throwable $e) {
                $this->logger->error("HistoryCleanup: cleanup for user {$user->getUID()} error", ["exception" => $e]);
            } finally {
                \OC_Util::tearDownFS();
            }
        });

        try {
            $this->cleanupGroupFolderHistory();
        } catch (Throwable $e) {
            $this->logger->error("HistoryCleanup: cleanup for group folders error", ["exception" => $e]);
        }

        $this->logger->info("HistoryCleanup: removed {$this->deletedFolders} orphaned history folders and {$this->deletedFiles} stale version files", ["app" => $this->appName]);
    }

    /**
     * Remove history of deleted files and expired versions of a user
     *
     * @param IUser $user - user
     */
    private function cleanupUserHistory(IUser $user): void {
        $userId = $user->getUID();

        $view = new View("/" . $userId);
        if (!$view->is_dir($this->appName)) {
            return;
        }

        $userFolder = $this->rootFolder->getUserFolder($userId);

        $entries = $view->getDirectoryContent($this->appName);
        if (!is_array($entries)) {
            return;
        }

        foreach ($entries as $entry) {
            $name = $entry->getName();
            if ($entry->getType() !== FileInfo::TYPE_FOLDER || !ctype_digit($name)) {
                continue;
            }

            $fileId = (int)$name;
            $path = $this->appName . "/" . $name;

            // The source file was permanently deleted: reclaim the whole folder.
            if (!$this->fileExistsInCache($fileId)) {
                $view->unlink($path);
                $this->deletedFolders++;
                continue;
            }

            // The source file still exists: drop the stored changes of versions
            // that files_versions has already expired. Skip (never delete) when
            // it cannot be resolved here, e.g. on a currently offline mount.
            if ($this->versionManager === null) {
                continue;
            }

            $file = $userFolder->getFirstNodeById($fileId);
            if (!($file instanceof File)) {
                continue;
            }

            $keepIds = [];
            try {
                foreach ($this->versionManager->getVersionsForFile($user, $file) as $version) {
                    $keepIds[(string)$version->getRevisionId()] = true;
                }
            } catch (Throwable $e) {
                $this->logger->debug("HistoryCleanup: cannot get versions of $name", ["exception" => $e]);
                continue;
            }

            $this->cleanupVersionFiles($view, $path, $keepIds, $file->getMTime());
        }
    }

    /**
     * Remove history of deleted files in group folders
     *
     * Per-version cleanup of still-existing group folder files is handled by
     * GroupFolderVersionsListener, since the group folder version layout is
     * internal to the groupfolders app and must not be assumed here. As a
     * consequence there is no periodic backstop for a group folder file whose
     * expiry event was missed (app disabled at the time, listener error): its
     * stale per-version files are only reclaimed once the whole file is
     * deleted. This is an accepted trade-off against guessing at the internal
     * layout, which previously deleted history of live files by mistake.
     */
    private function cleanupGroupFolderHistory(): void {
        $view = new View("/" . self::$groupFolderName);
        if (!$view->is_dir($this->appName)) {
            return;
        }

        $entries = $view->getDirectoryContent($this->appName);
        if (!is_array($entries)) {
            return;
        }

        foreach ($entries as $entry) {
            $name = $entry->getName();
            if ($entry->getType() !== FileInfo::TYPE_FOLDER || !ctype_digit($name)) {
                continue;
            }

            if (!$this->fileExistsInCache((int)$name)) {
                $view->unlink($this->appName . "/" . $name);
                $this->deletedFolders++;
            }
        }
    }

    /**
     * Remove stored version files that do not belong to an existing version
     *
     * @param View $view - view of the history storage
     * @param string $path - path of the file history folder
     * @param array $keepIds - version ids that still exist
     * @param int $mtime - current file modification time
     */
    private function cleanupVersionFiles(View $view, string $path, array $keepIds, int $mtime): void {
        $entries = $view->getDirectoryContent($path);
        if (!is_array($entries)) {
            return;
        }

        foreach ($entries as $entry) {
            if (!preg_match(self::$versionFilePattern, $entry->getName(), $matches)) {
                continue;
            }

            $versionId = $matches[1];
            if (isset($keepIds[$versionId]) || (int)$versionId >= $mtime) {
                continue;
            }

            $view->unlink($path . "/" . $entry->getName());
            $this->deletedFiles++;
        }
    }

    /**
     * Check whether a file id still has an entry in the file cache
     *
     * A file that exists anywhere - any storage, any group folder, or the
     * trash - keeps its file cache row, so this returns false only when the
     * file has been permanently deleted.
     *
     * @param int $fileId - file id
     */
    private function fileExistsInCache(int $fileId): bool {
        $select = $this->connection->prepare("
            SELECT `fileid`
            FROM `*PREFIX*filecache`
            WHERE `fileid` = ?
        ");
        $result = $select->execute([$fileId]);
        $exists = $result->fetchOne();
        $result->closeCursor();

        return $exists !== false;
    }
}
