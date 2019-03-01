<?php
/**
 *
 * (c) Copyright Ascensio System SIA 2019
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
 * You can contact Ascensio System SIA at 17-2 Elijas street, Riga, Latvia, EU, LV-1021.
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
     * @var OCP\IConfig
     */
    private $config;

    /**
     * Logger
     *
     * @var OCP\ILogger
     */
    private $logger;

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
        return NULL;
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

        $this->logger->info("SetDocumentServerUrl: " . $documentServer, array("app" => $this->appName));

        $this->config->setAppValue($this->appName, $this->_documentserver, $documentServer);
    }

    /**
     * Get the document service address from the application configuration
     *
     * @return string
     */
    public function GetDocumentServerUrl() {
        $url = $this->config->getAppValue($this->appName, $this->_documentserver, "");
        if (empty($url)) {
            $url = $this->getSystemValue($this->_documentserver);
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
     * @param string $documentServer - document service address
     */
    public function SetDocumentServerInternalUrl($documentServerInternal) {
        $documentServerInternal = rtrim(trim($documentServerInternal), "/");
        if (strlen($documentServerInternal) > 0) {
            $documentServerInternal = $documentServerInternal . "/";
            if (!preg_match("/^https?:\/\//i", $documentServerInternal)) {
                $documentServerInternal = "http://" . $documentServerInternal;
            }
        }

        $this->logger->info("SetDocumentServerInternalUrl: " . $documentServerInternal, array("app" => $this->appName));

        $this->config->setAppValue($this->appName, $this->_documentserverInternal, $documentServerInternal);
    }

    /**
     * Get the document service address available from Nextcloud from the application configuration
     *
     * @param bool $origin - take origin
     *
     * @return string
     */
    public function GetDocumentServerInternalUrl($origin) {
        $url = $this->config->getAppValue($this->appName, $this->_documentserverInternal, "");
        if (empty($url)) {
            $url = $this->getSystemValue($this->_documentserverInternal);
        }
        if (!$origin && empty($url)) {
            $url = $this->GetDocumentServerUrl();
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

        $this->logger->info("SetStorageUrl: " . $storageUrl, array("app" => $this->appName));

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
            $url = $this->getSystemValue($this->_storageUrl);
        }
        return $url;
    }

    /**
     * Save the document service secret key to the application configuration
     *
     * @param string $secret - secret key
     */
    public function SetDocumentServerSecret($secret) {
        if (empty($secret)) {
            $this->logger->info("Clear secret key", array("app" => $this->appName));
        } else {
            $this->logger->info("Set secret key", array("app" => $this->appName));
        }

        $this->config->setAppValue($this->appName, $this->_jwtSecret, $secret);
    }

    /**
     * Get the document service secret key from the application configuration
     *
     * @return string
     */
    public function GetDocumentServerSecret() {
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
    public function GetSKey() {
        return $this->config->getSystemValue($this->_cryptSecret, true);
    }

    /**
     * Save an array of formats with default action
     *
     * @param array $formats - formats with status
     */
    public function SetDefaultFormats($formats) {
        $value = json_encode($formats);
        $this->logger->info("Set default formats: " . $value, array("app" => $this->appName));

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
        $this->logger->info("Set editing formats: " . $value, array("app" => $this->appName));

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
        $this->logger->info("Set opening in a same tab: " . json_encode($value), array("app" => $this->appName));

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
     * Save the list of groups
     *
     * @param array $groups - the list of groups
     */
    public function SetLimitGroups($groups) {
        if (!is_array($groups)) {
            $groups = array();
        }
        $value = json_encode($groups);
        $this->logger->info("Set groups: " . $value, array("app" => $this->appName));

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
                \OC::$server->getLogger()->error("Group is unknown " . $groupName, ["app" => $this->appName]);
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
        $turnOff = $this->getSystemValue($this->_verification);
        return $turnOff === true;
    }

    /**
     * Get the jwt header setting
     *
     * @return string
     */
    public function JwtHeader() {
        $header = $this->getSystemValue($this->_jwtHeader);
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
}
