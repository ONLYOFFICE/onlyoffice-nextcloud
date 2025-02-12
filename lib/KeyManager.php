<?php
/**
 *
 * (c) Copyright Ascensio System SIA 2025
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

/**
 * Key manager
 *
 * @package OCA\Onlyoffice
 */
class KeyManager {

    /**
     * Table name
     */
    private const TABLENAME_KEY = "onlyoffice_filekey";

    /**
     * Get document identifier
     *
     * @param integer $fileId - file identifier
     *
     * @return string
     */
    public static function get($fileId) {
        $connection = \OC::$server->getDatabaseConnection();
        $select = $connection->prepare("
            SELECT `key`
            FROM  `*PREFIX*" . self::TABLENAME_KEY . "`
            WHERE `file_id` = ?
        ");
        $result = $select->execute([$fileId]);

        $keys = $result ? $select->fetch() : [];
        $key = is_array($keys) && isset($keys["key"]) ? $keys["key"] : "";

        return $key;
    }

    /**
     * Store document identifier
     *
     * @param integer $fileId - file identifier
     * @param integer $key - file key
     *
     * @return bool
     */
    public static function set($fileId, $key) {
        $connection = \OC::$server->getDatabaseConnection();
        $insert = $connection->prepare("
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
     *
     * @return bool
     */
    public static function delete($fileId, $unlock = false) {
        $connection = \OC::$server->getDatabaseConnection();
        $delete = $connection->prepare(
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
     *
     * @return bool
     */
    public static function lock($fileId, $lock = true) {
        $connection = \OC::$server->getDatabaseConnection();
        $update = $connection->prepare("
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
     *
     * @return bool
     */
    public static function setForcesave($fileId, $fs = true) {
        $connection = \OC::$server->getDatabaseConnection();
        $update = $connection->prepare("
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
     *
     * @return bool
     */
    public static function wasForcesave($fileId) {
        $connection = \OC::$server->getDatabaseConnection();
        $select = $connection->prepare("
            SELECT `fs`
            FROM  `*PREFIX*" . self::TABLENAME_KEY . "`
            WHERE `file_id` = ?
        ");
        $result = $select->execute([$fileId]);

        $rows = $result ? $select->fetch() : [];
        $fs = is_array($rows) && isset($rows["fs"]) ? $rows["fs"] : 0;

        return $fs === 1 || $fs === "1";
    }
}
