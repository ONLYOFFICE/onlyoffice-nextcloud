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

use OCP\Constants;
use OCP\Files\Folder;
use OCP\Files\NotFoundException;
use OCP\IL10N;
use OCP\ISession;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager;
use OCP\Share\IShare;
use Psr\Log\LoggerInterface;

/**
 * File utility
 *
 * @package OCA\Onlyoffice
 */
class FileUtility {

    /**
     * Application name
     *
     * @var string
     */
    private $appName;

    /**
     * l10n service
     *
     * @var IL10N
     */
    private $trans;

    /**
     * Logger
     *
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Share manager
     *
     * @var IManager
     */
    private $shareManager;

    /**
     * Session
     *
     * @var ISession
     */
    private $session;

    /**
     * Application configuration
     *
     * @var AppConfig
     */
    private $config;

    /**
     * @param string $AppName - application name
     * @param IL10N $trans - l10n service
     * @param LoggerInterface $logger - logger
     * @param AppConfig $config - application configuration
     * @param IManager $shareManager - Share manager
     * @param IManager $ISession - Session
     */
    public function __construct(
        $AppName,
        IL10N $trans,
        LoggerInterface $logger,
        AppConfig $config,
        IManager $shareManager,
        ISession $session
    ) {
        $this->appName = $AppName;
        $this->trans = $trans;
        $this->logger = $logger;
        $this->config = $config;
        $this->shareManager = $shareManager;
        $this->session = $session;
    }

    /**
     * Getting file by token
     *
     * @param integer $fileId - file identifier
     * @param string $shareToken - access token
     * @param string $path - file path
     *
     * @return array
     */
    public function getFileByToken($fileId, $shareToken, $path = null) {
        list($node, $error, $share) = $this->getNodeByToken($shareToken);

        if (isset($error)) {
            return [null, $error, null];
        }

        if ($node instanceof Folder) {
            if ($fileId !== null && $fileId !== 0) {
                try {
                    $files = $node->getById($fileId);
                } catch (\Exception $e) {
                    $this->logger->error("getFileByToken: $fileId", ["exception" => $e]);
                    return [null, $this->trans->t("Invalid request"), null];
                }

                if (empty($files)) {
                    $this->logger->info("Files not found: $fileId");
                    return [null, $this->trans->t("File not found"), null];
                }
                $file = $files[0];
            } else {
                try {
                    $file = $node->get($path);
                } catch (\Exception $e) {
                    $this->logger->error("getFileByToken for path: $path", ["exception" => $e]);
                    return [null, $this->trans->t("Invalid request"), null];
                }
            }
        } else {
            $file = $node;
        }

        return [$file, null, $share];
    }

    /**
     * Getting file by token
     *
     * @param string $shareToken - access token
     *
     * @return array
     */
    public function getNodeByToken($shareToken) {
        list($share, $error) = $this->getShare($shareToken);

        if (isset($error)) {
            return [null, $error, null];
        }

        if (($share->getPermissions() & Constants::PERMISSION_READ) === 0) {
            return [null, $this->trans->t("You do not have enough permissions to view the file"), null];
        }

        try {
            $node = $share->getNode();
        } catch (NotFoundException $e) {
            $this->logger->error("getNodeByToken error", ["exception" => $e]);
            return [null, $this->trans->t("File not found"), null];
        }

        return [$node, null, $share];
    }

    /**
     * Getting share by token
     *
     * @param string $shareToken - access token
     *
     * @return array
     */
    public function getShare($shareToken) {
        if (empty($shareToken)) {
            return [null, $this->trans->t("FileId is empty")];
        }

        try {
            $share = $this->shareManager->getShareByToken($shareToken);
        } catch (ShareNotFound $e) {
            $this->logger->error("getShare error", ["exception" => $e]);
            $share = null;
        }

        if ($share === null || $share === false) {
            return [null, $this->trans->t("You do not have enough permissions to view the file")];
        }

        if ($share->getPassword()
            && (!$this->session->exists("public_link_authenticated")
                || $this->session->get("public_link_authenticated") !== (string) $share->getId())) {
            return [null, $this->trans->t("You do not have enough permissions to view the file")];
        }

        return [$share, null];
    }

    /**
     * Generate unique document identifier
     *
     * @param File $file - file
     * @param bool $origin - request from federated store
     *
     * @return string
     */
    public function getKey($file, $origin = false) {
        $fileId = $file->getId();

        if ($origin
            && RemoteInstance::isRemoteFile($file)) {
            $key = RemoteInstance::getRemoteKey($file);
            if (!empty($key)) {
                return $key;
            }
        }

        $key = KeyManager::get($fileId);

        if (empty($key)) {
            $instanceId = $this->config->getSystemValue("instanceid", true);

            $key = $instanceId . "_" . $this->GUID();

            KeyManager::set($fileId, $key);
        }

        return $key;
    }

    /**
     * Generate unique identifier
     *
     * @return string
     */
    private function GUID() {
        if (function_exists("com_create_guid") === true) {
            return trim(com_create_guid(), "{}");
        }

        return sprintf('%04X%04X-%04X-%04X-%04X-%04X%04X%04X', mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(16384, 20479), mt_rand(32768, 49151), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535));
    }

    /**
     * Generate unique file version key
     *
     * @param OCA\Files_Versions\Versions\IVersion $version - file version
     *
     * @return string
     */
    public function getVersionKey($version) {
        $instanceId = $this->config->getSystemValue("instanceid", true);

        $key = $instanceId . "_" . $version->getSourceFile()->getEtag() . "_" . $version->getRevisionId();

        return $key;
    }

    /**
     * The method checks download permission
     *
     * @param IShare $share - share object
     *
     * @return bool
     */
    public static function canShareDownload($share) {
        $can = true;

        $downloadAttribute = self::getShareAttrubute($share, "download");
        if (isset($downloadAttribute)) {
            $can = $downloadAttribute;
        }

        return $can;
    }

    /**
     * The method extracts share attribute
     *
     * @param IShare $share - share object
     * @param string $attribute - attribute name
     *
     * @return bool|null
     */
    private static function getShareAttrubute($share, $attribute) {
        $attributes = null;
        if (method_exists(IShare::class, "getAttributes")) {
            $attributes = $share->getAttributes();
        }

        $attribute = isset($attributes) ? $attributes->getAttribute("permissions", $attribute) : null;

        return $attribute;
    }
}
