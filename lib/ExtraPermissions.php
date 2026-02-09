<?php
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

namespace OCA\Onlyoffice;

use OCA\Talk\Manager as TalkManager;
use OCP\Constants;
use OCP\IDBConnection;
use OCP\Server;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager;
use OCP\Share\IShare;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;

/**
 * Class expands base permissions
 *
 * @package OCA\Onlyoffice
 */
class ExtraPermissions {

    /**
     * Talk manager
     *
     * @var TalkManager
     */
    private $talkManager;

    /**
     * Table name
     */
    private const TABLENAME_KEY = "onlyoffice_permissions";

    /**
     * Extra permission values
     *
     * @var integer
     */
    public const NONE = 0;
    public const REVIEW = 1;
    public const COMMENT = 2;
    public const FILLFORMS = 4;
    public const MODIFYFILTER = 8;

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly IManager $shareManager,
        private readonly AppConfig $appConfig
    ) {
        if (Server::get(\OCP\App\IAppManager::class)->isInstalled("spreed")) {
            try {
                $this->talkManager = Server::get(Talkmanager::class);
            } catch (NotFoundExceptionInterface $e) {
                $this->logger->error("TalkManager init error", ["exception" => $e]);
            }
        }
    }

    /**
     * Get extra permissions by shareId
     *
     * @param integer $shareId - share identifier
     *
     * @return array
     */
    public function getExtra($shareId): ?array {
        $share = $this->getShare($shareId);
        if (!$share instanceof IShare) {
            return null;
        }

        $shareId = $share->getId();
        $extra = $this->get($shareId);

        $wasInit = isset($extra["permissions"]);
        $checkExtra = $wasInit ? (int)$extra["permissions"] : self::NONE;
        [$availableExtra, $defaultPermissions] = $this->validation($share, $checkExtra, $wasInit);

        if ($availableExtra === 0
            || ($availableExtra & $checkExtra) !== $checkExtra) {
            if (!empty($extra)) {
                self::delete($shareId);
            }

            $this->logger->debug("Share " . $shareId . " does not support extra permissions");
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
     */
    public function getExtras($shares): array {
        $result = [];

        $shareIds = [];
        foreach ($shares as $share) {
            $shareIds[] = $share->getId();
        }

        if (empty($shareIds)) {
            return $result;
        }

        $extras = $this->getList($shareIds);

        $noActualList = [];
        foreach ($shares as $share) {
            $currentExtra = [];
            foreach ($extras as $extra) {
                if ($extra["share_id"] === $share->getId()) {
                    $currentExtra = $extra;
                }
            }

            $wasInit = isset($currentExtra["permissions"]);
            $checkExtra = $wasInit ? (int)$currentExtra["permissions"] : self::NONE;
            [$availableExtra, $defaultPermissions] = $this->validation($share, $checkExtra, $wasInit);

            if (($availableExtra === 0 || ($availableExtra & $checkExtra) !== $checkExtra) && !empty($currentExtra)) {
                $noActualList[] = $share->getId();
                $currentExtra = [];
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

                if ($currentExtra["type"] === IShare::TYPE_ROOM && $this->talkManager !== null) {
                    $rooms = $this->talkManager->searchRoomsByToken($currentExtra["shareWith"]);
                    if (!empty($rooms)) {
                        $room = $rooms[0];

                        $currentExtra["shareWith"] = $room->getName();
                        $currentExtra["shareWithName"] = $room->getName();
                    }
                }

                $result[] = $currentExtra;
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
    public function setExtra(string $shareId, $permissions, $extraId) {
        $result = false;

        $share = $this->getShare($shareId);
        if (!$share instanceof IShare) {
            return $result;
        }

        [$availableExtra, $defaultPermissions] = $this->validation($share, $permissions);
        if (($availableExtra & $permissions) !== $permissions) {
            $this->logger->debug("Share " . $shareId . " does not available to extend permissions");
            return $result;
        }

        $result = $extraId > 0 ? $this->update($share->getId(), $permissions) : $this->insert($share->getId(), $permissions);

        return $result;
    }

    /**
     * Delete extra permissions for share
     *
     * @param integer $shareId - file identifier
     */
    public static function delete($shareId): bool {
        $connection = Server::get(IDBConnection::class);
        $delete = $connection->prepare("
            DELETE FROM `*PREFIX*" . self::TABLENAME_KEY . "`
            WHERE `share_id` = ?
        ");
        return (bool)$delete->execute([$shareId]);
    }

    /**
     * Delete list extra permissions
     *
     * @param array $shareIds - array of share identifiers
     */
    public static function deleteList($shareIds): bool {
        $connection = Server::get(IDBConnection::class);

        $condition = "";
        if (count($shareIds) > 1) {
            $counter = count($shareIds);
            for ($i = 1; $i < $counter; $i++) {
                $condition .= " OR `share_id` = ?";
            }
        }

        $delete = $connection->prepare("
            DELETE FROM `*PREFIX*" . self::TABLENAME_KEY . "`
            WHERE `share_id` = ?
        " . $condition);
        return (bool)$delete->execute($shareIds);
    }

    /**
     * Get extra permissions for share
     *
     * @param integer $shareId - share identifier
     */
    private function get($shareId): array {
        $connection = Server::get(IDBConnection::class);
        $select = $connection->prepare("
            SELECT id, share_id, permissions
            FROM  `*PREFIX*" . self::TABLENAME_KEY . "`
            WHERE `share_id` = ?
        ");
        $result = $select->execute([$shareId]);
        $values = $result->fetch();
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
    private function getList(array $shareIds) {
        $connection = Server::get(IDBConnection::class);

        $condition = "";
        if (count($shareIds) > 1) {
            $counter = count($shareIds);
            for ($i = 1; $i < $counter; $i++) {
                $condition .= " OR `share_id` = ?";
            }
        }

        $select = $connection->prepare("
            SELECT id, share_id, permissions
            FROM  `*PREFIX*" . self::TABLENAME_KEY . "`
            WHERE `share_id` = ?
        " . $condition);

        $result = $select->execute($shareIds);
        $values = $result->fetchAll();

        $result = [];
        if (is_array($values)) {
            foreach ($values as $value) {
                $result[] = [
                    "id" => (int)$value["id"],
                    "share_id" => (string)$value["share_id"],
                    "permissions" => (int)$value["permissions"]
                ];
            }
        }

        return $result;
    }

    /**
     * Store extra permissions for share
     *
     * @param integer $shareId - share identifier
     * @param integer $permissions - value permissions
     */
    private function insert($shareId, int $permissions): bool {
        $connection = Server::get(IDBConnection::class);
        $insert = $connection->prepare("
            INSERT INTO `*PREFIX*" . self::TABLENAME_KEY . "`
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
     */
    private function update($shareId, int $permissions): bool {
        $connection = Server::get(IDBConnection::class);
        $update = $connection->prepare("
            UPDATE `*PREFIX*" . self::TABLENAME_KEY . "`
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
     */
    private function validation($share, $checkExtra, bool $wasInit = true): array {
        $availableExtra = self::NONE;
        $defaultExtra = self::NONE;

        if ($share->getShareType() !== IShare::TYPE_LINK
            && ($share->getPermissions() & Constants::PERMISSION_SHARE) === Constants::PERMISSION_SHARE) {
            return [$availableExtra, $defaultExtra];
        }

        $node = $share->getNode();
        $ext = strtolower(pathinfo((string) $node->getName(), PATHINFO_EXTENSION));
        $format = !empty($ext) && array_key_exists($ext, $this->appConfig->formatsSetting()) ? $this->appConfig->formatsSetting()[$ext] : null;
        if (!isset($format)) {
            return [$availableExtra, $defaultExtra];
        }

        if (($share->getPermissions() & Constants::PERMISSION_UPDATE) === Constants::PERMISSION_UPDATE) {
            if (isset($format["modifyFilter"]) && $format["modifyFilter"]
                && ($checkExtra & self::COMMENT) !== self::COMMENT) {
                $availableExtra |= self::MODIFYFILTER;
                $defaultExtra |= self::MODIFYFILTER;
            }
            if (isset($format["review"]) && $format["review"]) {
                $availableExtra |= self::REVIEW;
            }
            if (isset($format["comment"]) && $format["comment"]
                && ($checkExtra & self::REVIEW) !== self::REVIEW
                && (($checkExtra & self::MODIFYFILTER) !== self::MODIFYFILTER)) {
                $availableExtra |= self::COMMENT;
            }
            if (isset($format["fillForms"]) && $format["fillForms"]
                && ($checkExtra & self::REVIEW) !== self::REVIEW) {
                $availableExtra |= self::FILLFORMS;
            }

            if (!$wasInit && ($defaultExtra & self::MODIFYFILTER) === self::MODIFYFILTER) {
                $availableExtra ^= self::COMMENT;
            }
        }

        return [$availableExtra, $defaultExtra];
    }

    /**
     * Get origin share
     */
    private function getShare(string $shareId): ?IShare {
        try {
            return $this->shareManager->getShareById("ocinternal:" . $shareId);
        } catch (ShareNotFound) {}

        try {
            return $this->shareManager->getShareById("ocRoomShare:" . $shareId);
        } catch (ShareNotFound) {}

        $this->logger->error("getShare: share not found: " . $shareId);

        return null;
    }
}
