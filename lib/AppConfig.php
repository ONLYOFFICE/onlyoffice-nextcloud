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

use \DateInterval;
use \DateTime;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IConfig;
use Psr\Log\LoggerInterface;
use OCA\Onlyoffice\AppInfo\Application;

/**
 * Application configutarion
 *
 * @package OCA\Onlyoffice
 */
class AppConfig {
    /**
     * The config key for the demo server
     *
     * @var string
     */
    private $_demo = "demo";

    /**
     * The config key for the document server address
     *
     * @var string
     */
    private $_documentserver = "DocumentServerUrl";

    /**
     * The config key for the document server address available from Nextcloud
     *
     * @var string
     */
    private $_documentserverInternal = "DocumentServerInternalUrl";

    /**
     * The config key for the Nextcloud address available from document server
     *
     * @var string
     */
    private $_storageUrl = "StorageUrl";

    /**
     * The config key for the secret key
     *
     * @var string
     */
    private $_cryptSecret = "secret";

    /**
     * The config key for the default formats
     *
     * @var string
     */
    private $_defFormats = "defFormats";

    /**
     * The config key for the editable formats
     *
     * @var string
     */
    private $_editFormats = "editFormats";

    /**
     * The config key for the setting same tab
     *
     * @var string
     */
    private $_sameTab = "sameTab";

    /**
     * The config key for the enabling sharring in a same tab
     *
     * @var string
     */
    private $_enableSharing = "enableSharing";

    /**
     * The config key for the generate preview
     *
     * @var string
     */
    private $_preview = "preview";

    /**
     * The config key for the advanced
     *
     * @var string
     */
    private $_advanced = "advanced";

    /**
     * The config key for the cronChecker
     *
     * @var string
     */
    private $_cronChecker = "cronChecker";

    /**
     * The config key for the e-mail notifications
     *
     * @var string
     */
    private $_emailNotifications = "emailNotifications";

    /**
     * The config key for the keep versions history
     *
     * @var string
     */
    private $_versionHistory = "versionHistory";

    /**
     * The config key for the protection
     *
     * @var string
     */
    private $_protection = "protection";

    /**
     * The config key for the chat display setting
     *
     * @var string
     */
    private $_customizationChat = "customizationChat";

    /**
     * The config key for display the header more compact setting
     *
     * @var string
     */
    private $_customizationCompactHeader = "customizationCompactHeader";

    /**
     * The config key for the feedback display setting
     *
     * @var string
     */
    private $_customizationFeedback = "customizationFeedback";

    /**
     * The config key for the forcesave setting
     *
     * @var string
     */
    private $_customizationForcesave = "customizationForcesave";

    /**
     * The config key for the live view on share setting
     *
     * @var string
     */
    private $_liveViewOnShare = "liveViewOnShare";

    /**
     * The config key for the help display setting
     *
     * @var string
     */
    private $_customizationHelp = "customizationHelp";

    /**
     * The config key for the review mode setting
     *
     * @var string
     */
    private $_customizationReviewDisplay = "customizationReviewDisplay";

    /**
     * The config key for the theme setting
     *
     * @var string
     */
    private $_customizationTheme = "customizationTheme";

    /**
     * Display name of the unknown author
     *
     * @var string
     */
    private $_unknownAuthor = "unknownAuthor";

    /**
     * The config key for the setting limit groups
     *
     * @var string
     */
    private $_groups = "groups";

    /**
     * The config key for the verification
     *
     * @var string
     */
    private $_verification = "verify_peer_off";

    /**
     * The config key for the secret key in jwt
     *
     * @var string
     */
    private $_jwtSecret = "jwt_secret";

    /**
     * The config key for the jwt header
     *
     * @var string
     */
    private $_jwtHeader = "jwt_header";

    /**
     * The config key for the allowable leeway in Jwt checks
     *
     * @var string
     */
    private $_jwtLeeway = "jwt_leeway";

    /**
     * The config key for the settings error
     *
     * @var string
     */
    private $_settingsError = "settings_error";

    /**
     * Application name for watermark settings
     *
     * @var string
     */
    public const WATERMARK_APP_NAMESPACE = "files";

    /**
     * The config key for limit thumbnail size
     *
     * @var string
     */
    public $_limitThumbSize = "limit_thumb_size";

    /**
     * The config key for the modifyFilter
     *
     * @var string
     */
    public $_permissions_modifyFilter = "permissions_modifyFilter";

    /**
     * The config key for the customer
     *
     * @var string
     */
    public $_customization_customer = "customization_customer";

    /**
     * The config key for the loaderLogo
     *
     * @var string
     */
    public $_customization_loaderLogo = "customization_loaderLogo";

    /**
     * The config key for the loaderName
     *
     * @var string
     */
    public $_customization_loaderName = "customization_loaderName";

    /**
     * The config key for the logo
     *
     * @var string
     */
    public $_customization_logo = "customization_logo";

