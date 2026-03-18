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

use OCA\Onlyoffice\AppConfig;
use OCA\Onlyoffice\ExtraPermissions;
use OCP\IDBConnection;
use OCP\Server;
use OCP\Share\IManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\LoggerInterface;
use Test\TestCase;

#[CoversClass(ExtraPermissions::class)]
#[Group('DB')]
class ExtraPermissionsTest extends TestCase {

    private ExtraPermissions $extraPermissions;
    private IDBConnection $connection;

    /** Snowflake-style bigint share IDs used for test fixtures */
    private const SHARE_ID_A = "1750000000000000001";
    private const SHARE_ID_B = "1750000000000000002";
    private const SHARE_ID_C = "1750000000000000003";

    protected function setUp(): void {
        parent::setUp();

        $this->connection = Server::get(IDBConnection::class);

        $this->extraPermissions = new ExtraPermissions(
            $this->createStub(LoggerInterface::class),
            $this->createStub(IManager::class),
            $this->createStub(AppConfig::class),
            $this->connection,
            null,
        );

        $this->cleanUp();
    }

    protected function tearDown(): void {
        $this->cleanUp();
        parent::tearDown();
    }

    private function cleanUp(): void {
        $this->connection->prepare(
            "DELETE FROM `*PREFIX*onlyoffice_permissions`
             WHERE `share_id` IN (?, ?, ?)"
        )->execute([self::SHARE_ID_A, self::SHARE_ID_B, self::SHARE_ID_C]);
    }

    private function insertRow(string $shareId, int $permissions = ExtraPermissions::REVIEW): void {
        $this->connection->prepare(
            "INSERT INTO `*PREFIX*onlyoffice_permissions` (`share_id`, `permissions`) VALUES (?, ?)"
        )->execute([$shareId, $permissions]);
    }

    private function rowExists(string $shareId): bool {
        $result = $this->connection->prepare(
            "SELECT COUNT(*) AS cnt FROM `*PREFIX*onlyoffice_permissions` WHERE `share_id` = ?"
        )->execute([$shareId]);
        $row = $result->fetch();
        return (int)$row["cnt"] > 0;
    }

    /**
     * Returns true and removes the row for the given share ID.
     */
    public function testDeleteRemovesExistingRow(): void {
        $this->insertRow(self::SHARE_ID_A);

        $result = $this->extraPermissions->delete(self::SHARE_ID_A);

        $this->assertTrue($result);
        $this->assertFalse($this->rowExists(self::SHARE_ID_A));
    }

    /**
     * Returns true even when no row exists for the given share ID.
     */
    public function testDeleteReturnsTrueWhenRowDoesNotExist(): void {
        $result = $this->extraPermissions->delete(self::SHARE_ID_A);

        $this->assertTrue($result);
    }

    /**
     * Removes only the targeted share row, leaving others intact.
     */
    public function testDeleteDoesNotAffectOtherRows(): void {
        $this->insertRow(self::SHARE_ID_A);
        $this->insertRow(self::SHARE_ID_B);

        $this->extraPermissions->delete(self::SHARE_ID_A);

        $this->assertFalse($this->rowExists(self::SHARE_ID_A));
        $this->assertTrue($this->rowExists(self::SHARE_ID_B));
    }

    /**
     * Removes all rows whose share IDs appear in the given list.
     */
    public function testDeleteListRemovesAllMatchingRows(): void {
        $this->insertRow(self::SHARE_ID_A);
        $this->insertRow(self::SHARE_ID_B);
        $this->insertRow(self::SHARE_ID_C);

        $result = $this->extraPermissions->deleteList([self::SHARE_ID_A, self::SHARE_ID_B, self::SHARE_ID_C]);

        $this->assertTrue($result);
        $this->assertFalse($this->rowExists(self::SHARE_ID_A));
        $this->assertFalse($this->rowExists(self::SHARE_ID_B));
        $this->assertFalse($this->rowExists(self::SHARE_ID_C));
    }

    /**
     * Removes only the listed share rows, leaving unlisted ones intact.
     */
    public function testDeleteListDoesNotAffectUnlistedRows(): void {
        $this->insertRow(self::SHARE_ID_A);
        $this->insertRow(self::SHARE_ID_B);

        $this->extraPermissions->deleteList([self::SHARE_ID_A]);

        $this->assertFalse($this->rowExists(self::SHARE_ID_A));
        $this->assertTrue($this->rowExists(self::SHARE_ID_B));
    }

    /**
     * Accepts a single-element list without SQL errors.
     */
    public function testDeleteListWithSingleElement(): void {
        $this->insertRow(self::SHARE_ID_A);

        $result = $this->extraPermissions->deleteList([self::SHARE_ID_A]);

        $this->assertTrue($result);
        $this->assertFalse($this->rowExists(self::SHARE_ID_A));
    }
}
