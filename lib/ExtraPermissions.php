<?php
/**
 *
 * (c) Copyright Ascensio System SIA 2023
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
use OCP\Files\File;
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
    public function getExtra($shareId) {
        $share = $this->getShare($shareId);
        if (empty($share)) {
            return null;
        }

        $shareId = $share->getId();
        $extra = self::get($shareId);

        $wasInit = isset($extra["permissions"]);
        $checkExtra = $wasInit ? (int)$extra["permissions"] : self::None;
        list($availableExtra, $defaultPermissions) = $this->validation($share, $checkExtra, $wasInit);

        if ($availableExtra === 0
            || ($availableExtra & $checkExtra) !== $checkExtra) {
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

        $extra["type"] = $share->getShareType();
        $extra["shareWith"] = $share->getSharedWith();
        $extra["shareWithName"] = $share->getSharedWithDisplayName();
        $extra["available"] = $availableExtra;

        return $extra;
    }

    /**
     * Get list extra permissions by shares
     *
     * @param array $shares - array of shares
     *
     * @return array
     */
    public function getExtras($shares) {
        $result = [];

        $shareIds = [];
        foreach ($shares as $share) {
            array_push($shareIds, $share->getId());
        }

        if (empty($shareIds)) {
            return $result;
        }

        $extras = self::getList($shareIds);

        $noActualList = [];
        foreach ($shares as $share) {

            $currentExtra = [];
            foreach ($extras as $extra) {
                if ($extra["share_id"] === $share->getId()) {
                    $currentExtra = $extra;
                }
            }

            $wasInit = isset($currentExtra["permissions"]);
            $checkExtra = $wasInit ? (int)$currentExtra["permissions"] : self::None;
            list($availableExtra, $defaultPermissions) = $this->validation($share, $checkExtra, $wasInit);

            if ($availableExtra === 0
                || ($availableExtra & $checkExtra) !== $checkExtra) {
                if (!empty($currentExtra)) {
                    array_push($noActualList, $share->getId());
                    $currentExtra = [];
                }
            }

            if ($availableExtra > 0) {
                if (empty($currentExtra)) {
                    $currentExtra["id"] = -1;
                    $currentExtra["share_id"] = $share->getId();
                    $currentExtra["permissions"] = $defaultPermissions;
                }

                $currentExtra["type"] = $share->getShareType();
                $currentExtra["shareWith"] = $share->getSharedWith();
                $currentExtra["shareWithName"] = $share->getSharedWithDisplayName();
                $currentExtra["available"] = $availableExtra;

                array_push($result, $currentExtra);
            }
        }

        if (!empty($noActualList)) {
            self::deleteList($noActualList);
        }

        return $result;
    }

    /**
     * Get extra permissions by share
     *
     * @param integer $shareId - share identifier
     * @param integer $permissions - value extra permissions
     * @param integer $extraId - extra permission identifier
     *
     * @return bool
     */
    public function setExtra($shareId, $permissions, $extraId) {
        $result = false;

        $share = $this->getShare($shareId);
        if (empty($share)) {
            return $result;
        }

        list($availableExtra, $defaultPermissions) = $this->validation($share, $permissions);
        if (($availableExtra & $permissions) !== $permissions) {
            $this->logger->debug("Share " . $shareId . " does not available to extend permissions", ["app" => $this->appName]);
            return $result;
        }

        if ($extraId > 0) {
            $result = self::update($share->getId(), $permissions);
        } else {
            $result = self::insert($share->getId(), $permissions);
        }

        return $result;
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
     * Delete list extra permissions
     *
     * @param array $shareIds - array of share identifiers
     *
     * @return bool
     */
    public static function deleteList($shareIds) {
        $connection = \OC::$server->getDatabaseConnection();

        $condition = "";
        if (count($shareIds) > 1) {
            for ($i = 1; $i < count($shareIds); $i++) {
                $condition = $condition . " OR `share_id` = ?";
            }
        }

        $delete = $connection->prepare("
            DELETE FROM `*PREFIX*" . self::TableName_Key . "`
            WHERE `share_id` = ?
        " . $condition);
        return (bool)$delete->execute($shareIds);
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

        $value = is_array($values) ? $values : [];

        $result = [];
        if (!empty($value)) {
            $result = [
                "id" => (int)$value["id"],
                "share_id" => (string)$value["share_id"],
                "permissions" => (int)$value["permissions"]
            ];
        }

        return $result;
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
            for ($i = 1; $i < count($shareIds); $i++) {
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

        $result = [];
        if (is_array($values)) {
            foreach ($values as $value) {
                array_push($result, [
                    "id" => (int)$value["id"],
                    "share_id" => (string)$value["share_id"],
                    "permissions" => (int)$value["permissions"]
                ]);
            }
        }

        return $result;
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

    /**
     * Validation share on extend capability by extra permissions
     *
     * @param IShare $share - share
     * @param int $checkExtra - checkable extra permissions
     * @param bool $wasInit - was initialization extra
     *
     * @return array
     */
    private function validation($share, $checkExtra, $wasInit = true) {
        $availableExtra = self::None;
        $defaultExtra = self::None;

        if (($share->getPermissions() & Constants::PERMISSION_SHARE) === Constants::PERMISSION_SHARE) {
            return [$availableExtra, $defaultExtra];
        }

        $node = $share->getNode();
        $ext = strtolower(pathinfo($node->getName(), PATHINFO_EXTENSION));
        $format = !empty($ext) && array_key_exists($ext, $this->config->FormatsSetting()) ? $this->config->FormatsSetting()[$ext] : null;
        if (!isset($format)) {
            return [$availableExtra, $defaultExtra];
        }

        if (($share->getPermissions() & Constants::PERMISSION_UPDATE) === Constants::PERMISSION_UPDATE) {
            if (isset($format["modifyFilter"]) && $format["modifyFilter"]
                && ($checkExtra & self::Comment) !== self::Comment) {
                $availableExtra |= self::ModifyFilter;
                $defaultExtra |= self::ModifyFilter;
            }
            if (isset($format["review"]) && $format["review"]) {
                $availableExtra |= self::Review;
            }
            if (isset($format["comment"]) && $format["comment"]
                && ($checkExtra & self::Review) !== self::Review
                && (($checkExtra & self::ModifyFilter) !== self::ModifyFilter)) {
                $availableExtra |= self::Comment;
            }
            if (isset($format["fillForms"]) && $format["fillForms"]
                && ($checkExtra & self::Review) !== self::Review) {
                $availableExtra |= self::FillForms;
            }

            if (!$wasInit) {
                if (($defaultExtra & self::ModifyFilter) === self::ModifyFilter) {
                    $availableExtra ^= self::Comment;
                }
            }
        }

        return [$availableExtra, $defaultExtra];
    }

    /**
     * Get origin share
     *
     * @param integer $shareId - share identifier
     *
     * @return IShare
     */
    private function getShare($shareId) {
        try {
            $share = $this->shareManager->getShareById("ocinternal:" . $shareId);
        } catch (ShareNotFound $e) {
            $this->logger->logException($e, ["message" => "getShare error", "app" => $this->appName]);
            return null;
        }

        return $share;
    }
}