    /**
     * The config key for the zoom
     *
     * @var string
     */
    public $_customization_zoom = "customization_zoom";

    /**
     * The config key for the autosave
     *
     * @var string
     */
    public $_customization_autosave = "customization_autosave";

    /**
     * The config key for the macros
     *
     * @var string
     */
    public $_customizationMacros = "customization_macros";

    /**
     * The config key for the plugins
     *
     * @var string
     */
    public $_customizationPlugins = "customization_plugins";

    /**
     * The config key for the disable downloading
     *
     * @var string
     */
    public $_disableDownload = "disable_download";

    /**
     * The config key for the interval of editors availability check by cron
     *
     * @var string
     */
    private $_editors_check_interval = "editors_check_interval";

    /**
     * The config key for the JWT expiration
     *
     * @var string
     */
    private $_jwt_expiration = "jwt_expiration";

    /**
     * The config key for store cache
     */
    private ICache $cache;

    public function __construct(
        private string $appName,
        private IConfig $config,
        private LoggerInterface $logger,
        ICacheFactory $cacheFactory,
    ) {
        $this->cache = $cacheFactory->createLocal(Application::APP_ID);
    }

    /**
     * Get value from the system configuration
     *
     * @param string $key - key configuration
     * @param bool $system - get from root or from app section
     *
     * @return string
     */
    public function getSystemValue($key, $system = false) {
        if ($system) {
            return $this->config->getSystemValue($key);
        }
        if (!empty($this->config->getSystemValue($this->appName))
            && array_key_exists($key, $this->config->getSystemValue($this->appName))) {
            return $this->config->getSystemValue($this->appName)[$key];
        }
        return null;
    }

    /**
     * Switch on demo server
     *
     * @param bool $value - select demo
     *
     * @return bool
     */
    public function selectDemo($value) {
        $this->logger->info("Select demo: " . json_encode($value), ["app" => $this->appName]);

        $data = $this->getDemoData();

        if ($value === true && !$data["available"]) {
            $this->logger->info("Trial demo is overdue: " . json_encode($data), ["app" => $this->appName]);
            return false;
        }

        $data["enabled"] = $value === true;
        if (!isset($data["start"])) {
            $data["start"] = new DateTime();
        }

        $this->config->setAppValue($this->appName, $this->_demo, json_encode($data));
        return true;
    }

    /**
     * Get demo data
     *
     * @return array
     */
    public function getDemoData() {
        $data = $this->config->getAppValue($this->appName, $this->_demo, "");

        if (empty($data)) {
            return [
                "available" => true,
                "enabled" => false
            ];
        }
        $data = json_decode($data, true);

        $overdue = new DateTime(isset($data["start"]) ? $data["start"]["date"] : null);
        $overdue->add(new DateInterval("P" . $this->DEMO_PARAM["TRIAL"] . "D"));
        if ($overdue > new DateTime()) {
            $data["available"] = true;
            $data["enabled"] = $data["enabled"] === true;
        } else {
            $data["available"] = false;
            $data["enabled"] = false;
        }

        return $data;
    }

    /**
     * Get status of demo server
     *
     * @return bool
     */
    public function useDemo() {
        return $this->getDemoData()["enabled"] === true;
    }

    /**
     * Save the document service address to the application configuration
     *
     * @param string $documentServer - document service address
     */
    public function setDocumentServerUrl($documentServer) {
        $documentServer = trim($documentServer);
        if (strlen($documentServer) > 0) {
            $documentServer = rtrim($documentServer, "/") . "/";
            if (!preg_match("/(^https?:\/\/)|^\//i", $documentServer)) {
                $documentServer = "http://" . $documentServer;
            }
        }

        $this->logger->info("setDocumentServerUrl: $documentServer", ["app" => $this->appName]);

        $this->config->setAppValue($this->appName, $this->_documentserver, $documentServer);
    }

    /**
     * Get the document service address from the application configuration
     *
     * @param bool $origin - take origin
     *
     * @return string
     */
    public function getDocumentServerUrl($origin = false) {
        if (!$origin && $this->useDemo()) {
            return $this->DEMO_PARAM["ADDR"];
        }

        $url = $this->config->getAppValue($this->appName, $this->_documentserver, "");
        if (empty($url)) {
            $url = $this->getSystemValue($this->_documentserver);
        }
        if ($url !== null && $url !== "/") {
            $url = rtrim($url, "/");
            if (strlen($url) > 0) {
                $url = $url . "/";
            }
        }
        return $url;
    }

    /**
     * Save the document service address available from Nextcloud to the application configuration
     *
     * @param string $documentServerInternal - document service address
     */
    public function setDocumentServerInternalUrl($documentServerInternal) {
        $documentServerInternal = rtrim(trim($documentServerInternal), "/");
        if (strlen($documentServerInternal) > 0) {
            $documentServerInternal = $documentServerInternal . "/";
            if (!preg_match("/^https?:\/\//i", $documentServerInternal)) {
                $documentServerInternal = "http://" . $documentServerInternal;
            }
        }

        $this->logger->info("setDocumentServerInternalUrl: $documentServerInternal", ["app" => $this->appName]);

        $this->config->setAppValue($this->appName, $this->_documentserverInternal, $documentServerInternal);
    }

