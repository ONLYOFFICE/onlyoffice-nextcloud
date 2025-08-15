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

use OC\Files\Filesystem;
use OC\Files\Node\File;
use OCP\Util;

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

        // Listen share deletion
        Util::connectHook("OCP\Share", "post_unshare", Hooks::class, "extraPermissionsDelete");
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

        \OCP\Log\logger('onlyoffice')->debug("Hook fileUpdate " . json_encode($params), ["app" => self::$appName]);
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

            FileVersions::deleteAllVersions($ownerId, $fileInfo);
        } catch (\Exception $e) {
            \OCP\Log\logger('onlyoffice')->error("Hook: fileDelete " . json_encode($params), ['exception' => $e]);
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
            list($filePath, $versionId) = FileVersions::splitPathVersion($pathVersion);
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

            FileVersions::deleteVersion($ownerId, $fileInfo, $versionId);
            FileVersions::deleteAuthor($ownerId, $fileInfo, $versionId);
        } catch (\Exception $e) {
            \OCP\Log\logger('onlyoffice')->error("Hook: fileVersionDelete " . json_encode($params), ['exception' => $e]);
        }
    }

    /**
     * Erase versions of restored version of file
     *
     * @param array $params - hook param
     */
    public static function fileVersionRestore($params) {
        $node = $params["node"];

        if (empty($node) || !($node instanceof File)) {
            return;
        }

        $filePath = preg_replace('/^\/\w+\/files\//', '', $params["node"]->getPath());
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

            FileVersions::deleteVersion($ownerId, $fileInfo, $versionId);
        } catch (\Exception $e) {
            \OCP\Log\logger('onlyoffice')->error("Hook: fileVersionRestore " . json_encode($params), ['exception' => $e]);
        }
    }

    /**
     * Erase extra permissions of deleted share
     *
     * @param array $params - hook param
     */
    public static function extraPermissionsDelete($params) {
        $shares = $params["deletedShares"];
        if (empty($shares)) {
            return;
        }

        try {
            $shareIds = [];
            foreach ($shares as $share) {
                array_push($shareIds, $share["id"]);
            }

            ExtraPermissions::deleteList($shareIds);
        } catch (\Exception $e) {
            \OCP\Log\logger('onlyoffice')->error("Hook: extraPermissionsDelete " . json_encode($params), ['exception' => $e]);
        }
    }
}
