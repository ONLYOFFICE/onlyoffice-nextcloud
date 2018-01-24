<?php
/**
 *
 * (c) Copyright Ascensio System Limited 2010-2017
 *
 * This program is freeware. You can redistribute it and/or modify it under the terms of the GNU
 * General Public License (GPL) version 3 as published by the Free Software Foundation (https://www.gnu.org/copyleft/gpl.html).
 * In accordance with Section 7(a) of the GNU GPL its Section 15 shall be amended to the effect that
 * Ascensio System SIA expressly excludes the warranty of non-infringement of any third-party rights.
 *
 * THIS PROGRAM IS DISTRIBUTED WITHOUT ANY WARRANTY; WITHOUT EVEN THE IMPLIED WARRANTY OF MERCHANTABILITY OR
 * FITNESS FOR A PARTICULAR PURPOSE. For more details, see GNU GPL at https://www.gnu.org/copyleft/gpl.html
 *
 * You can contact Ascensio System SIA by email at sales@onlyoffice.com
 *
 * The interactive user interfaces in modified source and object code versions of ONLYOFFICE must display
 * Appropriate Legal Notices, as required under Section 5 of the GNU GPL version 3.
 *
 * Pursuant to Section 7 § 3(b) of the GNU GPL you must retain the original ONLYOFFICE logo which contains
 * relevant author attributions when distributing the software. If the display of the logo in its graphic
 * form is not reasonably feasible for technical reasons, you must include the words "Powered by ONLYOFFICE"
 * in every copy of the program you distribute.
 * Pursuant to Section 7 § 3(e) we decline to grant you any rights under trademark law for use of our trademarks.
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
     * The config key for the document server address available from ownCloud
     *
     * @var string
     */
    private $_documentserverInternal = "DocumentServerInternalUrl";

    /**
     * The config key for the ownCloud address available from document server
     *
     * @var string
     */
    private $_storageUrl = "StorageUrl";

    /**
     * The config key for the secret key
     *
     * @var string
     */
    private $_cryptSecret = "skey";

    /**
     * The config key for the default formats
     *
     * @var string
     */
    private $_defFormats = "defFormats";

    /**
     * The config key for the setting same tab
     *
     * @var string
     */
    private $_sameTab = "sameTab";

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
     * @param string $AppName - application name
     */
    public function __construct($AppName) {

        $this->appName = $AppName;

        $this->config = \OC::$server->getConfig();
        $this->logger = \OC::$server->getLogger();
    }

    /**
     * Save the document service address to the application configuration
     *
     * @param string $documentServer - document service address
     */
    public function SetDocumentServerUrl($documentServer) {
        $documentServer = strtolower(trim($documentServer));
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
        if (empty($url)
            && !empty($this->config->getSystemValue($this->appName))
            && array_key_exists($this->_documentserver, $this->config->getSystemValue($this->appName))) {
            $url = $this->config->getSystemValue($this->appName)[$this->_documentserver];
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
     * Save the document service address available from ownCloud to the application configuration
     *
     * @param string $documentServer - document service address
     */
    public function SetDocumentServerInternalUrl($documentServerInternal) {
        $documentServerInternal = strtolower(rtrim(trim($documentServerInternal), "/"));
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
     * Get the document service address available from ownCloud from the application configuration
     *
     * @return string
     */
    public function GetDocumentServerInternalUrl($origin) {
        $url = $this->config->getAppValue($this->appName, $this->_documentserverInternal, "");
        if (empty($url)
            && !empty($this->config->getSystemValue($this->appName))
            && array_key_exists($this->_documentserverInternal, $this->config->getSystemValue($this->appName))) {
            $url = $this->config->getSystemValue($this->appName)[$this->_documentserverInternal];
        }
        if (!$origin && empty($url)) {
            $url = $this->GetDocumentServerUrl();
        }
        return $url;
    }

    /**
     * Save the ownCloud address available from document server to the application configuration
     *
     * @param string $documentServer - document service address
     */
    public function SetStorageUrl($storageUrl) {
        $storageUrl = strtolower(rtrim(trim($storageUrl), "/"));
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
     * Get the ownCloud address available from document server from the application configuration
     *
     * @return string
     */
    public function GetStorageUrl() {
        $url = $this->config->getAppValue($this->appName, $this->_storageUrl, "");
        if (empty($url)
            && !empty($this->config->getSystemValue($this->appName))
            && array_key_exists($this->_storageUrl, $this->config->getSystemValue($this->appName))) {
            $url = $this->config->getSystemValue($this->appName)[$this->_storageUrl];
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
        if (empty($secret)
            && !empty($this->config->getSystemValue($this->appName))
            && array_key_exists($this->_jwtSecret, $this->config->getSystemValue($this->appName))) {
            $secret = $this->config->getSystemValue($this->appName)[$this->_jwtSecret];
        }
        return $secret;
    }

    /**
     * Get the secret key from the application configuration
     *
     * @return string
     */
    public function GetSKey() {
        $skey = $this->config->getAppValue($this->appName, $this->_cryptSecret, "");
        if (empty($skey)) {
            $skey = number_format(round(microtime(true) * 1000), 0, ".", "");
            $this->config->setAppValue($this->appName, $this->_cryptSecret, $skey);
        }
        return $skey;
    }

    /**
     * Regenerate the secret key
     */
    public function DropSKey() {
        $this->config->setAppValue($this->appName, $this->_cryptSecret, "");
    }

    /**
     * Save the formats array with default action
     *
     * @param array $formats - formats with status
     */
    public function SetDefaultFormats($formats) {
        $value = json_encode($formats);
        $this->logger->info("Set default formats: " . $value, array("app" => $this->appName));

        $this->config->setAppValue($this->appName, $this->_defFormats, $value);
    }

    /**
     * Get the formats array with default action
     *
     * @return array
     */
    public function GetDefaultFormats() {
        $value = $this->config->getAppValue($this->appName, $this->_defFormats, "");
        if (empty($value)) {
            return array();
        }
        return json_decode($value, true);
    }

    /**
     * Save the opening setting in a same tab
     *
     * @param boolean $value - same tab
     */
    public function SetSameTab($value) {
        $this->logger->info("Set opening in a same tab: " . $value, array("app" => $this->appName));

        $this->config->setAppValue($this->appName, $this->_sameTab, $value);
    }

    /**
     * Get the opening setting in a same tab
     *
     * @return boolean
     */
    public function GetSameTab() {
        return $this->config->getAppValue($this->appName, $this->_sameTab, "false") === "true";
    }

    /**
     * Get the turn off verification setting
     *
     * @return boolean
     */
    public function TurnOffVerification() {
        $turnOff = FALSE;
        if (!empty($this->config->getSystemValue($this->appName))
            && array_key_exists($this->_verification, $this->config->getSystemValue($this->appName))) {
            $turnOff = $this->config->getSystemValue($this->appName)[$this->_verification];
        }
        return $turnOff === TRUE;
    }

    /**
     * Get the jwt header setting
     *
     * @return boolean
     */
    public function JwtHeader() {
        $header = "Authorization";
        if (!empty($this->config->getSystemValue($this->appName))
            && array_key_exists($this->_jwtHeader, $this->config->getSystemValue($this->appName))) {
            $header = $this->config->getSystemValue($this->appName)[$this->_jwtHeader];
        }
        return $header;
    }

    /**
     * Save the status settings
     *
     * @param boolean $value - error
     */
    public function SetSettingsError($value) {
        $this->config->setAppValue($this->appName, $this->_settingsError, $value);
    }

    /**
     * Get the status settings
     *
     * @return boolean
     */
    public function SettingsAreSuccessful() {
        return empty($this->config->getAppValue($this->appName, $this->_settingsError, ""));
    }


    /**
     * Additional data about formats
     *
     * @var array
     */
    public $formats = [
            "docx" => [ "mime" => "application/vnd.openxmlformats-officedocument.wordprocessingml.document", "type" => "text", "edit" => true, "def" => true ],
            "xlsx" => [ "mime" => "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet", "type" => "spreadsheet", "edit" => true, "def" => true ],
            "pptx" => [ "mime" => "application/vnd.openxmlformats-officedocument.presentationml.presentation", "type" => "presentation", "edit" => true, "def" => true ],
            "ppsx" => [ "mime" => "application/vnd.openxmlformats-officedocument.presentationml.slideshow", "type" => "presentation", "edit" => true, "def" => true ],
            "txt" => [ "mime" => "text/plain", "type" => "text", "edit" => true ],
            "csv" => [ "mime" => "text/csv", "type" => "spreadsheet", "edit" => true ],
            "odt" => [ "mime" => "application/vnd.oasis.opendocument.text", "type" => "text", "conv" => true ],
            "ods" => [ "mime" => "application/vnd.oasis.opendocument.spreadsheet", "type" => "spreadsheet", "conv" => true ],
            "odp" => [ "mime" => "application/vnd.oasis.opendocument.presentation", "type" => "presentation", "conv" => true ],
            "doc" => [ "mime" => "application/msword", "type" => "text", "conv" => true ],
            "xls" => [ "mime" => "application/vnd.ms-excel", "type" => "spreadsheet", "conv" => true ],
            "ppt" => [ "mime" => "application/vnd.ms-powerpoint", "type" => "presentation", "conv" => true ],
            "pps" => [ "mime" => "application/vnd.ms-powerpoint", "type" => "presentation", "conv" => true ],
            "epub" => [ "mime" => "application/epub+zip", "type" => "text", "conv" => true ],
            "rtf" => [ "mime" => "text/rtf", "type" => "text", "type" => "text", "conv" => true ],
            "mht" => [ "mime" => "message/rfc822", "conv" => true ],
            "html" => [ "mime" => "text/html", "type" => "text", "conv" => true ],
            "htm" => [ "mime" => "text/html", "type" => "text", "conv" => true ],
            "xps" => [ "mime" => "application/vnd.ms-xpsdocument", "type" => "text" ],
            "pdf" => [ "mime" => "application/pdf", "type" => "text" ],
            "djvu" => [ "mime" => "image/vnd.djvu", "type" => "text" ]
        ];
}
