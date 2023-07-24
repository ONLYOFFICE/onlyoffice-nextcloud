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

use \DateInterval;
use \DateTime;

use OCP\IConfig;
use OCP\ILogger;

/**
 * Application configutarion
 *
 * @package OCA\Onlyoffice
 */
class AppConfig {

    /**
     * Application name
     *
     * @var string
     */
    private $appName;

    /**
     * Config service
     *
     * @var IConfig
     */
    private $config;

    /**
     * Logger
     *
     * @var ILogger
     */
    private $logger;

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
     * The config key for the help display setting
     *
     * @var string
     */
    private $_customizationHelp = "customizationHelp";

    /**
     * The config key for the no tabs setting
     *
     * @var string
     */
    private $_customizationToolbarNoTabs = "customizationToolbarNoTabs";

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
    const WATERMARK_APP_NAMESPACE = "files";

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
     * The config key for the goback
     *
     * @var string
     */
    public $_customization_goback = "customization_goback";

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
     * The config key for the interval of editors availability check by cron
     *
     * @var string
     */
    private $_editors_check_interval = "editors_check_interval";

    /**
     * @param string $AppName - application name
     */
    public function __construct($AppName) {

        $this->appName = $AppName;

        $this->config = \OC::$server->getConfig();
        $this->logger = \OC::$server->getLogger();
    }