    /**
     * Get the document service address available from Nextcloud from the application configuration
     *
     * @param bool $origin - take origin
     *
     * @return string
     */
    public function getDocumentServerInternalUrl($origin = false) {
        if (!$origin && $this->useDemo()) {
            return $this->getDocumentServerUrl();
        }

        $url = $this->config->getAppValue($this->appName, $this->_documentserverInternal, "");
        if (empty($url)) {
            $url = $this->getSystemValue($this->_documentserverInternal);
        }
        if (!$origin && empty($url)) {
            $url = $this->getDocumentServerUrl();
        }
        return $url;
    }

    /**
     * Replace domain in document server url with internal address from configuration
     *
     * @param string $url - document server url
     *
     * @return string
     */
    public function replaceDocumentServerUrlToInternal($url) {
        $documentServerUrl = $this->getDocumentServerInternalUrl();
        if (!empty($documentServerUrl)) {
            $from = $this->getDocumentServerUrl();

            if (!preg_match("/^https?:\/\//i", $from)) {
                $parsedUrl = parse_url($url);
                $from = $parsedUrl["scheme"] . "://" . $parsedUrl["host"] . (array_key_exists("port", $parsedUrl) ? (":" . $parsedUrl["port"]) : "") . $from;
            }

            if ($from !== $documentServerUrl) {
                $this->logger->debug("Replace url from $from to $documentServerUrl", ["app" => $this->appName]);
                $url = str_replace($from, $documentServerUrl, $url);
            }
        }

        return $url;
    }

    /**
     * Save the Nextcloud address available from document server to the application configuration
     *
     * @param string $documentServer - document service address
     */
    public function setStorageUrl($storageUrl) {
        $storageUrl = rtrim(trim($storageUrl), "/");
        if (strlen($storageUrl) > 0) {
            $storageUrl = $storageUrl . "/";
            if (!preg_match("/^https?:\/\//i", $storageUrl)) {
                $storageUrl = "http://" . $storageUrl;
            }
        }

        $this->logger->info("setStorageUrl: $storageUrl", ["app" => $this->appName]);

        $this->config->setAppValue($this->appName, $this->_storageUrl, $storageUrl);
    }

    /**
     * Get the Nextcloud address available from document server from the application configuration
     *
     * @return string
     */
    public function getStorageUrl() {
        $url = $this->config->getAppValue($this->appName, $this->_storageUrl, "");
        if (empty($url)) {
            $url = $this->getSystemValue($this->_storageUrl);
        }
        return $url;
    }

    /**
     * Save the document service secret key to the application configuration
     *
     * @param string $secret - secret key
     */
    public function setDocumentServerSecret($secret) {
        $secret = trim($secret);
        if (empty($secret)) {
            $this->logger->info("Clear secret key", ["app" => $this->appName]);
        } else {
            $this->logger->info("Set secret key", ["app" => $this->appName]);
        }

        $this->config->setAppValue($this->appName, $this->_jwtSecret, $secret);
    }

    /**
     * Get the document service secret key from the application configuration
     *
     * @param bool $origin - take origin
     *
     * @return string
     */
    public function getDocumentServerSecret($origin = false) {
        if (!$origin && $this->useDemo()) {
            return $this->DEMO_PARAM["SECRET"];
        }

        $secret = $this->config->getAppValue($this->appName, $this->_jwtSecret, "");
        if (empty($secret)) {
            $secret = $this->getSystemValue($this->_jwtSecret);
        }
        return $secret;
    }

    /**
     * Get the secret key from the application configuration
     *
     * @return string
     */
    public function getSKey() {
        $secret = $this->getDocumentServerSecret();
        if (empty($secret)) {
            $secret = $this->getSystemValue($this->_cryptSecret, true);
        }
        return $secret;
    }

    /**
     * Save an array of formats with default action
     *
     * @param array $formats - formats with status
     */
    public function setDefaultFormats($formats) {
        $value = json_encode($formats);
        $this->logger->info("Set default formats: $value", ["app" => $this->appName]);

        $this->config->setAppValue($this->appName, $this->_defFormats, $value);
    }

    /**
     * Get an array of formats with default action
     *
     * @return array
     */
    private function getDefaultFormats() {
        $value = $this->config->getAppValue($this->appName, $this->_defFormats, "");
        if (empty($value)) {
            return array();
        }
        return json_decode($value, true);
    }

    /**
     * Save an array of formats that is opened for editing
     *
     * @param array $formats - formats with status
     */
    public function setEditableFormats($formats) {
        $value = json_encode($formats);
        $this->logger->info("Set editing formats: $value", ["app" => $this->appName]);

        $this->config->setAppValue($this->appName, $this->_editFormats, $value);
    }

