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

namespace OCA\Onlyoffice\Tests\Integration;

use OC\Files\View;
use OCA\Files_Versions\Versions\IVersionManager;
use OCA\Onlyoffice\Cron\HistoryCleanup;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\IUser;
use OCP\Server;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Test\TestCase;
use Test\Traits\UserTrait;

#[CoversClass(HistoryCleanup::class)]
#[Group('DB')]
class HistoryCleanupTest extends TestCase {
    use UserTrait;

    private string $userId = "onlyoffice-hc-testuser";
    private IUser $user;
    private ?IUser $otherUser = null;
    private File $file;
    private View $userView;
    private View $groupFolderView;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpUserTrait();

        $this->user = $this->createUser($this->userId, "password");
        self::loginAsUser($this->userId);

        $userFolder = Server::get(IRootFolder::class)->getUserFolder($this->userId);
        $this->file = $userFolder->newFile("test-historycleanup.docx", "initial content");

        $this->userView = new View("/" . $this->userId);
        $this->userView->mkdir("onlyoffice");

        $rootView = new View("/");
        if (!$rootView->is_dir("__groupfolders")) {
            $rootView->mkdir("__groupfolders");
        }
        $this->groupFolderView = new View("/__groupfolders");
        $this->groupFolderView->mkdir("onlyoffice");
    }

    protected function tearDown(): void {
        if ($this->userView->is_dir("onlyoffice")) {
            $this->userView->unlink("onlyoffice");
        }

        foreach (["onlyoffice", "1"] as $path) {
            if ($this->groupFolderView->file_exists($path)) {
                $this->groupFolderView->unlink($path);
            }
        }

        if ($this->file->isReadable()) {
            $this->file->delete();
        }

        if ($this->otherUser !== null) {
            $this->otherUser->delete();
            $this->otherUser = null;
        }

        self::logout();
        $this->tearDownUserTrait();
        parent::tearDown();
    }

    private function runJob(): void {
        $job = Server::get(HistoryCleanup::class);
        self::invokePrivate($job, "run", [[]]);
        self::loginAsUser($this->userId);
    }

    /**
     * The history folder of a file that no longer exists is removed,
     * the history folder of an existing file is kept.
     */
    public function testRunRemovesOrphanedUserHistoryFolder(): void {
        $this->userView->mkdir("onlyoffice/999999990");
        $this->userView->file_put_contents("onlyoffice/999999990/1000.zip", "changes");

        $fileId = $this->file->getId();
        $mtime = $this->file->getMTime();
        $this->userView->mkdir("onlyoffice/" . $fileId);
        $this->userView->file_put_contents("onlyoffice/" . $fileId . "/" . $mtime . ".zip", "changes");

        $this->runJob();

        $this->assertFalse($this->userView->file_exists("onlyoffice/999999990"));
        $this->assertTrue($this->userView->file_exists("onlyoffice/" . $fileId . "/" . $mtime . ".zip"));
    }

    /**
     * A history folder whose source file still exists but cannot be resolved
     * in this owner's tree - as happens on a currently offline external mount
     * or after an ownership transfer - is kept, because the file still has a
     * file cache row. This is the core safety property of the orphan check:
     * only a permanently deleted file loses its cache row.
     */
    public function testRunKeepsHistoryOfExistingButUnresolvableFile(): void {
        $otherUserId = $this->userId . "-other";
        $this->otherUser = $this->createUser($otherUserId, "password");

        self::loginAsUser($otherUserId);
        $otherFolder = Server::get(IRootFolder::class)->getUserFolder($otherUserId);
        $otherFile = $otherFolder->newFile("other-historycleanup.docx", "content");
        $otherFileId = $otherFile->getId();
        self::loginAsUser($this->userId);

        // history folder under THIS user, named with the other user's file id:
        // the file exists (cache row present) but getFirstNodeById returns null
        // for this user, so version reconciliation is skipped and nothing is removed.
        $this->userView->mkdir("onlyoffice/" . $otherFileId);
        $this->userView->file_put_contents("onlyoffice/" . $otherFileId . "/1000.zip", "changes");

        $this->runJob();

        $this->assertTrue($this->userView->file_exists("onlyoffice/" . $otherFileId . "/1000.zip"));
    }

    /**
     * Version files that match neither an existing version nor the current
     * file state are removed, the file of the current state is kept.
     */
    public function testRunRemovesStaleVersionFilesOfExistingFile(): void {
        $fileId = $this->file->getId();
        $mtime = $this->file->getMTime();
        $path = "onlyoffice/" . $fileId;

        $this->userView->mkdir($path);
        $this->userView->file_put_contents($path . "/" . $mtime . ".zip", "changes");
        $this->userView->file_put_contents($path . "/" . $mtime . ".json", "{}");
        $this->userView->file_put_contents($path . "/1000.zip", "changes");
        $this->userView->file_put_contents($path . "/1000.json", "{}");
        $this->userView->file_put_contents($path . "/1000_author.json", "{}");
        $this->userView->file_put_contents($path . "/unrelated.txt", "keep me");

        $this->runJob();

        $this->assertTrue($this->userView->file_exists($path . "/" . $mtime . ".zip"));
        $this->assertTrue($this->userView->file_exists($path . "/" . $mtime . ".json"));
        $this->assertFalse($this->userView->file_exists($path . "/1000.zip"));
        $this->assertFalse($this->userView->file_exists($path . "/1000.json"));
        $this->assertFalse($this->userView->file_exists($path . "/1000_author.json"));
        $this->assertTrue($this->userView->file_exists($path . "/unrelated.txt"));
    }

    /**
     * Version files of versions that still exist in files_versions are kept.
     */
    public function testRunKeepsVersionFilesOfExistingVersions(): void {
        $oldMtime = time() - 3600;
        $this->file->touch($oldMtime);
        $this->file->putContent("updated content");

        $userFolder = Server::get(IRootFolder::class)->getUserFolder($this->userId);
        $this->file = $userFolder->get("test-historycleanup.docx");

        $versionManager = Server::get(IVersionManager::class);
        $revisionIds = [];
        foreach ($versionManager->getVersionsForFile($this->user, $this->file) as $version) {
            $revisionIds[] = (string)$version->getRevisionId();
        }
        if (!in_array((string)$oldMtime, $revisionIds, true)) {
            $this->markTestSkipped("files_versions did not store a version for the old mtime");
        }

        $fileId = $this->file->getId();
        $path = "onlyoffice/" . $fileId;
        $this->userView->mkdir($path);
        $this->userView->file_put_contents($path . "/" . $oldMtime . ".zip", "changes");
        $this->userView->file_put_contents($path . "/1000.zip", "changes");

        $this->runJob();

        $this->assertTrue($this->userView->file_exists($path . "/" . $oldMtime . ".zip"));
        $this->assertFalse($this->userView->file_exists($path . "/1000.zip"));
    }

    /**
     * The group folder history of a permanently deleted file is removed,
     * while the history of a file that still exists is kept wholesale.
     *
     * Per-version pruning of existing group folder files is intentionally not
     * done by the job (GroupFolderVersionsListener handles it), so every
     * stored file of a still-existing source file is retained.
     */
    public function testRunCleansGroupFolderHistory(): void {
        // orphaned history folder: no file with this id exists
        $this->groupFolderView->mkdir("onlyoffice/999999991");
        $this->groupFolderView->file_put_contents("onlyoffice/999999991/1000.zip", "changes");

        // existing file inside a group folder
        $this->groupFolderView->mkdir("1");
        $this->groupFolderView->file_put_contents("1/test-historycleanup.docx", "content");
        $fileInfo = $this->groupFolderView->getFileInfo("1/test-historycleanup.docx");
        $fileId = $fileInfo->getId();
        $mtime = $fileInfo->getMtime();

        $path = "onlyoffice/" . $fileId;
        $this->groupFolderView->mkdir($path);
        $this->groupFolderView->file_put_contents($path . "/" . $mtime . ".zip", "changes");
        $this->groupFolderView->file_put_contents($path . "/1000.zip", "changes");

        $this->runJob();

        $this->assertFalse($this->groupFolderView->file_exists("onlyoffice/999999991"));
        $this->assertTrue($this->groupFolderView->file_exists($path . "/" . $mtime . ".zip"));
        $this->assertTrue($this->groupFolderView->file_exists($path . "/1000.zip"));
    }
}
