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

namespace OCA\Onlyoffice;

use OCP\IDBConnection;

/**
 * Key manager
 *
 * @package OCA\Onlyoffice
 */
class KeyManager {

    public function __construct(private IDBConnection $connection) {}

    /**
     * Table name
     */
    private const TABLENAME_KEY = "onlyoffice_filekey";

    /**
     * Get document identifier by file id
     */
    public function get(int $fileId): string {
        $select = $this->connection->prepare("
            SELECT `key`
            FROM  `*PREFIX*" . self::TABLENAME_KEY . "`
            WHERE `file_id` = ?
        ");
        $result = $select->execute([$fileId]);
        $key = $result->fetchOne();

        return $key === false ? "" : $key;
    }

    /**
     * Store document identifier
     */
    public function set(int $fileId, string $key): bool {
        $insert = $this->connection->prepare("
            INSERT INTO `*PREFIX*" . self::TABLENAME_KEY . "`
                (`file_id`, `key`)
            VALUES (?, ?)
        ");
        return (bool)$insert->execute([$fileId, $key]);
    }

    /**
     * Delete document identifier
     *
     * @param integer $fileId - file identifier
     * @param bool $unlock - delete even with lock label
     */
    public function delete(int $fileId, bool $unlock = false): bool {
        $delete = $this->connection->prepare(
            "
            DELETE FROM `*PREFIX*" . self::TABLENAME_KEY . "`
            WHERE `file_id` = ?
            " . ($unlock === false ? "AND `lock` != 1" : "")
        );
        return (bool)$delete->execute([$fileId]);
    }

    /**
     * Change lock status
     *
     * @param integer $fileId - file identifier
     * @param bool $lock - status
     */
    public function lock(int $fileId, bool $lock = true): bool {
        $update = $this->connection->prepare("
            UPDATE `*PREFIX*" . self::TABLENAME_KEY . "`
            SET `lock` = ?
            WHERE `file_id` = ?
        ");
        return (bool)$update->execute([$lock === true ? 1 : 0, $fileId]);
    }

    /**
     * Change forcesave status
     *
     * @param integer $fileId - file identifier
     * @param bool $fs - status
     */
    public function setForcesave(int $fileId, bool $fs = true): bool {
        $update = $this->connection->prepare("
            UPDATE `*PREFIX*" . self::TABLENAME_KEY . "`
            SET `fs` = ?
            WHERE `file_id` = ?
        ");
        return (bool)$update->execute([$fs === true ? 1 : 0, $fileId]);
    }

    /**
     * Get forcesave status
     *
     * @param integer $fileId - file identifier
     */
    public function wasForcesave(int $fileId): bool {
        $select = $this->connection->prepare("
            SELECT `fs`
            FROM  `*PREFIX*" . self::TABLENAME_KEY . "`
            WHERE `file_id` = ?
        ");
        $result = $select->execute([$fileId]);
        $fs = $result->fetchOne();

        return (bool) $fs;
    }
}
