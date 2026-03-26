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
use OCP\Files\IRootFolder;
use OCP\IUser;
use OCP\Server;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Test\TestCase;
use Test\Traits\UserTrait;

#[CoversClass(FileVersions::class)]
#[Group('DB')]
class FileVersionsTest extends TestCase {
    use UserTrait;

    private string $userId = "onlyoffice-fv-testuser";
    private IUser $user;
    private File $file;
    private string $versionId;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpUserTrait();

        $this->user = $this->createUser($this->userId, "password");
        self::loginAsUser($this->userId);

        $userFolder = Server::get(IRootFolder::class)->getUserFolder($this->userId);
        $this->file = $userFolder->newFile("test-fileversiontest.docx", "initial content");
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
     * hasChanges returns false before any history is saved for the version.
     */
    public function testHasChangesReturnsFalseBeforeSave(): void {
        $this->assertFalse(FileVersions::hasChanges($this->userId, $this->file, $this->versionId));
    }

    /**
     * hasChanges returns false when ownerId or fileInfo is null.
     */
    public function testHasChangesReturnsFalseForNullParams(): void {
        $this->assertFalse(FileVersions::hasChanges(null, $this->file, $this->versionId));
        $this->assertFalse(FileVersions::hasChanges($this->userId, null, $this->versionId));
    }

    /**
     * hasChanges returns true after saveHistory persists changes for the version.
     */
    public function testHasChangesReturnsTrueAfterSaveHistory(): void {
        FileVersions::saveHistory($this->file, ["key" => "v1"], "changes-data", "prev-key");

        $this->assertTrue(FileVersions::hasChanges($this->userId, $this->file, $this->versionId));
    }

    /**
     * getHistoryData returns null before any history is saved.
     */
    public function testGetHistoryDataReturnsNullBeforeSave(): void {
        $this->assertNull(FileVersions::getHistoryData($this->userId, $this->file, $this->versionId, null));
    }

    /**
     * getHistoryData returns null when ownerId or fileInfo is null.
     */
    public function testGetHistoryDataReturnsNullForNullParams(): void {
        $this->assertNull(FileVersions::getHistoryData(null, $this->file, $this->versionId, null));
        $this->assertNull(FileVersions::getHistoryData($this->userId, null, $this->versionId, null));
    }

    /**
     * saveHistory persists history data that getHistoryData can retrieve.
     */
    public function testSaveAndGetHistoryDataRoundTrip(): void {
        $history = ["key" => "doc-key-v1", "url" => "https://example.com/file.docx"];
        $prevVersion = "prev-doc-key";

        FileVersions::saveHistory($this->file, $history, "changes-binary-data", $prevVersion);
        $retrieved = FileVersions::getHistoryData($this->userId, $this->file, $this->versionId, $prevVersion);

        $this->assertIsArray($retrieved);
        $this->assertSame($history["key"], $retrieved["key"]);
        $this->assertSame($prevVersion, $retrieved["prev"]);
    }

    /**
     * getHistoryData returns null and cleans up files when prevVersion does not match stored value.
     */
    public function testGetHistoryDataReturnsNullAndCleansUpOnPrevVersionMismatch(): void {
        FileVersions::saveHistory($this->file, ["key" => "v1"], "changes-data", "original-prev");

        $result = FileVersions::getHistoryData($this->userId, $this->file, $this->versionId, "different-prev");

        $this->assertNull($result);
        $this->assertFalse(FileVersions::hasChanges($this->userId, $this->file, $this->versionId));
    }

    /**
     * getChangesFile returns null before history is saved.
     */
    public function testGetChangesFileReturnsNullBeforeSave(): void {
        $this->assertNull(FileVersions::getChangesFile($this->userId, $this->file, $this->versionId));
    }

    /**
     * getChangesFile returns null when ownerId or fileInfo is null.
     */
    public function testGetChangesFileReturnsNullForNullParams(): void {
        $this->assertNull(FileVersions::getChangesFile(null, $this->file, $this->versionId));
        $this->assertNull(FileVersions::getChangesFile($this->userId, null, $this->versionId));
    }

    /**
     * getChangesFile returns a File instance after saveHistory persists changes.
     */
    public function testGetChangesFileReturnsFileAfterSave(): void {
        FileVersions::saveHistory($this->file, ["key" => "v1"], "changes-binary-data", "prev-key");

        $changesFile = FileVersions::getChangesFile($this->userId, $this->file, $this->versionId);

        $this->assertInstanceOf(File::class, $changesFile);
    }

    /**
     * saveAuthor persists author data that getAuthor can retrieve.
     */
    public function testSaveAndGetAuthorRoundTrip(): void {
        FileVersions::saveAuthor($this->file, $this->user);

        $author = FileVersions::getAuthor($this->userId, $this->file, $this->versionId);

        $this->assertIsArray($author);
        $this->assertSame($this->userId, $author["id"]);
    }

    /**
     * getAuthor returns null before any author is saved for the version.
     */
    public function testGetAuthorReturnsNullBeforeSave(): void {
        $this->assertNull(FileVersions::getAuthor($this->userId, $this->file, $this->versionId));
    }

    /**
     * getAuthor returns null when ownerId or fileInfo is null.
     */
    public function testGetAuthorReturnsNullForNullParams(): void {
        $this->assertNull(FileVersions::getAuthor(null, $this->file, $this->versionId));
        $this->assertNull(FileVersions::getAuthor($this->userId, null, $this->versionId));
    }

    /**
     * deleteAuthor removes the author file so getAuthor returns null afterwards.
     */
    public function testDeleteAuthorRemovesAuthorData(): void {
        FileVersions::saveAuthor($this->file, $this->user);

        FileVersions::deleteAuthor($this->userId, $this->file, $this->versionId);

        $this->assertNull(FileVersions::getAuthor($this->userId, $this->file, $this->versionId));
    }

    /**
     * deleteVersion removes history and changes files for the given version.
     */
    public function testDeleteVersionRemovesHistoryAndChanges(): void {
        FileVersions::saveHistory($this->file, ["key" => "v1"], "changes-data", "prev");
        FileVersions::saveAuthor($this->file, $this->user);

        FileVersions::deleteVersion($this->userId, $this->file, $this->versionId);

        $this->assertNull(FileVersions::getHistoryData($this->userId, $this->file, $this->versionId, "prev"));
        $this->assertFalse(FileVersions::hasChanges($this->userId, $this->file, $this->versionId));
    }

    /**
     * deleteAllVersions removes the entire version directory for the file.
     */
    public function testDeleteAllVersionsRemovesAllStoredData(): void {
        FileVersions::saveHistory($this->file, ["key" => "v1"], "changes-data", "prev");
        FileVersions::saveAuthor($this->file, $this->user);

        FileVersions::deleteAllVersions($this->userId, $this->file);

        $this->assertFalse(FileVersions::hasChanges($this->userId, $this->file, $this->versionId));
        $this->assertNull(FileVersions::getAuthor($this->userId, $this->file, $this->versionId));
    }

    /**
     * saveHistory does nothing when fileInfo is null.
     */
    public function testSaveHistoryDoesNothingForNullFileInfo(): void {
        FileVersions::saveHistory(null, ["key" => "v1"], "changes", "prev");

        // No exception thrown — passes if we reach this point
        $this->addToAssertionCount(1);
    }

    /**
     * saveAuthor does nothing when fileInfo or author is null.
     */
    public function testSaveAuthorDoesNothingForNullParams(): void {
        FileVersions::saveAuthor(null, $this->user);
        FileVersions::saveAuthor($this->file, null);

        $this->addToAssertionCount(1);
    }
}
