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
     * The config key for the feedback
     *
     * @var string
     */
    public $_customization_feedback = "customization_feedback";

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
        if ($url !== "/") {
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
        return $this->config->getAppValue($this->appName, $this->_sameTab, "false") === "true";
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
            "text" => $this->config->getAppValue(AppConfig::WATERMARK_APP_NAMESPACE, "watermark_text", "{userId}"),
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
     * @return bool
     */
    public function isUserAllowedToUse() {
        // no user -> no
        $userSession = \OC::$server->getUserSession();
        if ($userSession === null || !$userSession->isLoggedIn()) {
            return false;
        }

        $groups = $this->GetLimitGroups();
        // no group set -> all users are allowed
        if (count($groups) === 0) {
            return true;
        }

        $user = $userSession->getUser();

        foreach ($groups as $groupName) {
            // group unknown -> error and allow nobody
            $group = \OC::$server->getGroupManager()->get($groupName);
            if ($group === null) {
                \OC::$server->getLogger()->error("Group is unknown $groupName", ["app" => $this->appName]);
            } else {
                if ($group->inGroup($user)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get the turn off verification setting
     *
     * @return bool
     */
    public function TurnOffVerification() {
        $turnOff = $this->GetSystemValue($this->_verification);
        return $turnOff === true;
    }

    /**
     * Get the jwt header setting
     *
     * @return string
     */
    public function JwtHeader() {
        if ($this->UseDemo()) {
            return $this->DEMO_PARAM["HEADER"];
        }

        $header = $this->GetSystemValue($this->_jwtHeader);
        if (empty($header)) {
            $header = "Authorization";
        }
        return $header;
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
     * Additional data about formats
     *
     * @var array
     */
    private $formats = [
        "csv" => [ "mime" => "text/csv", "type" => "spreadsheet", "edit" => true, "editable" => true ],
        "doc" => [ "mime" => "application/msword", "type" => "text", "conv" => true ],
        "docm" => [ "mime" => "application/vnd.ms-word.document.macroEnabled.12", "type" => "text", "conv" => true ],
        "docx" => [ "mime" => "application/vnd.openxmlformats-officedocument.wordprocessingml.document", "type" => "text", "edit" => true, "def" => true ],
        "dot" => [ "type" => "text", "conv" => true ],
        "dotx" => [ "mime" => "application/vnd.openxmlformats-officedocument.wordprocessingml.template", "type" => "text", "conv" => true ],
        "epub" => [ "mime" => "application/epub+zip", "type" => "text", "conv" => true ],
        "htm" => [ "type" => "text", "conv" => true ],
        "html" => [ "mime" => "text/html", "type" => "text", "conv" => true ],
        "odp" => [ "mime" => "application/vnd.oasis.opendocument.presentation", "type" => "presentation", "conv" => true, "editable" => true ],
        "ods" => [ "mime" => "application/vnd.oasis.opendocument.spreadsheet", "type" => "spreadsheet", "conv" => true, "editable" => true ],
        "odt" => [ "mime" => "application/vnd.oasis.opendocument.text", "type" => "text", "conv" => true, "editable" => true ],
        "pdf" => [ "mime" => "application/pdf", "type" => "text" ],
        "pot" => [ "type" => "presentation", "conv" => true ],
        "potm" => [ "mime" => "application/vnd.ms-powerpoint.template.macroEnabled.12", "type" => "presentation", "conv" => true ],
        "potx" => [ "mime" => "application/vnd.openxmlformats-officedocument.presentationml.template", "type" => "presentation", "conv" => true ],
        "pps" => [ "type" => "presentation", "conv" => true ],
        "ppsm" => [ "mime" => "application/vnd.ms-powerpoint.slideshow.macroEnabled.12", "type" => "presentation", "conv" => true ],
        "ppsx" => [ "mime" => "application/vnd.openxmlformats-officedocument.presentationml.slideshow", "type" => "presentation", "conv" => true ],
        "ppt" => [ "mime" => "application/vnd.ms-powerpoint", "type" => "presentation", "conv" => true ],
        "pptm" => [ "mime" => "application/vnd.ms-powerpoint.presentation.macroEnabled.12", "type" => "presentation", "conv" => true ],
        "pptx" => [ "mime" => "application/vnd.openxmlformats-officedocument.presentationml.presentation", "type" => "presentation", "edit" => true, "def" => true ],
        "rtf" => [ "mime" => "text/rtf", "type" => "text", "conv" => true, "editable" => true ],
        "txt" => [ "mime" => "text/plain", "type" => "text", "edit" => true, "editable" => true ],
        "xls" => [ "mime" => "application/vnd.ms-excel", "type" => "spreadsheet", "conv" => true ],
        "xlsm" => [ "mime" => "application/vnd.ms-excel.sheet.macroEnabled.12", "type" => "spreadsheet", "conv" => true ],
        "xlsx" => [ "mime" => "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet", "type" => "spreadsheet", "edit" => true, "def" => true ],
        "xlt" => [ "type" => "spreadsheet", "conv" => true ],
        "xltm" => [ "mime" => "application/vnd.ms-excel.template.macroEnabled.12", "type" => "spreadsheet", "conv" => true ],
        "xltx" => [ "mime" => "application/vnd.openxmlformats-officedocument.spreadsheetml.template", "type" => "spreadsheet", "conv" => true ]
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
}
