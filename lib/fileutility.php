<?php
/**
 *
 * (c) Copyright Ascensio System SIA 2020
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
use OCP\ILogger;
use OCP\ISession;
use OCP\Share\IManager;

use OCA\Files_Sharing\External\Storage as SharingExternalStorage;

use OCA\Onlyoffice\AppConfig;

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
     * Session
     *
     * @var ISession
     */
    private $session;

    /**
     * Application configuration
     *
     * @var OCA\Onlyoffice\AppConfig
     */
    private $config;

    /**
     * @param string $AppName - application name
     * @param IL10N $trans - l10n service
     * @param ILogger $logger - logger
     * @param OCA\Onlyoffice\AppConfig $config - application configuration
     * @param IManager $shareManager - Share manager
     * @param IManager $ISession - Session
     */
    public function __construct($AppName,
                                IL10N $trans,
                                ILogger $logger,
                                AppConfig $config,
                                IManager $shareManager,
                                ISession $session) {
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
        list ($node, $error, $share) = $this->getNodeByToken($shareToken);

        if (isset($error)) {
            return [NULL, $error, NULL];
        }

        if ($node instanceof Folder) {
            if ($fileId !== null) {
                try {
                    $files = $node->getById($fileId);
                } catch (\Exception $e) {
                    $this->logger->error("getFileByToken: $fileId " . $e->getMessage(), array("app" => $this->appName));
                    return [NULL, $this->trans->t("Invalid request"), NULL];
                }

                if (empty($files)) {
                    $this->logger->info("Files not found: $fileId", array("app" => $this->appName));
                    return [NULL, $this->trans->t("File not found"), NULL];
                }
                $file = $files[0];
            } else {
                try {
                    $file = $node->get($path);
                } catch (\Exception $e) {
                    $this->logger->error("getFileByToken for path: $path " . $e->getMessage(), array("app" => $this->appName));
                    return [NULL, $this->trans->t("Invalid request"), NULL];
                }
            }
        } else {
            $file = $node;
        }

        return [$file, NULL, $share];
    }

    /**
     * Getting file by token
     *
     * @param string $shareToken - access token
     *
     * @return array
     */
    public function getNodeByToken($shareToken) {
        list ($share, $error) = $this->getShare($shareToken);

        if (isset($error)) {
            return [NULL, $error, NULL];
        }

        if (($share->getPermissions() & Constants::PERMISSION_READ) === 0) {
            return [NULL, $this->trans->t("You do not have enough permissions to view the file"), NULL];
        }

        try {
            $node = $share->getNode();
        } catch (NotFoundException $e) {
            $this->logger->error("getNodeByToken error: " . $e->getMessage(), array("app" => $this->appName));
            return [NULL, $this->trans->t("File not found"), NULL];
        }

        return [$node, NULL, $share];
    }

    /**
     * Getting share by token
     *
     * @param string $shareToken - access token
     *
     * @return array
     */
    private function getShare($shareToken) {
        if (empty($shareToken)) {
            return [NULL, $this->trans->t("FileId is empty")];
        }

        $share;
        try {
            $share = $this->shareManager->getShareByToken($shareToken);
        } catch (ShareNotFound $e) {
            $this->logger->error("getShare error: " . $e->getMessage(), array("app" => $this->appName));
            $share = NULL;
        }

        if ($share === NULL || $share === false) {
            return [NULL, $this->trans->t("You do not have enough permissions to view the file")];
        }

        if ($share->getPassword()
            && (!$this->session->exists("public_link_authenticated")
                || $this->session->get("public_link_authenticated") !== (string) $share->getId())) {
            return [NULL, $this->trans->t("You do not have enough permissions to view the file")];
        }

        return [$share, NULL];
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
        if ($origin
            && $file->getStorage()->instanceOfStorage(SharingExternalStorage::class)) {

            try {
                $key = $this->getFederatedKey($file);

                if (!empty($key)) {
                    return $key;
                }
            } catch (\Exception $e) {
                $this->logger->error("Failed to request federated key " . $file->getId() . " " . $e->getMessage(), array("app" => $this->appName));
            }
        }

        $instanceId = $this->config->GetSystemValue("instanceid", true);

        $key = $instanceId . "_" . $file->getEtag();

        return $key;
    }

    /**
     * Generate unique document identifier in federated share
     *
     * @param File $file - file
     *
     * @return string
     */
    private function getFederatedKey($file) {
        $remote = $file->getStorage()->getRemote();
        $shareToken = $file->getStorage()->getToken();
        $internalPath = $file->getInternalPath();

        $httpClientService = \OC::$server->getHTTPClientService();
        $client = $httpClientService->newClient();
        $response = $client->post($remote . "ocs/v2.php/apps/" . $this->appName . "/api/v1/key?format=json", [
            "timeout" => 5,
            "body" => [
                "shareToken" => $shareToken,
                "path" => $internalPath
            ]
        ]);
        $body = \json_decode($response->getBody(), true);

        $data = $body["ocs"]["data"];
        if (!empty($data["error"])) {
            $this->logger->error("Error federated key " . $data["error"], array("app" => $this->appName));
            return null;
        }

        $key = $data["key"];
        $this->logger->debug("Federated key: $key", array("app" => $this->appName));

        return $key;
    }
}
