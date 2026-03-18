<?php

declare(strict_types=1);

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
