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

use OCA\Onlyoffice\KeyManager;
use OCP\IDBConnection;
use OCP\Server;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Test\TestCase;

#[CoversClass(KeyManager::class)]
#[Group('DB')]
class KeyManagerTest extends TestCase {

    private KeyManager $keyManager;
    private IDBConnection $connection;

    /** Arbitrary file ID used across tests; cleaned up in tearDown */
    private const FILE_ID = 123456789;

    protected function setUp(): void {
        parent::setUp();

        $this->connection = Server::get(IDBConnection::class);
        $this->keyManager = new KeyManager($this->connection);

        $this->cleanUp();
    }

    protected function tearDown(): void {
        $this->cleanUp();
        parent::tearDown();
    }

    private function cleanUp(): void {
        $this->connection->prepare(
            "DELETE FROM `*PREFIX*onlyoffice_filekey` WHERE `file_id` = ?"
        )->execute([self::FILE_ID]);
    }

    /**
     * Returns an empty string when no key exists for the given file.
     */
    public function testGetReturnsEmptyStringWhenNoKeyExists(): void {
        $this->assertSame("", $this->keyManager->get(self::FILE_ID));
    }

    /**
     * Returns the stored key after it has been set.
     */
    public function testGetReturnsKeyAfterSet(): void {
        $this->keyManager->set(self::FILE_ID, "abc123");

        $this->assertSame("abc123", $this->keyManager->get(self::FILE_ID));
    }

    /**
     * Persists the key to the database and returns true on success.
     */
    public function testSetReturnsTrueAndPersistsKey(): void {
        $result = $this->keyManager->set(self::FILE_ID, "key-value");

        $this->assertTrue($result);
        $this->assertSame("key-value", $this->keyManager->get(self::FILE_ID));
    }

    /**
     * Deletes an unlocked row and returns true; the key is no longer retrievable.
     */
    public function testDeleteRemovesUnlockedRow(): void {
        $this->keyManager->set(self::FILE_ID, "key-value");

        $result = $this->keyManager->delete(self::FILE_ID);

        $this->assertTrue($result);
        $this->assertSame("", $this->keyManager->get(self::FILE_ID));
    }

    /**
     * Does not delete a locked row when $unlock is false (the default).
     */
    public function testDeleteDoesNotRemoveLockedRowByDefault(): void {
        $this->keyManager->set(self::FILE_ID, "key-value");
        $this->keyManager->lock(self::FILE_ID, true);

        $this->keyManager->delete(self::FILE_ID);

        $this->assertSame("key-value", $this->keyManager->get(self::FILE_ID));
    }

    /**
     * Deletes a locked row when $unlock is explicitly true.
     */
    public function testDeleteRemovesLockedRowWhenUnlockIsTrue(): void {
        $this->keyManager->set(self::FILE_ID, "key-value");
        $this->keyManager->lock(self::FILE_ID, true);

        $result = $this->keyManager->delete(self::FILE_ID, true);

        $this->assertTrue($result);
        $this->assertSame("", $this->keyManager->get(self::FILE_ID));
    }

    /**
     * Returns false for forcesave status when no row exists.
     */
    public function testWasForcesaveReturnsFalseWhenNoRowExists(): void {
        $this->assertFalse($this->keyManager->wasForcesave(self::FILE_ID));
    }

    /**
     * Returns false for forcesave status immediately after inserting a key.
     */
    public function testWasForcesaveReturnsFalseByDefault(): void {
        $this->keyManager->set(self::FILE_ID, "key-value");

        $this->assertFalse($this->keyManager->wasForcesave(self::FILE_ID));
    }

    /**
     * Returns true for forcesave status after setForcesave is called with true.
     */
    public function testWasForcesaveReturnsTrueAfterSetForcesave(): void {
        $this->keyManager->set(self::FILE_ID, "key-value");
        $this->keyManager->setForcesave(self::FILE_ID, true);

        $this->assertTrue($this->keyManager->wasForcesave(self::FILE_ID));
    }

    /**
     * Returns false for forcesave status after setForcesave is reset to false.
     */
    public function testWasForcesaveReturnsFalseAfterReset(): void {
        $this->keyManager->set(self::FILE_ID, "key-value");
        $this->keyManager->setForcesave(self::FILE_ID, true);
        $this->keyManager->setForcesave(self::FILE_ID, false);

        $this->assertFalse($this->keyManager->wasForcesave(self::FILE_ID));
    }
}
