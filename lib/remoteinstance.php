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
    private const App_Name = "onlyoffice";

    /**
     * Health remote list
     */
    private static $healthRemote = [];

    /**
     * Health check remote instance
     *
     * @param string $remote - remote instance
     *
     * @return bool
     */
    public static function healthCheck($remote) {
        $remote = rtrim($remote, "/") . "/";

        if (in_array($remote, self::$healthRemote)) {
            return true;
        }

        $httpClientService = \OC::$server->getHTTPClientService();
        $client = $httpClientService->newClient();

        try {
            $response = $client->get($remote . "ocs/v2.php/apps/" . self::App_Name . "/api/v1/healthcheck?format=json");
            $body = json_decode($response->getBody(), true);

            $data = $body["ocs"]["data"];
            if ($data["alive"]) {
                array_push(self::$healthRemote, $remote);
                return true;
            }
        } catch (\Exception $e) {
            \OC::$server->getLogger()->logException($e, ["message" => "Failed to request federated health check for" . $remote, "app" => self::App_Name]);
        }

        return false;
    }
}