    /**
     * Get an array of formats opening for editing
     *
     * @return array
     */
    private function getEditableFormats() {
        $value = $this->config->getAppValue($this->appName, $this->_editFormats, "");
        if (empty($value)) {
            return array();
        }
        return json_decode($value, true);
    }

    /**
     * Save the opening setting in a same tab
     *
     * @param bool $value - same tab
     */
    public function setSameTab($value) {
        $this->logger->info("Set opening in a same tab: " . json_encode($value), ["app" => $this->appName]);

        if ($value) {
            $this->setEnableSharing(false);
        }

        $this->config->setAppValue($this->appName, $this->_sameTab, json_encode($value));
    }

    /**
     * Get the opening setting in a same tab
     *
     * @return bool
     */
    public function getSameTab() {
        return $this->config->getAppValue($this->appName, $this->_sameTab, "true") === "true";
    }

    /**
     * Save the enable sharing setting
     *
     * @param bool $value - enable sharing
     */
    public function setEnableSharing($value) {
        $this->logger->info("Set enable sharing: " . json_encode($value), ["app" => $this->appName]);

        $this->config->setAppValue($this->appName, $this->_enableSharing, json_encode($value));
    }

    /**
     * Get the enable sharing setting
     *
     * @return bool
     */
    public function getEnableSharing() {
        return $this->config->getAppValue($this->appName, $this->_enableSharing, "false") === "true";
    }

    /**
     * Save generate preview setting
     *
     * @param bool $value - preview
     */
    public function setPreview($value) {
        $this->logger->info("Set generate preview: " . json_encode($value), ["app" => $this->appName]);

        $this->config->setAppValue($this->appName, $this->_preview, json_encode($value));
    }

    /**
     * Get advanced setting
     *
     * @return bool
     */
    public function getAdvanced() {
        return $this->config->getAppValue($this->appName, $this->_advanced, "false") === "true";
    }

    /**
     * Save advanced setting
     *
     * @param bool $value - advanced
     */
    public function setAdvanced($value) {
        $this->logger->info("Set advanced: " . json_encode($value), ["app" => $this->appName]);

        $this->config->setAppValue($this->appName, $this->_advanced, json_encode($value));
    }

    /**
     * Get cron checker setting
     *
     * @return bool
     */
    public function getCronChecker() {
        return $this->config->getAppValue($this->appName, $this->_cronChecker, "true") !== "false";
    }

    /**
     * Save cron checker setting
     *
     * @param bool $value - cronChecker
     */
    public function setCronChecker($value) {
        $this->logger->info("Set cron checker: " . json_encode($value), ["app" => $this->appName]);

        $this->config->setAppValue($this->appName, $this->_cronChecker, json_encode($value));
    }

    /**
     * Get e-mail notifications setting
     *
     * @return bool
     */
    public function getEmailNotifications() {
        return $this->config->getAppValue($this->appName, $this->_emailNotifications, "true") !== "false";
    }

    /**
     * Save e-mail notifications setting
     *
     * @param bool $value - emailNotifications
     */
    public function setEmailNotifications($value) {
        $this->logger->info("Set e-mail notifications: " . json_encode($value), ["app" => $this->appName]);

        $this->config->setAppValue($this->appName, $this->_emailNotifications, json_encode($value));
    }

    /**
     * Get generate preview setting
     *
     * @return bool
     */
    public function getPreview() {
        return $this->config->getAppValue($this->appName, $this->_preview, "true") === "true";
    }

    /**
     * Save keep versions history
     *
     * @param bool $value - version history
     */
    public function setVersionHistory($value) {
        $this->logger->info("Set keep versions history: " . json_encode($value), ["app" => $this->appName]);

        $this->config->setAppValue($this->appName, $this->_versionHistory, json_encode($value));
    }

    /**
     * Get keep versions history
     *
     * @return bool
     */
    public function getVersionHistory() {
        return $this->config->getAppValue($this->appName, $this->_versionHistory, "true") === "true";
    }

    /**
     * Save protection
     *
     * @param bool $value - version history
     */
    public function setProtection($value) {
        $this->logger->info("Set protection: " . $value, ["app" => $this->appName]);

        $this->config->setAppValue($this->appName, $this->_protection, $value);
    }

    /**
     * Get protection
     *
     * @return bool
     */
    public function getProtection() {
        $value = $this->config->getAppValue($this->appName, $this->_protection, "owner");
        if ($value === "all") {
            return "all";
        }
        return "owner";
    }

    /**
     * Save chat display setting
     *
     * @param bool $value - display chat
     */
    public function setCustomizationChat($value) {
        $this->logger->info("Set chat display: " . json_encode($value), ["app" => $this->appName]);

        $this->config->setAppValue($this->appName, $this->_customizationChat, json_encode($value));
    }

