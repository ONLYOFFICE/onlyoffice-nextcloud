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

use OCA\Files_Sharing\External\Storage as SharingExternalStorage;
use OCP\Files\File;

/**
 * Remote instance manager
 *
 * @package OCA\Onlyoffice
 */
class RemoteInstance {

    /**
     * App name
     */
    private const APP_NAME = "onlyoffice";

    /**
     * Table name
     */
    private const TABLENAME_KEY = "onlyoffice_instance";

    /**
     * Time to live of remote instance (12 hours)
     */
    private static $ttl = 60 * 60 * 1;

    /**
     * Health remote list
     */
    private static $healthRemote = [];

    /**
     * Get remote instance
     *
     * @param string $remote - remote instance
     *
     * @return array
     */
    private static function get($remote) {
        $connection = \OC::$server->getDatabaseConnection();
        $select = $connection->prepare("
            SELECT remote, expire, status
            FROM  `*PREFIX*" . self::TABLENAME_KEY . "`
            WHERE `remote` = ?
        ");
        $result = $select->execute([$remote]);

        $dbremote = $result ? $select->fetch() : [];

        return $dbremote;
    }

    /**
     * Store remote instance
     *
     * @param string $remote - remote instance
     * @param bool $status - remote status
     *
     * @return bool
     */
    private static function set($remote, $status) {
        $connection = \OC::$server->getDatabaseConnection();
        $insert = $connection->prepare("
            INSERT INTO `*PREFIX*" . self::TABLENAME_KEY . "`
                (`remote`, `status`, `expire`)
            VALUES (?, ?, ?)
        ");
        return (bool)$insert->execute([$remote, $status === true ? 1 : 0, time()]);
    }

    /**
     * Update remote instance
     *
     * @param string $remote - remote instance
     * @param bool $status - remote status
     *
     * @return bool
     */
    private static function update($remote, $status) {
        $connection = \OC::$server->getDatabaseConnection();
        $update = $connection->prepare("
            UPDATE `*PREFIX*" . self::TABLENAME_KEY . "`
            SET status = ?, expire = ?
            WHERE remote = ?
        ");
        return (bool)$update->execute([$status === true ? 1 : 0, time(), $remote]);
    }

    /**
     * Health check remote instance
     *
     * @param string $remote - remote instance
     *
     * @return bool
     */
    public static function healthCheck($remote) {
        $logger = \OCP\Log\logger('onlyoffice');
        $remote = rtrim($remote, "/") . "/";

        if (array_key_exists($remote, self::$healthRemote)) {
            $logger->debug("Remote instance " . $remote . " from local cache", ["app" => self::APP_NAME]);
            return self::$healthRemote[$remote];
        }

        $dbremote = self::get($remote);
        if (!empty($dbremote) && $dbremote["expire"] + self::$ttl > time()) {
            $logger->debug("Remote instance " . $remote . " from database status " . $dbremote["status"], ["app" => self::APP_NAME]);
            self::$healthRemote[$remote] = $dbremote["status"];
            return self::$healthRemote[$remote];
        }

        $httpClientService = \OC::$server->getHTTPClientService();
        $client = $httpClientService->newClient();

        $status = false;
        try {
            $response = $client->get($remote . "ocs/v2.php/apps/" . self::APP_NAME . "/api/v1/healthcheck?format=json");
            $body = json_decode($response->getBody(), true);

            $data = $body["ocs"]["data"];

            if (isset($data["alive"])) {
                $status = $data["alive"] === true;
            }
        } catch (\Exception $e) {
            $logger->error("Failed to request federated health check for" . $remote, ['exception' => $e]);
        }

        if (empty($dbremote)) {
            self::set($remote, $status);
        } else {
            self::update($remote, $status);
        }

        $logger->debug("Remote instance " . $remote . " was stored to database status " . $dbremote["status"], ["app" => self::APP_NAME]);

        self::$healthRemote[$remote] = $status;

        return self::$healthRemote[$remote];
    }

    /**
     * Generate unique document identifier in federated share
     *
     * @param File $file - file
     *
     * @return string
     */
    public static function getRemoteKey($file) {
        $logger = \OCP\Log\logger('onlyoffice');

        $remote = rtrim($file->getStorage()->getRemote(), "/") . "/";
        $shareToken = $file->getStorage()->getToken();
        $internalPath = $file->getInternalPath();

        $httpClientService = \OC::$server->getHTTPClientService();
        $client = $httpClientService->newClient();

        try {
            $response = $client->post($remote . "ocs/v2.php/apps/" . self::APP_NAME . "/api/v1/key?format=json", [
                "timeout" => 5,
                "body" => [
                    "shareToken" => $shareToken,
                    "path" => $internalPath
                ]
            ]);

            $body = \json_decode($response->getBody(), true);

            $data = $body["ocs"]["data"];
            if (!empty($data["error"])) {
                $logger->error("Error federated key " . $data["error"], ["app" => self::APP_NAME]);
                return null;
            }

            $key = $data["key"];
            $logger->debug("Federated key: $key", ["app" => self::APP_NAME]);

            return $key;
        } catch (\Exception $e) {
            $logger->error("Failed to request federated key " . $file->getId(), ['exception' => $e]);

            if ($e->getResponse()->getStatusCode() === 404) {
                self::update($remote, false);
                $logger->debug("Changed status for remote instance $remote to false", ["app" => self::APP_NAME]);
            }

            return null;
        }
    }

    /**
     * Change lock status in the federated share
     *
     * @param File $file - file
     * @param bool $lock - status
     * @param bool $fs - status
     *
     * @return bool
     */
    public static function lockRemoteKey($file, $lock, $fs) {
        $logger = \OCP\Log\logger('onlyoffice');
        $action = $lock ? "lock" : "unlock";

        $remote = rtrim($file->getStorage()->getRemote(), "/") . "/";
        $shareToken = $file->getStorage()->getToken();
        $internalPath = $file->getInternalPath();

        $httpClientService = \OC::$server->getHTTPClientService();
        $client = $httpClientService->newClient();
        $data = [
            "timeout" => 5,
            "body" => [
                "shareToken" => $shareToken,
                "path" => $internalPath,
                "lock" => $lock
            ]
        ];
        if (!empty($fs)) {
            $data["body"]["fs"] = $fs;
        }

        try {
            $response = $client->post($remote . "ocs/v2.php/apps/" . self::APP_NAME . "/api/v1/keylock?format=json", $data);
            $body = \json_decode($response->getBody(), true);

            $data = $body["ocs"]["data"];

            if (empty($data)) {
                $logger->debug("Federated request " . $action . " for " . $file->getFileInfo()->getId() . " is successful", ["app" => self::APP_NAME]);
                return true;
            }

            if (!empty($data["error"])) {
                $logger->error("Error " . $action . " federated key for " . $file->getFileInfo()->getId() . ": " . $data["error"], ["app" => self::APP_NAME]);
                return false;
            }
        } catch (\Exception $e) {
            $logger->error("Failed to request federated " . $action . " for " . $file->getFileInfo()->getId(), ['exception' => $e]);
            return false;
        }
    }

    /**
     * Check of federated capable
     *
     * @param File $file - file
     *
     * @return bool
     */
    public static function isRemoteFile($file) {
        $storage = $file->getStorage();

        $alive = false;
        $isFederated = $storage->instanceOfStorage(SharingExternalStorage::class);
        if (!$isFederated) {
            return false;
        }

        $alive = RemoteInstance::healthCheck($storage->getRemote());
        return $alive;
    }
}
