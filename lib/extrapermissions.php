<?php
/**
 *
 * (c) Copyright Ascensio System SIA 2022
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 */

namespace OCA\Onlyoffice;

/**
 * Class expands base permissions
 *
 * @package OCA\Onlyoffice
 */
class ExtraPermissions {

    /**
     * Application name
     *
     * @var string
     */
    private const App_Name = "onlyoffice";

    /**
     * Table name
     */
    private const TableName_Key = "onlyoffice_permissions";

    /**
     * Extra permission values
     *
     * @var integer
     */
    public const Review = 1;
    public const Comment = 2;
    public const FillForms = 4;
    public const ModifyFilter = 8;

    /**
     * Extra permission names
     *
     * @var string
     */
    public const ReviewName = "review";
    public const CommentName = "comment";
    public const FillFormsName = "fillForms";
    public const ModifyFilterName = "modifyFilter";

    /**
     * Get extra permissions for share
     *
     * @param integer $shareId - share identifier
     *
     * @return integer
     */
    public static function get($shareId) {
        $connection = \OC::$server->getDatabaseConnection();
        $select = $connection->prepare("
            SELECT id, share_id, permissions
            FROM  `*PREFIX*" . self::TableName_Key . "`
            WHERE `share_id` = ?
        ");
        $result = $select->execute([$shareId]);

        $values = $result ? $select->fetch() : [];

        return $values;
    }

    /**
     * Store extra permissions for share
     *
     * @param integer $shareId - share identifier
     * @param integer $permissions - value permissions
     *
     * @return bool
     */
    public static function set($shareId, $permissions) {
        $connection = \OC::$server->getDatabaseConnection();
        $insert = $connection->prepare("
            INSERT INTO `*PREFIX*" . self::TableName_Key . "`
                (`share_id`, `permissions`)
            VALUES (?, ?)
        ");
        return (bool)$insert->execute([$shareId, $permissions]);
    }

    /**
     * Update extra permissions for share
     *
     * @param integer $shareId - share identifier
     * @param bool $permissions - value permissions
     *
     * @return bool
     */
    public static function update($shareId, $permissions) {
        $connection = \OC::$server->getDatabaseConnection();
        $update = $connection->prepare("
            UPDATE `*PREFIX*" . self::TableName_Key . "`
            SET `permissions` = ?
            WHERE `share_id` = ?
        ");
        return (bool)$update->execute([$permissions, $shareId]);
    }
}