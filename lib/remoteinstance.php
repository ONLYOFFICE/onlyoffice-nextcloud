<?php
/**
 *
 * (c) Copyright Ascensio System SIA 2021
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
 * Remote instance manager
 *
 * @package OCA\Onlyoffice
 */
class RemoteInstance {

    /**
     * App name
     */
    private const App_Name = "onlyoffice";

    /**
     * Table name
     */
    private const TableName_Key = "onlyoffice_instance";

    /**
     * Time to live of remote instance (12 hours)
     */
    private static $ttl = 60 * 60 * 12;

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
            FROM  `*PREFIX*" . self::TableName_Key . "`
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
            INSERT INTO `*PREFIX*" . self::TableName_Key . "`
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
            UPDATE `*PREFIX*" . self::TableName_Key . "`
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
        $logger = \OC::$server->getLogger();
        $remote = rtrim($remote, "/") . "/";

        if (array_key_exists($remote, self::$healthRemote)) {
            $logger->debug("Remote instance " . $remote . " from local cache", ["app" => self::App_Name]);
            return self::$healthRemote[$remote];
        }

        $dbremote = self::get($remote);
        if (!empty($dbremote) && $dbremote["expire"] + self::$ttl > time()) {
            $logger->debug("Remote instance " . $remote . " from database status " . $dbremote["status"], ["app" => self::App_Name]);
            self::$healthRemote[$remote] = $dbremote["status"];
            return self::$healthRemote[$remote];
        }

        $httpClientService = \OC::$server->getHTTPClientService();
        $client = $httpClientService->newClient();

        $status = false;
        try {
            $response = $client->get($remote . "ocs/v2.php/apps/" . self::App_Name . "/api/v1/healthcheck?format=json");
            $body = json_decode($response->getBody(), true);

            $data = $body["ocs"]["data"];

            if (isset($data["alive"])) {
                $status = $data["alive"] === true;
            }

        } catch (\Exception $e) {
            $logger->logException($e, ["message" => "Failed to request federated health check for" . $remote, "app" => self::App_Name]);
        }

        if (empty($dbremote)) {
            self::set($remote, $status);
        } else {
            self::update($remote, $status);
        }

        $logger->debug("Remote instance " . $remote . " was stored to database status " . $dbremote["status"], ["app" => self::App_Name]);

        self::$healthRemote[$remote] = $status;

        return self::$healthRemote[$remote];
    }
}