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

use OCP\Constants;
use OCP\ILogger;
use OCP\Share\IShare;
use OCP\Share\IManager;
use OCP\Share\Exceptions\ShareNotFound;

use OCA\Onlyoffice\AppConfig;

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
    private $appName;

    /**
     * Logger
     *
     * @var ILogger
     */
    private $logger;

    /**
     * Share manager
     *
     * @var IManager
     */
    private $shareManager;

    /**
     * Application configuration
     *
     * @var AppConfig
     */
    private $config;

    /**
     * Table name
     */
    private const TableName_Key = "onlyoffice_permissions";

    /**
     * Extra permission values
     *
     * @var integer
     */
    public const None = 0;
    public const Review = 1;
    public const Comment = 2;
    public const FillForms = 4;
    public const ModifyFilter = 8;

    /**
     * @param string $AppName - application name
     * @param ILogger $logger - logger
     * @param AppConfig $config - application configuration
     * @param IManager $shareManager - Share manager
     */
    public function __construct($AppName,
                                ILogger $logger,
                                IManager $shareManager,
                                AppConfig $config) {
        $this->appName = $AppName;
        $this->logger = $logger;
        $this->shareManager = $shareManager;
        $this->config = $config;
    }

    /**
     * Get extra permissions by shareId
     *
     * @param integer $shareId - share identifier
     *
     * @return array
     */
    public function getByShareId($shareId) {
        try {
            $share = $this->shareManager->getShareById("ocinternal:" . $shareId);
        } catch (ShareNotFound $e) {
            $this->logger->logException($e, ["message" => "getByShareId error", "app" => $this->appName]);
            return null;
        }

        return $this->getByShare($share);
    }

    /**
     * Get extra permissions by share
     *
     * @param IShare $shareId - share identifier
     *
     * @return array
     */
    public function getByShare($share) {
        list($available, $defaultPermissions) = $this->validation($share);

        $shareId = $share->getId();
        $extra = self::get($shareId);

        if (!$available) {
            if (!empty($extra)) {
                self::delete($shareId);
            }

            $this->logger->debug("Share " . $shareId . " does not support extra permissions", ["app" => $this->appName]);
            return null;
        }

        if (empty($extra)) {
            $extra["id"] = -1;
            $extra["share_id"] = $share->getId();
            $extra["permissions"] = $defaultPermissions;
        }

        $extra["shareWith"] = $share->getSharedWith();
        $extra["shareWithName"] = $share->getSharedWithDisplayName();
        $extra["basePermissions"] = $share->getPermissions();

        return $extra;
    }

    /**
     * Get list extra permissions by shares
     *
     * @param array $shares - array of shares
     *
     * @return array
     */
    public function getShares($shares) {
        $result = [];

        $shareIds = [];
        foreach ($shares as $share) {
            list($available, $defaultPermissions) = $this->validation($share);
            if (!$available) {
                $this->logger->debug("Share " . $shareId . " does not support extra permissions", ["app" => $this->appName]);
                continue;
            }

            array_push($result, [
                "id" => -1,
                "share_id" => $share->getId(),
                "permissions" => $defaultPermissions,
                "shareWith" => $share->getSharedWith(),
                "shareWithName" => $share->getSharedWithDisplayName(),
                "basePermissions" => $share->getPermissions()
            ]);

            array_push($shareIds, $share->getId());
        }

        if (empty($shareIds)) {
            return $result;
        }

        $extras = self::getList($shareIds);
        foreach ($extras as $extra) {
            foreach ($result as &$changeExtra) {
                if ($extra["share_id"] === $changeExtra["share_id"]) {
                    $changeExtra["id"] = $extra["id"];
                    $changeExtra["permissions"] = $extra["permissions"];
                }
            }
        }

        return $result;
    }

    /**
     * Get extra permissions by share
     *
     * @param IShare $share - share
     * @param integer $permissions - value extra permissions
     * @param integer $extraId - extra permission identifier
     *
     * @return bool
     */
    public function setShare($share, $permissions, $extraId) {
        $result = false;

        if ($extraId > 0) {
            $result = self::update($share->getId(), $permissions);
        } else {
            $result = self::insert($share->getId(), $permissions);
        }

        return $result;
    }

    /**
     * Validation share on extend capability by extra permissions
     *
     * @param IShare $share - share
     *
     * @return array
     */
    private function validation($share) {
        $node = $share->getNode();
        $fileInfo = $node->getFileInfo();

        $pathinfo = pathinfo($fileInfo->getName());
        $extension = $pathinfo["extension"];
        $format = $this->config->FormatsSetting()[$extension];

        $available = false;
        $defaultPermissions = self::None;
        if (($share->getPermissions() & Constants::PERMISSION_UPDATE) === Constants::PERMISSION_UPDATE) {
            if (isset($format["modifyFilter"]) && $format["modifyFilter"]) {
                $available = true;
                $defaultPermissions |= self::ModifyFilter;
            }
        }
        if (($share->getPermissions() & Constants::PERMISSION_UPDATE) !== Constants::PERMISSION_UPDATE) {
            if (isset($format["review"]) && $format["review"]
                || isset($format["comment"]) && $format["comment"]
                || isset($format["fillForms"]) && $format["fillForms"]) {
                $available = true;
            }
        }

        return [$available, $defaultPermissions];
    }

    /**
     * Delete extra permissions for share
     *
     * @param integer $shareId - file identifier
     *
     * @return bool
     */
    public static function delete($shareId) {
        $connection = \OC::$server->getDatabaseConnection();
        $delete = $connection->prepare("
            DELETE FROM `*PREFIX*" . self::TableName_Key . "`
            WHERE `share_id` = ?
        ");
        return (bool)$delete->execute([$shareId]);
    }

    /**
     * Get extra permissions for share
     *
     * @param integer $shareId - share identifier
     *
     * @return array
     */
    private static function get($shareId) {
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
     * Get list extra permissions
     *
     * @param array $shareIds - array of share identifiers
     *
     * @return array
     */
    private static function getList($shareIds) {
        $connection = \OC::$server->getDatabaseConnection();

        $condition = "";
        if (count($shareIds) > 1) {
            for($i = 1; $i < count($shareIds); $i++) {
                $condition = $condition . " OR `share_id` = ?";
            }
        }

        $select = $connection->prepare("
            SELECT id, share_id, permissions
            FROM  `*PREFIX*" . self::TableName_Key . "`
            WHERE `share_id` = ?
        " . $condition);

        $result = $select->execute($shareIds);

        $values = $result ? $select->fetchAll() : [];

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
    private static function insert($shareId, $permissions) {
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
    private static function update($shareId, $permissions) {
        $connection = \OC::$server->getDatabaseConnection();
        $update = $connection->prepare("
            UPDATE `*PREFIX*" . self::TableName_Key . "`
            SET `permissions` = ?
            WHERE `share_id` = ?
        ");
        return (bool)$update->execute([$permissions, $shareId]);
    }
}