    /**
     * Get chat display setting
     *
     * @return bool
     */
    public function getCustomizationChat() {
        return $this->config->getAppValue($this->appName, $this->_customizationChat, "true") === "true";
    }

    /**
     * Save compact header setting
     *
     * @param bool $value - display compact header
     */
    public function setCustomizationCompactHeader($value) {
        $this->logger->info("Set compact header display: " . json_encode($value), ["app" => $this->appName]);

        $this->config->setAppValue($this->appName, $this->_customizationCompactHeader, json_encode($value));
    }

    /**
     * Get compact header setting
     *
     * @return bool
     */
    public function getCustomizationCompactHeader() {
        return $this->config->getAppValue($this->appName, $this->_customizationCompactHeader, "true") === "true";
    }

    /**
     * Save feedback display setting
     *
     * @param bool $value - display feedback
     */
    public function setCustomizationFeedback($value) {
        $this->logger->info("Set feedback display: " . json_encode($value), ["app" => $this->appName]);

        $this->config->setAppValue($this->appName, $this->_customizationFeedback, json_encode($value));
    }

    /**
     * Get feedback display setting
     *
     * @return bool
     */
    public function getCustomizationFeedback() {
        return $this->config->getAppValue($this->appName, $this->_customizationFeedback, "true") === "true";
    }

    /**
     * Save forcesave setting
     *
     * @param bool $value - forcesave
     */
    public function setCustomizationForcesave($value) {
        $this->logger->info("Set forcesave: " . json_encode($value), ["app" => $this->appName]);

        $this->config->setAppValue($this->appName, $this->_customizationForcesave, json_encode($value));
    }

    /**
     * Get forcesave setting
     *
     * @return bool
     */
    public function getCustomizationForcesave() {
        return $this->config->getAppValue($this->appName, $this->_customizationForcesave, "false") === "true";
    }

    /**
     * Save live view on share setting
     *
     * @param bool $value - live view on share
     */
    public function setLiveViewOnShare($value) {
        $this->logger->info("Set live view on share: " . json_encode($value), ["app" => $this->appName]);

        $this->config->setAppValue($this->appName, $this->_liveViewOnShare, json_encode($value));
    }

    /**
     * Get live view on share setting
     *
     * @return bool
     */
    public function getLiveViewOnShare() {
        return $this->config->getAppValue($this->appName, $this->_liveViewOnShare, "false") === "true";
    }

    /**
     * Save help display setting
     *
     * @param bool $value - display help
     */
    public function setCustomizationHelp($value) {
        $this->logger->info("Set help display: " . json_encode($value), ["app" => $this->appName]);

        $this->config->setAppValue($this->appName, $this->_customizationHelp, json_encode($value));
    }

    /**
     * Get help display setting
     *
     * @return bool
     */
    public function getCustomizationHelp() {
        return $this->config->getAppValue($this->appName, $this->_customizationHelp, "true") === "true";
    }

    /**
     * Save review viewing mode setting
     *
     * @param string $value - review mode
     */
    public function setCustomizationReviewDisplay($value) {
        $this->logger->info("Set review mode: " . $value, array("app" => $this->appName));

        $this->config->setAppValue($this->appName, $this->_customizationReviewDisplay, $value);
    }

    /**
     * Get review viewing mode setting
     *
     * @return string
     */
    public function getCustomizationReviewDisplay() {
        $value = $this->config->getAppValue($this->appName, $this->_customizationReviewDisplay, "original");
        if ($value === "markup") {
            return "markup";
        }
        if ($value === "final") {
            return "final";
        }
        return "original";
    }

    /**
     * Save theme setting
     *
     * @param string $value - theme
     */
    public function setCustomizationTheme($value) {
        $this->logger->info("Set theme: " . $value, array("app" => $this->appName));

        $this->config->setAppValue($this->appName, $this->_customizationTheme, $value);
    }

    /**
     * Get theme setting
     *
     * @param bool $realValue - get real value (for example, for settings)
     * @return string
     */
    public function getCustomizationTheme($realValue = false) {
        $value = $this->config->getAppValue($this->appName, $this->_customizationTheme, "theme-system");
        $validThemes = [
            "default" => "theme-system",
            "light" => "default-light",
            "dark" => "default-dark"
        ];

        if (!in_array($value, $validThemes)) {
            $value = "theme-system";
        }

        if ($realValue) {
            return $value;
        }

        if ($value === "theme-system") {
            $user = \OC::$server->getUserSession()->getUser();

            if ($user !== null) {
                $themingMode = $this->config->getUserValue($user->getUID(), "theming", "enabled-themes", "");

                if ($themingMode !== "") {
                    try {
                        $themingModeArray = json_decode($themingMode, true);
                        $themingMode = $themingModeArray[0] ?? "";

                        if (isset($validThemes[$themingMode])) {
                            return $validThemes[$themingMode];
                        }
                    } catch (Exception $e) {
                        $this->logger->error("Error decoding theming mode: " . $e->getMessage());
                    }
                }
            }
        }

        return $value;
    }

