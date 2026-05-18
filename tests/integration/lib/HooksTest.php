<?php

declare(strict_types=1);

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

use OC\Files\Node\File;
use OCA\Onlyoffice\FileVersions;
use OCA\Onlyoffice\Hooks;
use OCP\Files\IRootFolder;
use OCP\IUser;
use OCP\Server;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Test\TestCase;
use Test\Traits\UserTrait;

#[CoversClass(Hooks::class)]
#[Group('DB')]
class HooksTest extends TestCase {
    use UserTrait;

    private string $userId = "onlyoffice-hooks-testuser";
    private IUser $user;
    private File $file;
    private string $versionId;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpUserTrait();

        $this->user = $this->createUser($this->userId, "password");
        self::loginAsUser($this->userId);

        $userFolder = Server::get(IRootFolder::class)->getUserFolder($this->userId);
        $this->file = $userFolder->newFile("hook-test-file.docx", "content");
        $this->versionId = (string)$this->file->getMtime();
    }

    protected function tearDown(): void {
        FileVersions::deleteAllVersions($this->userId, $this->file);

        if ($this->file->isReadable()) {
            $this->file->delete();
        }

        self::logout();
        $this->tearDownUserTrait();
        parent::tearDown();
    }

    /**
     * Does nothing when the path param is empty.
     */
    public function testFileVersionDeleteDoesNothingForEmptyPath(): void {
        Hooks::fileVersionDelete(["path" => ""]);

        // No exception — passes if we reach this point
        $this->addToAssertionCount(1);
    }

    /**
     * Does nothing when the path has no version suffix.
     */
    public function testFileVersionDeleteDoesNothingForPathWithoutVersionSuffix(): void {
        Hooks::fileVersionDelete(["path" => "/hook-test-file.docx"]);

        $this->addToAssertionCount(1);
    }

    /**
     * Does nothing when the file referenced in the path does not exist.
     */
    public function testFileVersionDeleteDoesNothingForNonExistentFile(): void {
        Hooks::fileVersionDelete(["path" => "/non-existent-file.docx.v" . $this->versionId]);

        $this->addToAssertionCount(1);
    }

    /**
     * Deletes the version history and changes when a valid versioned path is given.
     */
    public function testFileVersionDeleteRemovesHistoryAndChanges(): void {
        FileVersions::saveHistory($this->file, ["key" => "v1"], "changes-data", "prev-key");

        Hooks::fileVersionDelete(["path" => "/hook-test-file.docx.v" . $this->versionId]);

        $this->assertFalse(FileVersions::hasChanges($this->userId, $this->file, $this->versionId));
    }

    /**
     * Deletes the version author when a valid versioned path is given.
     */
    public function testFileVersionDeleteRemovesAuthor(): void {
        FileVersions::saveAuthor($this->file, $this->user);

        Hooks::fileVersionDelete(["path" => "/hook-test-file.docx.v" . $this->versionId]);

        $this->assertNull(FileVersions::getAuthor($this->userId, $this->file, $this->versionId));
    }

    /**
     * Deletes both history and author in a single call when both are present.
     */
    public function testFileVersionDeleteRemovesHistoryAndAuthorTogether(): void {
        FileVersions::saveHistory($this->file, ["key" => "v1"], "changes-data", "prev-key");
        FileVersions::saveAuthor($this->file, $this->user);

        Hooks::fileVersionDelete(["path" => "/hook-test-file.docx.v" . $this->versionId]);

        $this->assertFalse(FileVersions::hasChanges($this->userId, $this->file, $this->versionId));
        $this->assertNull(FileVersions::getAuthor($this->userId, $this->file, $this->versionId));
    }
}
