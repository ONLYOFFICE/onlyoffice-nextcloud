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

use OC\Files\Filesystem;

use OCP\Util;

use OCA\Onlyoffice\FileVersions;
use OCA\Onlyoffice\KeyManager;

/**
 * The class to handle the filesystem hooks
 *
 * @package OCA\Onlyoffice
 */
class Hooks {

    /**
     * Application name
     *
     * @var string
     */
    private static $appName = "onlyoffice";

    public static function connectHooks() {
        // Listen user deletion
        Util::connectHook("OC_User", "pre_deleteUser", Hooks::class, "userDelete");

        // Listen file change
        Util::connectHook("OC_Filesystem", "write", Hooks::class, "fileUpdate");

        // Listen file deletion
        Util::connectHook("OC_Filesystem", "delete", Hooks::class, "fileDelete");

        // Listen file version deletion
        Util::connectHook("\OCP\Versions", "preDelete", Hooks::class, "fileVersionDelete");

        // Listen file version restore
        Util::connectHook("\OCP\Versions", "rollback", Hooks::class, "fileVersionRestore");
    }

    /**
     * Erase user file versions
     *
     * @param array $params - hook params
     */
    public static function userDelete($params) {
        $userId = $params["uid"];

        FileVersions::deleteAllVersions($userId);
    }

    /**
     * Listen of file change
     *
     * @param array $params - hook params
     */
    public static function fileUpdate($params) {
        $filePath = $params[Filesystem::signal_param_path];
        if (empty($filePath)) {
            return;
        }

        $fileInfo = Filesystem::getFileInfo($filePath);
        if ($fileInfo === false) {
            return;
        }

        $fileId = $fileInfo->getId();

        KeyManager::delete($fileId);

        \OC::$server->getLogger()->debug("Hook fileUpdate " . json_encode($params), ["app" => self::$appName]);
    }

    /**
     * Erase versions of deleted file
     *
     * @param array $params - hook params
     */
    public static function fileDelete($params) {
        $filePath = $params[Filesystem::signal_param_path];
        if (empty($filePath)) {
            return;
        }

        try {
            $fileInfo = Filesystem::getFileInfo($filePath);
            if ($fileInfo === false) {
                return;
            }

            $owner = $fileInfo->getOwner();
            if (empty($owner)) {
                return;
            }
            $ownerId = $owner->getUID();

            $fileId = $fileInfo->getId();

            KeyManager::delete($fileId, true);

            FileVersions::deleteAllVersions($ownerId, $fileId);
        } catch (\Exception $e) {
            \OC::$server->getLogger()->logException($e, ["message" => "Hook: fileDelete " . json_encode($params), "app" => self::$appName]);
        }
    }

    /**
     * Erase versions of deleted version of file
     *
     * @param array $params - hook param
     */
    public static function fileVersionDelete($params) {
        $pathVersion = $params["path"];
        if (empty($pathVersion)) {
            return;
        }

        try {
            list ($filePath, $versionId) = FileVersions::splitPathVersion($pathVersion);
            if (empty($filePath)) {
                return;
            }
            $fileInfo = Filesystem::getFileInfo($filePath);
            if ($fileInfo === false) {
                return;
            }

            $owner = $fileInfo->getOwner();
            if (empty($owner)) {
                return;
            }
            $ownerId = $owner->getUID();

            $fileId = $fileInfo->getId();

            FileVersions::deleteVersion($ownerId, $fileId, $versionId);
            FileVersions::deleteAuthor($ownerId, $fileId, $versionId);
        } catch (\Exception $e) {
            \OC::$server->getLogger()->logException($e, ["message" => "Hook: fileVersionDelete " . json_encode($params), "app" => self::$appName]);
        }
    }

    /**
     * Erase versions of restored version of file
     *
     * @param array $params - hook param
     */
    public static function fileVersionRestore($params) {
        $filePath = $params["path"];
        if (empty($filePath)) {
            return;
        }

        $versionId = $params["revision"];

        try {
            $fileInfo = Filesystem::getFileInfo($filePath);
            if ($fileInfo === false) {
                return;
            }

            $owner = $fileInfo->getOwner();
            if (empty($owner)) {
                return;
            }
            $ownerId = $owner->getUID();

            $fileId = $fileInfo->getId();

            KeyManager::delete($fileId);

            FileVersions::deleteVersion($ownerId, $fileId, $versionId);
        } catch (\Exception $e) {
            \OC::$server->getLogger()->logException($e, ["message" => "Hook: fileVersionRestore " . json_encode($params), "app" => self::$appName]);
        }
    }
}