    /**
     * Save unknownAuthor setting
     *
     * @param string $value - unknown author
     */
    public function setUnknownAuthor($value) {
        $this->logger->info("Set unknownAuthor: " . trim($value), ["app" => $this->appName]);
        $this->config->setAppValue($this->appName, $this->_unknownAuthor, trim($value));
    }

    /**
     * Get unknownAuthor setting
     *
     * @return string
     */
    public function getUnknownAuthor() {
        return $this->config->getAppValue($this->appName, $this->_unknownAuthor, "");
    }

    /**
     * Save watermark settings
     *
     * @param array $settings - watermark settings
     */
    public function setWatermarkSettings($settings) {
        $this->logger->info("Set watermark enabled: " . $settings["enabled"], ["app" => $this->appName]);

        if ($settings["enabled"] !== "true") {
            $this->config->setAppValue(AppConfig::WATERMARK_APP_NAMESPACE, "watermark_enabled", "no");
            return;
        }

        $this->config->setAppValue(AppConfig::WATERMARK_APP_NAMESPACE, "watermark_text", trim($settings["text"]));

        $watermarkLabels = [
            "allGroups",
            "allTags",
            "linkAll",
            "linkRead",
            "linkSecure",
            "linkTags",
            "enabled",
            "shareAll",
            "shareRead",
        ];
        foreach ($watermarkLabels as $key) {
            if (empty($settings[$key])) {
                $settings[$key] = array();
            }
            $value = $settings[$key] === "true" ? "yes" : "no";
            $this->config->setAppValue(AppConfig::WATERMARK_APP_NAMESPACE, "watermark_" . $key, $value);
        }

        $watermarkLists = [
            "allGroupsList",
            "allTagsList",
            "linkTagsList",
        ];
        foreach ($watermarkLists as $key) {
            if (empty($settings[$key])) {
                $settings[$key] = array();
            }
            $value = implode(",", $settings[$key]);
            $this->config->setAppValue(AppConfig::WATERMARK_APP_NAMESPACE, "watermark_" . $key, $value);
        }
    }

    /**
     * Get watermark settings
     *
     * @return bool|array
     */
    public function getWatermarkSettings() {
        $result = [
            "text" => $this->config->getAppValue(AppConfig::WATERMARK_APP_NAMESPACE, "watermark_text", "{userId}, {date}"),
        ];

        $watermarkLabels = [
            "allGroups",
            "allTags",
            "linkAll",
            "linkRead",
            "linkSecure",
            "linkTags",
            "enabled",
            "shareAll",
            "shareRead",
        ];

        $trueResult = array("on", "yes", "true");
        foreach ($watermarkLabels as $key) {
            $value = $this->config->getAppValue(AppConfig::WATERMARK_APP_NAMESPACE, "watermark_" . $key, "no");
            $result[$key] = in_array($value, $trueResult);
        }

        $watermarkLists = [
            "allGroupsList",
            "allTagsList",
            "linkTagsList",
        ];

        foreach ($watermarkLists as $key) {
            $value = $this->config->getAppValue(AppConfig::WATERMARK_APP_NAMESPACE, "watermark_" . $key, "");
            $result[$key] = !empty($value) ? explode(",", $value) : [];
        }

        return $result;
    }

    /**
     * Save the list of groups
     *
     * @param array $groups - the list of groups
     */
    public function setLimitGroups($groups) {
        if (!is_array($groups)) {
            $groups = array();
        }
        $value = json_encode($groups);
        $this->logger->info("Set groups: $value", ["app" => $this->appName]);

        $this->config->setAppValue($this->appName, $this->_groups, $value);
    }

    /**
     * Get the list of groups
     *
     * @return array
     */
    public function getLimitGroups() {
        $value = $this->config->getAppValue($this->appName, $this->_groups, "");
        if (empty($value)) {
            return array();
        }
        $groups = json_decode($value, true);
        if (!is_array($groups)) {
            $groups = array();
        }
        return $groups;
    }