    /**
     * Get value from the system configuration
     *
     * @param string $key - key configuration
     * @param bool $system - get from root or from app section
     *
     * @return string
     */
    public function GetSystemValue($key, $system = false) {
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
    public function SelectDemo($value) {
        $this->logger->info("Select demo: " . json_encode($value), ["app" => $this->appName]);

        $data = $this->GetDemoData();

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
    public function GetDemoData() {
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
    public function UseDemo() {
        return $this->GetDemoData()["enabled"] === true;
    }

    /**
     * Save the document service address to the application configuration
     *
     * @param string $documentServer - document service address
     */
    public function SetDocumentServerUrl($documentServer) {
        $documentServer = trim($documentServer);
        if (strlen($documentServer) > 0) {
            $documentServer = rtrim($documentServer, "/") . "/";
            if (!preg_match("/(^https?:\/\/)|^\//i", $documentServer)) {
                $documentServer = "http://" . $documentServer;
            }
        }

        $this->logger->info("SetDocumentServerUrl: $documentServer", ["app" => $this->appName]);

        $this->config->setAppValue($this->appName, $this->_documentserver, $documentServer);
    }

    /**
     * Get the document service address from the application configuration
     *
     * @param bool $origin - take origin
     *
     * @return string
     */
    public function GetDocumentServerUrl($origin = false) {
        if (!$origin && $this->UseDemo()) {
            return $this->DEMO_PARAM["ADDR"];
        }

        $url = $this->config->getAppValue($this->appName, $this->_documentserver, "");
        if (empty($url)) {
            $url = $this->GetSystemValue($this->_documentserver);
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
    public function SetDocumentServerInternalUrl($documentServerInternal) {
        $documentServerInternal = rtrim(trim($documentServerInternal), "/");
        if (strlen($documentServerInternal) > 0) {
            $documentServerInternal = $documentServerInternal . "/";
            if (!preg_match("/^https?:\/\//i", $documentServerInternal)) {
                $documentServerInternal = "http://" . $documentServerInternal;
            }
        }

        $this->logger->info("SetDocumentServerInternalUrl: $documentServerInternal", ["app" => $this->appName]);

        $this->config->setAppValue($this->appName, $this->_documentserverInternal, $documentServerInternal);
    }

    /**
     * Get the document service address available from Nextcloud from the application configuration
     *
     * @param bool $origin - take origin
     *
     * @return string
     */
    public function GetDocumentServerInternalUrl($origin = false) {
        if (!$origin && $this->UseDemo()) {
            return $this->GetDocumentServerUrl();
        }

        $url = $this->config->getAppValue($this->appName, $this->_documentserverInternal, "");
        if (empty($url)) {
            $url = $this->GetSystemValue($this->_documentserverInternal);
        }
        if (!$origin && empty($url)) {
            $url = $this->GetDocumentServerUrl();
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
    public function ReplaceDocumentServerUrlToInternal($url) {
        $documentServerUrl = $this->GetDocumentServerInternalUrl();
        if (!empty($documentServerUrl)) {
            $from = $this->GetDocumentServerUrl();

            if (!preg_match("/^https?:\/\//i", $from)) {
                $parsedUrl = parse_url($url);
                $from = $parsedUrl["scheme"] . "://" . $parsedUrl["host"] . (array_key_exists("port", $parsedUrl) ? (":" . $parsedUrl["port"]) : "") . $from;
            }

            if ($from !== $documentServerUrl)
            {
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
    public function SetStorageUrl($storageUrl) {
        $storageUrl = rtrim(trim($storageUrl), "/");
        if (strlen($storageUrl) > 0) {
            $storageUrl = $storageUrl . "/";
            if (!preg_match("/^https?:\/\//i", $storageUrl)) {
                $storageUrl = "http://" . $storageUrl;
            }
        }

        $this->logger->info("SetStorageUrl: $storageUrl", ["app" => $this->appName]);

        $this->config->setAppValue($this->appName, $this->_storageUrl, $storageUrl);
    }

    /**
     * Get the Nextcloud address available from document server from the application configuration
     *
     * @return string
     */
    public function GetStorageUrl() {
        $url = $this->config->getAppValue($this->appName, $this->_storageUrl, "");
        if (empty($url)) {
            $url = $this->GetSystemValue($this->_storageUrl);
        }
        return $url;
    }

    /**
     * Save the document service secret key to the application configuration
     *
     * @param string $secret - secret key
     */
    public function SetDocumentServerSecret($secret) {
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
    public function GetDocumentServerSecret($origin = false) {
        if (!$origin && $this->UseDemo()) {
            return $this->DEMO_PARAM["SECRET"];
        }

        $secret = $this->config->getAppValue($this->appName, $this->_jwtSecret, "");
        if (empty($secret)) {
            $secret = $this->GetSystemValue($this->_jwtSecret);
        }
        return $secret;
    }

    /**
     * Get the secret key from the application configuration
     *
     * @return string
     */
    public function GetSKey() {
        $secret = $this->GetDocumentServerSecret();
        if (empty($secret)) {
            $secret = $this->GetSystemValue($this->_cryptSecret, true);
        }
        return $secret;
    }

    /**
     * Save an array of formats with default action
     *
     * @param array $formats - formats with status
     */
    public function SetDefaultFormats($formats) {
        $value = json_encode($formats);
        $this->logger->info("Set default formats: $value", ["app" => $this->appName]);

        $this->config->setAppValue($this->appName, $this->_defFormats, $value);
    }

    /**
     * Get an array of formats with default action
     *
     * @return array
     */
    private function GetDefaultFormats() {
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
    public function SetEditableFormats($formats) {
        $value = json_encode($formats);
        $this->logger->info("Set editing formats: $value", ["app" => $this->appName]);

        $this->config->setAppValue($this->appName, $this->_editFormats, $value);
    }

    /**
     * Get an array of formats opening for editing
     *
     * @return array
     */
    private function GetEditableFormats() {
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
    public function SetSameTab($value) {
        $this->logger->info("Set opening in a same tab: " . json_encode($value), ["app" => $this->appName]);

        $this->config->setAppValue($this->appName, $this->_sameTab, json_encode($value));
    }

    /**
     * Get the opening setting in a same tab
     *
     * @return bool
     */
    public function GetSameTab() {
        return $this->config->getAppValue($this->appName, $this->_sameTab, "true") === "true";
    }

    /**
     * Save generate preview setting
     *
     * @param bool $value - preview
     */
    public function SetPreview($value) {
        $this->logger->info("Set generate preview: " . json_encode($value), ["app" => $this->appName]);

        $this->config->setAppValue($this->appName, $this->_preview, json_encode($value));
    }

    /**
     * Get advanced setting
     *
     * @return bool
     */
    public function GetAdvanced() {
        return $this->config->getAppValue($this->appName, $this->_advanced, "false") === "true";
    }

    /**
     * Save advanced setting
     *
     * @param bool $value - advanced
     */
    public function SetAdvanced($value) {
        $this->logger->info("Set advanced: " . json_encode($value), ["app" => $this->appName]);

        $this->config->setAppValue($this->appName, $this->_advanced, json_encode($value));
    }

    /**
     * Get generate preview setting
     *
     * @return bool
     */
    public function GetPreview() {
        return $this->config->getAppValue($this->appName, $this->_preview, "true") === "true";
    }

    /**
     * Save keep versions history
     *
     * @param bool $value - version history
     */
    public function SetVersionHistory($value) {
        $this->logger->info("Set keep versions history: " . json_encode($value), ["app" => $this->appName]);

        $this->config->setAppValue($this->appName, $this->_versionHistory, json_encode($value));
    }

    /**
     * Get keep versions history
     *
     * @return bool
     */
    public function GetVersionHistory() {
        return $this->config->getAppValue($this->appName, $this->_versionHistory, "true") === "true";
    }

    /**
     * Save protection
     *
     * @param bool $value - version history
     */
    public function SetProtection($value) {
        $this->logger->info("Set protection: " . $value, ["app" => $this->appName]);

        $this->config->setAppValue($this->appName, $this->_protection, $value);
    }

    /**
     * Get protection
     *
     * @return bool
     */
    public function GetProtection() {
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
    public function SetCustomizationChat($value) {
        $this->logger->info("Set chat display: " . json_encode($value), ["app" => $this->appName]);

        $this->config->setAppValue($this->appName, $this->_customizationChat, json_encode($value));
    }

    /**
     * Get chat display setting
     *
     * @return bool
     */
    public function GetCustomizationChat() {
        return $this->config->getAppValue($this->appName, $this->_customizationChat, "true") === "true";
    }

    /**
     * Save compact header setting
     *
     * @param bool $value - display compact header
     */
    public function SetCustomizationCompactHeader($value) {
        $this->logger->info("Set compact header display: " . json_encode($value), ["app" => $this->appName]);

        $this->config->setAppValue($this->appName, $this->_customizationCompactHeader, json_encode($value));
    }

    /**
     * Get compact header setting
     *
     * @return bool
     */
    public function GetCustomizationCompactHeader() {
        return $this->config->getAppValue($this->appName, $this->_customizationCompactHeader, "true") === "true";
    }

    /**
     * Save feedback display setting
     *
     * @param bool $value - display feedback
     */
    public function SetCustomizationFeedback($value) {
        $this->logger->info("Set feedback display: " . json_encode($value), ["app" => $this->appName]);

        $this->config->setAppValue($this->appName, $this->_customizationFeedback, json_encode($value));
    }

    /**
     * Get feedback display setting
     *
     * @return bool
     */
    public function GetCustomizationFeedback() {
        return $this->config->getAppValue($this->appName, $this->_customizationFeedback, "true") === "true";
    }

    /**
     * Save forcesave setting
     *
     * @param bool $value - forcesave
     */
    public function SetCustomizationForcesave($value) {
        $this->logger->info("Set forcesave: " . json_encode($value), ["app" => $this->appName]);

        $this->config->setAppValue($this->appName, $this->_customizationForcesave, json_encode($value));
    }

    /**
     * Get forcesave setting
     *
     * @return bool
     */
    public function GetCustomizationForcesave() {
        return $this->config->getAppValue($this->appName, $this->_customizationForcesave, "false") === "true";
    }

    /**
     * Save help display setting
     *
     * @param bool $value - display help
     */
    public function SetCustomizationHelp($value) {
        $this->logger->info("Set help display: " . json_encode($value), ["app" => $this->appName]);

        $this->config->setAppValue($this->appName, $this->_customizationHelp, json_encode($value));
    }

    /**
     * Get help display setting
     *
     * @return bool
     */
    public function GetCustomizationHelp() {
        return $this->config->getAppValue($this->appName, $this->_customizationHelp, "true") === "true";
    }

    /**
     * Save without tabs setting
     *
     * @param bool $value - without tabs
     */
    public function SetCustomizationToolbarNoTabs($value) {
        $this->logger->info("Set without tabs: " . json_encode($value), ["app" => $this->appName]);

        $this->config->setAppValue($this->appName, $this->_customizationToolbarNoTabs, json_encode($value));
    }

    /**
     * Get without tabs setting
     *
     * @return bool
     */
    public function GetCustomizationToolbarNoTabs() {
        return $this->config->getAppValue($this->appName, $this->_customizationToolbarNoTabs, "true") === "true";
    }

    /**
     * Save review viewing mode setting
     *
     * @param string $value - review mode
     */
    public function SetCustomizationReviewDisplay($value) {
        $this->logger->info("Set review mode: " . $value, array("app" => $this->appName));

        $this->config->setAppValue($this->appName, $this->_customizationReviewDisplay, $value);
    }

    /**
     * Get review viewing mode setting
     *
     * @return string
     */
    public function GetCustomizationReviewDisplay() {
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
    public function SetCustomizationTheme($value) {
        $this->logger->info("Set theme: " . $value, array("app" => $this->appName));

        $this->config->setAppValue($this->appName, $this->_customizationTheme, $value);
    }

    /**
     * Get theme setting
     *
     * @return string
     */
    public function GetCustomizationTheme() {
        $value = $this->config->getAppValue($this->appName, $this->_customizationTheme, "theme-classic-light");
        if ($value === "theme-light") {
            return "theme-light";
        }
        if ($value === "theme-dark") {
            return "theme-dark";
        }
        return "theme-classic-light";
    }

    /**
     * Save watermark settings
     *
     * @param array $settings - watermark settings
     */
    public function SetWatermarkSettings($settings) {
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
    public function GetWatermarkSettings() {
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
    public function SetLimitGroups($groups) {
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
    public function GetLimitGroups() {
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

        $groups = $this->GetLimitGroups();
        // no group set -> all users are allowed
        if (count($groups) === 0) {
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
                \OC::$server->getLogger()->error("Group is unknown $groupName", ["app" => $this->appName]);
                $this->SetLimitGroups(array_diff($groups, [$groupName]));
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
    public function SetVerifyPeerOff($verifyPeerOff) {
        $this->logger->info("SetVerifyPeerOff " . json_encode($verifyPeerOff), ["app" => $this->appName]);

        $this->config->setAppValue($this->appName, $this->_verification, json_encode($verifyPeerOff));
    }

    /**
     * Get the document service verification setting to the application configuration
     *
     * @return bool
     */
    public function GetVerifyPeerOff() {
        $turnOff = $this->config->getAppValue($this->appName, $this->_verification, "");

        if (!empty($turnOff)) {
            return $turnOff === "true";
        }

        return $this->GetSystemValue($this->_verification);
    }

    /**
     * Get the limit on size document when generating thumbnails
     *
     * @return int
     */
    public function GetLimitThumbSize() {
        $limitSize = (integer)$this->GetSystemValue($this->_limitThumbSize);

        if (!empty($limitSize)) {
            return $limitSize;
        }

        return 100*1024*1024;
    }

    /**
     * Get the jwt header setting
     *
     * @param bool $origin - take origin
     *
     * @return string
     */
    public function JwtHeader($origin = false) {
        if (!$origin && $this->UseDemo()) {
            return $this->DEMO_PARAM["HEADER"];
        }

        $header = $this->config->getAppValue($this->appName, $this->_jwtHeader, "");
        if (empty($header)) {
            $header = $this->GetSystemValue($this->_jwtHeader);
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
    public function SetJwtHeader($value) {
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
    public function GetJwtLeeway() {
        $jwtLeeway = (integer)$this->GetSystemValue($this->_jwtLeeway);

        return $jwtLeeway;
    }

    /**
     * Save the status settings
     *
     * @param string $value - error
     */
    public function SetSettingsError($value) {
        $this->config->setAppValue($this->appName, $this->_settingsError, $value);
    }

    /**
     * Get the status settings
     *
     * @return bool
     */
    public function SettingsAreSuccessful() {
        return empty($this->config->getAppValue($this->appName, $this->_settingsError, ""));
    }

    /**
     * Get supported formats
     *
     * @return array
     *
     * @NoAdminRequired
     */
    public function FormatsSetting() {
        $result = $this->formats;

        $defFormats = $this->GetDefaultFormats();
        foreach ($defFormats as $format => $setting) {
            if (array_key_exists($format, $result)) {
                $result[$format]["def"] = ($setting === true || $setting === "true");
            }
        }

        $editFormats = $this->GetEditableFormats();
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
    public function SetCustomizationMacros($value) {
        $this->logger->info("Set macros enabled: " . json_encode($value), ["app" => $this->appName]);

        $this->config->setAppValue($this->appName, $this->_customizationMacros, json_encode($value));
    }

    /**
     * Get macros setting
     *
     * @return bool
     */
    public function GetCustomizationMacros() {
        return $this->config->getAppValue($this->appName, $this->_customizationMacros, "true") === "true";
    }

    /**
     * Save plugins setting
     *
     * @param bool $value - enable macros
     */
    public function SetCustomizationPlugins($value) {
        $this->logger->info("Set plugins enabled: " . json_encode($value), ["app" => $this->appName]);

        $this->config->setAppValue($this->appName, $this->_customizationPlugins, json_encode($value));
    }

    /**
     * Get plugins setting
     *
     * @return bool
     */
    public function GetCustomizationPlugins() {
        return $this->config->getAppValue($this->appName, $this->_customizationPlugins, "true") === "true";
    }

    /**
     * Get the editors check interval
     *
     * @return int
     */
    public function GetEditorsCheckInterval() {
        $interval = $this->GetSystemValue($this->_editors_check_interval);

        if (empty($interval) && $interval !== 0) {
            $interval = 60*60*24;
        }
        return (integer)$interval;
    }

    /**
     * Additional data about formats
     *
     * @var array
     */
    private $formats = [
        "csv" => [ "mime" => "text/csv", "type" => "cell", "edit" => true, "editable" => true, "saveas" => ["ods", "pdf", "xlsx"] ],
        "doc" => [ "mime" => "application/msword", "type" => "word", "conv" => true, "saveas" => ["docx", "odt", "pdf", "rtf", "txt"] ],
        "docm" => [ "mime" => "application/vnd.ms-word.document.macroEnabled.12", "type" => "word", "conv" => true, "saveas" => ["docx", "odt", "pdf", "rtf", "txt"] ],
        "docx" => [ "mime" => "application/vnd.openxmlformats-officedocument.wordprocessingml.document", "type" => "word", "edit" => true, "def" => true, "review" => true, "comment" => true, "saveas" => ["odt", "pdf", "rtf", "txt", "docxf"] ],
        "docxf" => [ "mime" => "application/vnd.openxmlformats-officedocument.wordprocessingml.document.docxf", "type" => "word", "edit" => true, "def" => true, "review" => true, "comment" => true, "saveas" => ["odt", "pdf", "rtf", "txt"], "createForm" => true ],
        "oform" => [ "mime" => "application/vnd.openxmlformats-officedocument.wordprocessingml.document.oform", "type" => "word", "fillForms" => true, "def" => true ],
        "dot" => [ "type" => "word", "conv" => true, "saveas" => ["docx", "odt", "pdf", "rtf", "txt"] ],
        "dotx" => [ "mime" => "application/vnd.openxmlformats-officedocument.wordprocessingml.template", "type" => "word", "conv" => true, "saveas" => ["docx", "odt", "pdf", "rtf", "txt"] ],
        "epub" => [ "mime" => "application/epub+zip", "type" => "word", "conv" => true, "saveas" => ["docx", "odt", "pdf", "rtf", "txt"] ],
        "htm" => [ "type" => "word", "conv" => true ],
        "html" => [ "mime" => "text/html", "type" => "word", "conv" => true, "saveas" => ["docx", "odt", "pdf", "rtf", "txt"] ],
        "odp" => [ "mime" => "application/vnd.oasis.opendocument.presentation", "type" => "slide", "conv" => true, "editable" => true, "saveas" => ["pdf", "pptx"] ],
        "ods" => [ "mime" => "application/vnd.oasis.opendocument.spreadsheet", "type" => "cell", "conv" => true, "editable" => true, "saveas" => ["csv", "pdf", "xlsx"] ],
        "odt" => [ "mime" => "application/vnd.oasis.opendocument.text", "type" => "word", "conv" => true, "editable" => true, "saveas" => ["docx", "pdf", "rtf", "txt"] ],
        "otp" => [ "mime" => "application/vnd.oasis.opendocument.presentation-template", "type" => "slide", "conv" => true, "saveas" => ["pdf", "pptx", "odp"] ],
        "ots" => [ "mime" => "application/vnd.oasis.opendocument.spreadsheet-template", "type" => "cell", "conv" => true, "saveas" => ["csv", "ods", "pdf", "xlsx"] ],
        "ott" => [ "mime" => "application/vnd.oasis.opendocument.text-template", "type" => "word", "conv" => true, "saveas" => ["docx", "odt", "pdf", "rtf", "txt"] ],
        "pdf" => [ "mime" => "application/pdf", "type" => "word" ],
        "pot" => [ "type" => "slide", "conv" => true, "saveas" => ["pdf", "pptx", "odp"] ],
        "potm" => [ "mime" => "application/vnd.ms-powerpoint.template.macroEnabled.12", "type" => "slide", "conv" => true, "saveas" => ["pdf", "pptx", "odp"] ],
        "potx" => [ "mime" => "application/vnd.openxmlformats-officedocument.presentationml.template", "type" => "slide", "conv" => true, "saveas" => ["pdf", "pptx", "odp"] ],
        "pps" => [ "type" => "slide", "conv" => true, "saveas" => ["pdf", "pptx", "odp"] ],
        "ppsm" => [ "mime" => "application/vnd.ms-powerpoint.slideshow.macroEnabled.12", "type" => "slide", "conv" => true, "saveas" => ["pdf", "pptx", "odp"] ],
        "ppsx" => [ "mime" => "application/vnd.openxmlformats-officedocument.presentationml.slideshow", "type" => "slide", "conv" => true, "saveas" => ["pdf", "pptx", "odp"] ],
        "ppt" => [ "mime" => "application/vnd.ms-powerpoint", "type" => "slide", "conv" => true, "saveas" => ["pdf", "pptx", "odp"] ],
        "pptm" => [ "mime" => "application/vnd.ms-powerpoint.presentation.macroEnabled.12", "type" => "slide", "conv" => true, "saveas" => ["pdf", "pptx", "odp"] ],
        "pptx" => [ "mime" => "application/vnd.openxmlformats-officedocument.presentationml.presentation", "type" => "slide", "edit" => true, "def" => true, "comment" => true, "saveas" => ["pdf", "odp"] ],
        "rtf" => [ "mime" => "text/rtf", "type" => "word", "conv" => true, "editable" => true, "saveas" => ["docx", "odt", "pdf", "txt"] ],
        "txt" => [ "mime" => "text/plain", "type" => "word", "edit" => true, "editable" => true, "saveas" => ["docx", "odt", "pdf", "rtf"] ],
        "xls" => [ "mime" => "application/vnd.ms-excel", "type" => "cell", "conv" => true, "saveas" => ["csv", "ods", "pdf", "xlsx"] ],
        "xlsm" => [ "mime" => "application/vnd.ms-excel.sheet.macroEnabled.12", "type" => "cell", "conv" => true, "saveas" => ["csv", "ods", "pdf", "xlsx"] ],
        "xlsx" => [ "mime" => "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet", "type" => "cell", "edit" => true, "def" => true, "comment" => true, "modifyFilter" => true, "saveas" => ["csv", "ods", "pdf"] ],
        "xlt" => [ "type" => "cell", "conv" => true, "saveas" => ["csv", "ods", "pdf", "xlsx"] ],
        "xltm" => [ "mime" => "application/vnd.ms-excel.template.macroEnabled.12", "type" => "cell", "conv" => true, "saveas" => ["csv", "ods", "pdf", "xlsx"] ],
        "xltx" => [ "mime" => "application/vnd.openxmlformats-officedocument.spreadsheetml.template", "type" => "cell", "conv" => true, "saveas" => ["csv", "ods", "pdf", "xlsx"] ]
    ];

    /**
     * DEMO DATA
     */
    private $DEMO_PARAM = [
        "ADDR" => "https://onlinedocs.onlyoffice.com/",
        "HEADER" => "AuthorizationJWT",
        "SECRET" => "sn2puSUF7muF5Jas",
        "TRIAL" => 30
    ];

    private $linkToDocs = "https://www.onlyoffice.com/docs-registration.aspx?referer=nextcloud";

    /**
     * Get link to Docs Cloud
     *
     * @return string
     */
    public function GetLinkToDocs() {
        return $this->linkToDocs;
    }
}