    /**
     * Check access for group
     *
     * @param string $userId - user identifier
     *
     * @return bool
     */
    public function isUserAllowedToUse($userId = null) {
        // no user -> no
        $userSession = \OC::$server->getUserSession();
        if (is_null($userId) && ($userSession === null || !$userSession->isLoggedIn())) {
            return false;
        }

        $groups = $this->getLimitGroups();
        // no group set -> all users are allowed
        if (empty($groups)) {
            return true;
        }

        if (is_null($userId)) {
            $user = $userSession->getUser();
        } else {
            $user = \OC::$server->getUserManager()->get($userId);
            if (empty($user)) {
                return false;
            }
        }

        foreach ($groups as $groupName) {
            // group unknown -> error and allow nobody
            $group = \OC::$server->getGroupManager()->get($groupName);
            if ($group === null) {
                \OCP\Log\logger('onlyoffice')->error("Group is unknown $groupName", ["app" => $this->appName]);
                $this->setLimitGroups(array_diff($groups, [$groupName]));
            } else {
                if ($group->inGroup($user)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Save the document service verification setting to the application configuration
     *
     * @param bool $verifyPeerOff - parameter verification setting
     */
    public function setVerifyPeerOff($verifyPeerOff) {
        $this->logger->info("setVerifyPeerOff " . json_encode($verifyPeerOff), ["app" => $this->appName]);

        $this->config->setAppValue($this->appName, $this->_verification, json_encode($verifyPeerOff));
    }

    /**
     * Get the document service verification setting to the application configuration
     *
     * @return bool
     */
    public function getVerifyPeerOff() {
        $turnOff = $this->config->getAppValue($this->appName, $this->_verification, "");

        if (!empty($turnOff)) {
            return $turnOff === "true";
        }

        return $this->getSystemValue($this->_verification);
    }

    /**
     * Get the limit on size document when generating thumbnails
     *
     * @return int
     */
    public function getLimitThumbSize() {
        $limitSize = (integer)$this->getSystemValue($this->_limitThumbSize);

        if (!empty($limitSize)) {
            return $limitSize;
        }

        return 100 * 1024 * 1024;
    }

    /**
     * Get the jwt header setting
     *
     * @param bool $origin - take origin
     *
     * @return string
     */
    public function jwtHeader($origin = false) {
        if (!$origin && $this->useDemo()) {
            return $this->DEMO_PARAM["HEADER"];
        }

        $header = $this->config->getAppValue($this->appName, $this->_jwtHeader, "");
        if (empty($header)) {
            $header = $this->getSystemValue($this->_jwtHeader);
        }
        if (!$origin && empty($header)) {
            $header = "Authorization";
        }
        return $header;
    }

    /**
     * Save the jwtHeader setting
     *
     * @param string $value - jwtHeader
     */
    public function setJwtHeader($value) {
        $value = trim($value);
        if (empty($value)) {
            $this->logger->info("Clear header key", ["app" => $this->appName]);
        } else {
            $this->logger->info("Set header key " . $value, ["app" => $this->appName]);
        }

        $this->config->setAppValue($this->appName, $this->_jwtHeader, $value);
    }

    /**
     * Get the Jwt Leeway
     *
     * @return int
     */
    public function getJwtLeeway() {
        $jwtLeeway = (integer)$this->getSystemValue($this->_jwtLeeway);

        return $jwtLeeway;
    }

    /**
     * Save the status settings
     *
     * @param string $value - error
     */
    public function setSettingsError($value) {
        $this->config->setAppValue($this->appName, $this->_settingsError, $value);
    }

    /**
     * Get the error text of the status settings
     *
     * @param string $value - error
     */
    public function getSettingsError() {
        return $this->config->getAppValue($this->appName, $this->_settingsError, "");
    }

    /**
     * Get the status settings
     *
     * @return bool
     */
    public function settingsAreSuccessful() {
        return empty($this->getSettingsError());
    }

    /**
     * Get supported formats
     *
     * @return array
     */
    #[NoAdminRequired]
    public function formatsSetting() {
        $result = $this->buildOnlyofficeFormats();

        $defFormats = $this->getDefaultFormats();
        foreach ($defFormats as $format => $setting) {
            if (array_key_exists($format, $result)) {
                $result[$format]["def"] = ($setting === true || $setting === "true");
            }
        }

        $editFormats = $this->getEditableFormats();
        foreach ($editFormats as $format => $setting) {
            if (array_key_exists($format, $result)) {
                $result[$format]["edit"] = ($setting === true || $setting === "true");
            }
        }

        return $result;
    }

    /**
     * Save macros setting
     *
     * @param bool $value - enable macros
     */
    public function setCustomizationMacros($value) {
        $this->logger->info("Set macros enabled: " . json_encode($value), ["app" => $this->appName]);

        $this->config->setAppValue($this->appName, $this->_customizationMacros, json_encode($value));
    }

    /**
     * Get macros setting
     *
     * @return bool
     */
    public function getCustomizationMacros() {
        return $this->config->getAppValue($this->appName, $this->_customizationMacros, "true") === "true";
    }

    /**
     * Save plugins setting
     *
     * @param bool $value - enable macros
     */
    public function setCustomizationPlugins($value) {
        $this->logger->info("Set plugins enabled: " . json_encode($value), ["app" => $this->appName]);

        $this->config->setAppValue($this->appName, $this->_customizationPlugins, json_encode($value));
    }

    /**
     * Get plugins setting
     *
     * @return bool
     */
    public function getCustomizationPlugins() {
        return $this->config->getAppValue($this->appName, $this->_customizationPlugins, "true") === "true";
    }

    /**
     * Get the disable download value
     *
     * @return bool
     */
    public function getDisableDownload() {
        $disableDownload = (bool)$this->getSystemValue($this->_disableDownload);

        return $disableDownload;
    }
    /**
     * Get the editors check interval
     *
     * @return int
     */
    public function getEditorsCheckInterval() {
        $interval = $this->getSystemValue($this->_editors_check_interval);
        if ($interval !== null && !is_int($interval)) {
            if (is_string($interval) && !ctype_digit($interval)) {
                $interval = null;
            } else {
                $interval = (integer)$interval;
            }
        }

        if (empty($interval) && $interval !== 0) {
            $interval = 60 * 60 * 24;
        }
        return (integer)$interval;
    }

    /**
     * Get the JWT expiration
     *
     * @return int
     */
    public function getJwtExpiration() {
        $jwtExp = $this->getSystemValue($this->_jwt_expiration);

        if (empty($jwtExp)) {
            return 5;
        }
        return (integer)$jwtExp;
    }

    /**
     * Get ONLYOFFICE formats list
     *
     * @return array
     */
    private function buildOnlyofficeFormats() {
        try {
            $onlyofficeFormats = $this->getFormats();
            $result = [];
            $additionalFormats = $this->getAdditionalFormatAttributes();

            if ($onlyofficeFormats !== false) {
                foreach ($onlyofficeFormats as $onlyOfficeFormat) {
                    if ($onlyOfficeFormat["name"]
                        && $onlyOfficeFormat["mime"]
                        && $onlyOfficeFormat["type"]
                        && $onlyOfficeFormat["actions"]
                        && $onlyOfficeFormat["convert"]) {
                        $result[$onlyOfficeFormat["name"]] = [
                            "mime" => $onlyOfficeFormat["mime"],
                            "type" => $onlyOfficeFormat["type"],
                            "edit" => in_array("edit", $onlyOfficeFormat["actions"]),
                            "editable" => in_array("lossy-edit", $onlyOfficeFormat["actions"]),
                            "conv" => in_array("auto-convert", $onlyOfficeFormat["actions"]),
                            "fillForms" => in_array("fill", $onlyOfficeFormat["actions"]),
                            "saveas" => $onlyOfficeFormat["convert"],
                        ];
                        if (isset($additionalFormats[$onlyOfficeFormat["name"]])) {
                            $result[$onlyOfficeFormat["name"]] = array_merge($result[$onlyOfficeFormat["name"]], $additionalFormats[$onlyOfficeFormat["name"]]);
                        }
                    }
                }
            }
            return $result;
        } catch (\Exception $e) {
            $this->logger->error("Format matrix error", ['exception' => $e]);
            return [];
        }
    }

    /**
     * Get the additional format attributes
     *
     * @return array
     */
    private function getAdditionalFormatAttributes() {
        $additionalFormatAttributes = [
            "docx" => [
                "def" => true,
                "review" => true,
                "comment" => true,
            ],
            "docxf" => [
                "def" => true,
                "createForm" => true,
            ],
            "oform" => [
                "def" => true,
                "createForm" => true,
            ],
            "pdf" => [
                "def" => true,
            ],
            "pptx" => [
                "def" => true,
                "comment" => true,
            ],
            "xlsx" => [
                "def" => true,
                "comment" => true,
                "modifyFilter" => true,
            ],
            "txt" => [
                "edit" => true,
            ],
            "csv" => [
                "edit" => true,
            ],
            "vsdx" => [
                "def" => true,
            ],
        ];
        return $additionalFormatAttributes;
    }

    /**
     * Get the formats list from cache or file
     *
     * @return array
     */
    public function getFormats() {
        $cachedFormats = $this->cache->get("document_formats");
        if ($cachedFormats !== null) {
            return json_decode($cachedFormats, true);
        }

        $formats = file_get_contents(dirname(__DIR__) . DIRECTORY_SEPARATOR . "assets" . DIRECTORY_SEPARATOR . "document-formats" . DIRECTORY_SEPARATOR . "onlyoffice-docs-formats.json");
        $this->cache->set("document_formats", $formats, 6 * 3600);
        $this->logger->debug("Getting formats from file", ["app" => $this->appName]);
        return json_decode($formats, true);
    }

    /**
     * Get the mime type by format name
     *
     * @param string $ext - format name
     *
     * @return string
     */
    public function getMimeType($ext) {
        $onlyofficeFormats = $this->getFormats();
        $result = "text/plain";

        foreach ($onlyofficeFormats as $onlyOfficeFormat) {
            if ($onlyOfficeFormat["name"] === $ext && !empty($onlyOfficeFormat["mime"])) {
                $result = $onlyOfficeFormat["mime"][0];
                break;
            }
        }

        return $result;
    }

    /**
     * DEMO DATA
     */
    private $DEMO_PARAM = [
        "ADDR" => "https://onlinedocs.docs.onlyoffice.com/",
        "HEADER" => "AuthorizationJWT",
        "SECRET" => "sn2puSUF7muF5Jas",
        "TRIAL" => 30
    ];
}
