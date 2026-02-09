<?php
/**
 *
 * (c) Copyright Ascensio System SIA 2026
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
use Exception;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IConfig;
use Psr\Log\LoggerInterface;
use OCA\Onlyoffice\AppInfo\Application;
use OCP\IAppConfig;

/**
 * Application configutarion
 *
 * @package OCA\Onlyoffice
 */
class AppConfig {
    /**
     * The config key for the demo server
     */
    private string $_demo = "demo";

    /**
     * The config key for the document server address
     */
    private string $_documentserver = "DocumentServerUrl";

    /**
     * The config key for the document server address available from Nextcloud
     */
    private string $_documentserverInternal = "DocumentServerInternalUrl";

    /**
     * The config key for the Nextcloud address available from document server
     */
    private string $_storageUrl = "StorageUrl";

    /**
     * The config key for the secret key
     */
    private string $_cryptSecret = "secret";

    /**
     * The config key for the default formats
     */
    private string $_defFormats = "defFormats";

    /**
     * The config key for the editable formats
     */
    private string $_editFormats = "editFormats";

    /**
     * The config key for the setting same tab
     */
    private string $_sameTab = "sameTab";

    /**
     * The config key for the enabling sharring in a same tab
     */
    private string $_enableSharing = "enableSharing";

    /**
     * The config key for the generate preview
     */
    private string $_preview = "preview";

    /**
     * The config key for the advanced
     */
    private string $_advanced = "advanced";

    /**
     * The config key for the cronChecker
     */
    private string $_cronChecker = "cronChecker";

    /**
     * The config key for the e-mail notifications
     */
    private string $_emailNotifications = "emailNotifications";

    /**
     * The config key for the keep versions history
     */
    private string $_versionHistory = "versionHistory";

    /**
     * The config key for the protection
     */
    private string $_protection = "protection";

    /**
     * The config key for the chat display setting
     */
    private string $_customizationChat = "customizationChat";

    /**
     * The config key for display the header more compact setting
     */
    private string $_customizationCompactHeader = "customizationCompactHeader";

    /**
     * The config key for the feedback display setting
     */
    private string $_customizationFeedback = "customizationFeedback";

    /**
     * The config key for the forcesave setting
     */
    private string $_customizationForcesave = "customizationForcesave";

    /**
     * The config key for the live view on share setting
     */
    private string $_liveViewOnShare = "liveViewOnShare";

    /**
     * The config key for the help display setting
     */
    private string $_customizationHelp = "customizationHelp";

    /**
     * The config key for the review mode setting
     */
    private string $_customizationReviewDisplay = "customizationReviewDisplay";

    /**
     * The config key for the theme setting
     */
    private string $_customizationTheme = "customizationTheme";

    /**
     * Display name of the unknown author
     */
    private string $_unknownAuthor = "unknownAuthor";

    /**
     * The config key for the setting limit groups
     */
    private string $_groups = "groups";

    /**
     * The config key for the verification
     */
    private string $_verification = "verify_peer_off";

    /**
     * The config key for the secret key in jwt
     */
    private string $_jwtSecret = "jwt_secret";

    /**
     * The config key for the jwt header
     */
    private string $_jwtHeader = "jwt_header";

    /**
     * The config key for the allowable leeway in Jwt checks
     */
    private string $_jwtLeeway = "jwt_leeway";

    /**
     * The config key for the settings error
     */
    private string $_settingsError = "settings_error";

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
     */
    private string $_editors_check_interval = "editors_check_interval";

    /**
     * The config key for the JWT expiration
     */
    private string $_jwt_expiration = "jwt_expiration";

    /**
     * The config key for store cache
     */
    private readonly ICache $cache;

    public function __construct(
        private readonly string $appName,
        private readonly IAppConfig $appConfig,
        private readonly IConfig $config,
        private readonly LoggerInterface $logger,
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
     */
    public function selectDemo($value): bool {
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

        $this->appConfig->setValueString($this->appName, $this->_demo, json_encode($data));
        return true;
    }

    /**
     * Get demo data
     *
     * @return array
     */
    public function getDemoData() {
        $data = $this->appConfig->getValueString($this->appName, $this->_demo, "");

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
     */
    public function useDemo(): bool {
        return $this->getDemoData()["enabled"] === true;
    }

    /**
     * Save the document service address to the application configuration
     *
     * @param string $documentServer - document service address
     */
    public function setDocumentServerUrl($documentServer): void {
        $documentServer = trim($documentServer);
        if (strlen($documentServer) > 0) {
            $documentServer = rtrim($documentServer, "/") . "/";
            if (!preg_match("/(^https?:\/\/)|^\//i", $documentServer)) {
                $documentServer = "http://" . $documentServer;
            }
        }

        $this->logger->info("setDocumentServerUrl: $documentServer", ["app" => $this->appName]);

        $this->appConfig->setValueString($this->appName, $this->_documentserver, $documentServer);
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

        $url = $this->appConfig->getValueString($this->appName, $this->_documentserver, "");
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
    public function setDocumentServerInternalUrl($documentServerInternal): void {
        $documentServerInternal = rtrim(trim($documentServerInternal), "/");
        if (strlen($documentServerInternal) > 0) {
            $documentServerInternal = $documentServerInternal . "/";
            if (!preg_match("/^https?:\/\//i", $documentServerInternal)) {
                $documentServerInternal = "http://" . $documentServerInternal;
            }
        }

        $this->logger->info("setDocumentServerInternalUrl: $documentServerInternal", ["app" => $this->appName]);

        $this->appConfig->setValueString($this->appName, $this->_documentserverInternal, $documentServerInternal);
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

        $url = $this->appConfig->getValueString($this->appName, $this->_documentserverInternal, "");
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
    public function setStorageUrl($storageUrl): void {
        $storageUrl = rtrim(trim((string) $storageUrl), "/");
        if (strlen($storageUrl) > 0) {
            $storageUrl = $storageUrl . "/";
            if (!preg_match("/^https?:\/\//i", $storageUrl)) {
                $storageUrl = "http://" . $storageUrl;
            }
        }

        $this->logger->info("setStorageUrl: $storageUrl", ["app" => $this->appName]);

        $this->appConfig->setValueString($this->appName, $this->_storageUrl, $storageUrl);
    }

    /**
     * Get the Nextcloud address available from document server from the application configuration
     *
     * @return string
     */
    public function getStorageUrl() {
        $url = $this->appConfig->getValueString($this->appName, $this->_storageUrl, "");
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
    public function setDocumentServerSecret($secret): void {
        $secret = trim($secret);
        if (empty($secret)) {
            $this->logger->info("Clear secret key", ["app" => $this->appName]);
        } else {
            $this->logger->info("Set secret key", ["app" => $this->appName]);
        }

        $this->appConfig->setValueString($this->appName, $this->_jwtSecret, $secret);
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

        $secret = $this->appConfig->getValueString($this->appName, $this->_jwtSecret, "");
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
    public function setDefaultFormats($formats): void {
        $value = json_encode($formats);
        $this->logger->info("Set default formats: $value", ["app" => $this->appName]);

        $this->appConfig->setValueString($this->appName, $this->_defFormats, $value);
    }

    /**
     * Get an array of formats with default action
     *
     * @return array
     */
    private function getDefaultFormats() {
        $value = $this->appConfig->getValueString($this->appName, $this->_defFormats, "");
        if (empty($value)) {
            return [];
        }
        return json_decode($value, true);
    }

    /**
     * Save an array of formats that is opened for editing
     *
     * @param array $formats - formats with status
     */
    public function setEditableFormats($formats): void {
        $value = json_encode($formats);
        $this->logger->info("Set editing formats: $value", ["app" => $this->appName]);

        $this->appConfig->setValueString($this->appName, $this->_editFormats, $value);
    }

    /**
     * Get an array of formats opening for editing
     *
     * @return array
     */
    private function getEditableFormats() {
        $value = $this->appConfig->getValueString($this->appName, $this->_editFormats, "");
        if (empty($value)) {
            return [];
        }
        return json_decode($value, true);
    }

    /**
     * Save the opening setting in a same tab
     *
     * @param bool $value - same tab
     */
    public function setSameTab($value): void {
        $this->logger->info("Set opening in a same tab: " . json_encode($value), ["app" => $this->appName]);

        if ($value) {
            $this->setEnableSharing(false);
        }

        $this->appConfig->setValueString($this->appName, $this->_sameTab, json_encode($value));
    }

    /**
     * Get the opening setting in a same tab
     */
    public function getSameTab(): bool {
        return $this->appConfig->getValueString($this->appName, $this->_sameTab, "true") === "true";
    }

    /**
     * Save the enable sharing setting
     *
     * @param bool $value - enable sharing
     */
    public function setEnableSharing($value): void {
        $this->logger->info("Set enable sharing: " . json_encode($value), ["app" => $this->appName]);

        $this->appConfig->setValueString($this->appName, $this->_enableSharing, json_encode($value));
    }

    /**
     * Get the enable sharing setting
     */
    public function getEnableSharing(): bool {
        return $this->appConfig->getValueString($this->appName, $this->_enableSharing, "false") === "true";
    }

    /**
     * Save generate preview setting
     *
     * @param bool $value - preview
     */
    public function setPreview($value): void {
        $this->logger->info("Set generate preview: " . json_encode($value), ["app" => $this->appName]);

        $this->appConfig->setValueString($this->appName, $this->_preview, json_encode($value));
    }

    /**
     * Get advanced setting
     */
    public function getAdvanced(): bool {
        return $this->appConfig->getValueString($this->appName, $this->_advanced, "false") === "true";
    }

    /**
     * Save advanced setting
     *
     * @param bool $value - advanced
     */
    public function setAdvanced($value): void {
        $this->logger->info("Set advanced: " . json_encode($value), ["app" => $this->appName]);

        $this->appConfig->setValueString($this->appName, $this->_advanced, json_encode($value));
    }

    /**
     * Get cron checker setting
     */
    public function getCronChecker(): bool {
        return $this->appConfig->getValueString($this->appName, $this->_cronChecker, "true") !== "false";
    }

    /**
     * Save cron checker setting
     *
     * @param bool $value - cronChecker
     */
    public function setCronChecker($value): void {
        $this->logger->info("Set cron checker: " . json_encode($value), ["app" => $this->appName]);

        $this->appConfig->setValueString($this->appName, $this->_cronChecker, json_encode($value));
    }

    /**
     * Get e-mail notifications setting
     */
    public function getEmailNotifications(): bool {
        return $this->appConfig->getValueString($this->appName, $this->_emailNotifications, "true") !== "false";
    }

    /**
     * Save e-mail notifications setting
     *
     * @param bool $value - emailNotifications
     */
    public function setEmailNotifications($value): void {
        $this->logger->info("Set e-mail notifications: " . json_encode($value), ["app" => $this->appName]);

        $this->appConfig->setValueString($this->appName, $this->_emailNotifications, json_encode($value));
    }

    /**
     * Get generate preview setting
     */
    public function getPreview(): bool {
        return $this->appConfig->getValueString($this->appName, $this->_preview, "true") === "true";
    }

    /**
     * Save keep versions history
     *
     * @param bool $value - version history
     */
    public function setVersionHistory($value): void {
        $this->logger->info("Set keep versions history: " . json_encode($value), ["app" => $this->appName]);

        $this->appConfig->setValueString($this->appName, $this->_versionHistory, json_encode($value));
    }

    /**
     * Get keep versions history
     */
    public function getVersionHistory(): bool {
        return $this->appConfig->getValueString($this->appName, $this->_versionHistory, "true") === "true";
    }

    /**
     * Save protection
     *
     * @param bool $value - version history
     */
    public function setProtection($value): void {
        $this->logger->info("Set protection: " . $value, ["app" => $this->appName]);

        $this->appConfig->setValueString($this->appName, $this->_protection, $value);
    }

    /**
     * Get protection
     */
    public function getProtection(): string {
        $value = $this->appConfig->getValueString($this->appName, $this->_protection, "owner");
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
    public function setCustomizationChat($value): void {
        $this->logger->info("Set chat display: " . json_encode($value), ["app" => $this->appName]);

        $this->appConfig->setValueString($this->appName, $this->_customizationChat, json_encode($value));
    }

    /**
     * Get chat display setting
     */
    public function getCustomizationChat(): bool {
        return $this->appConfig->getValueString($this->appName, $this->_customizationChat, "true") === "true";
    }

    /**
     * Save compact header setting
     *
     * @param bool $value - display compact header
     */
    public function setCustomizationCompactHeader($value): void {
        $this->logger->info("Set compact header display: " . json_encode($value), ["app" => $this->appName]);

        $this->appConfig->setValueString($this->appName, $this->_customizationCompactHeader, json_encode($value));
    }

    /**
     * Get compact header setting
     */
    public function getCustomizationCompactHeader(): bool {
        return $this->appConfig->getValueString($this->appName, $this->_customizationCompactHeader, "true") === "true";
    }

    /**
     * Save feedback display setting
     *
     * @param bool $value - display feedback
     */
    public function setCustomizationFeedback($value): void {
        $this->logger->info("Set feedback display: " . json_encode($value), ["app" => $this->appName]);

        $this->appConfig->setValueString($this->appName, $this->_customizationFeedback, json_encode($value));
    }

    /**
     * Get feedback display setting
     */
    public function getCustomizationFeedback(): bool {
        return $this->appConfig->getValueString($this->appName, $this->_customizationFeedback, "true") === "true";
    }

    /**
     * Save forcesave setting
     *
     * @param bool $value - forcesave
     */
    public function setCustomizationForcesave($value): void {
        $this->logger->info("Set forcesave: " . json_encode($value), ["app" => $this->appName]);

        $this->appConfig->setValueString($this->appName, $this->_customizationForcesave, json_encode($value));
    }

    /**
     * Get forcesave setting
     */
    public function getCustomizationForcesave(): bool {
        return $this->appConfig->getValueString($this->appName, $this->_customizationForcesave, "false") === "true";
    }

    /**
     * Save live view on share setting
     *
     * @param bool $value - live view on share
     */
    public function setLiveViewOnShare($value): void {
        $this->logger->info("Set live view on share: " . json_encode($value), ["app" => $this->appName]);

        $this->appConfig->setValueString($this->appName, $this->_liveViewOnShare, json_encode($value));
    }

    /**
     * Get live view on share setting
     */
    public function getLiveViewOnShare(): bool {
        return $this->appConfig->getValueString($this->appName, $this->_liveViewOnShare, "false") === "true";
    }

    /**
     * Save help display setting
     *
     * @param bool $value - display help
     */
    public function setCustomizationHelp($value): void {
        $this->logger->info("Set help display: " . json_encode($value), ["app" => $this->appName]);

        $this->appConfig->setValueString($this->appName, $this->_customizationHelp, json_encode($value));
    }

    /**
     * Get help display setting
     */
    public function getCustomizationHelp(): bool {
        return $this->appConfig->getValueString($this->appName, $this->_customizationHelp, "true") === "true";
    }

    /**
     * Save review viewing mode setting
     *
     * @param string $value - review mode
     */
    public function setCustomizationReviewDisplay(string $value): void {
        $this->logger->info("Set review mode: " . $value, ["app" => $this->appName]);

        $this->appConfig->setValueString($this->appName, $this->_customizationReviewDisplay, $value);
    }

    /**
     * Get review viewing mode setting
     */
    public function getCustomizationReviewDisplay(): string {
        $value = $this->appConfig->getValueString($this->appName, $this->_customizationReviewDisplay, "original");
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
    public function setCustomizationTheme(string $value): void {
        $this->logger->info("Set theme: " . $value, ["app" => $this->appName]);

        $this->appConfig->setValueString($this->appName, $this->_customizationTheme, $value);
    }

    /**
     * Get theme setting
     *
     * @param bool $realValue - get real value (for example, for settings)
     * @return string
     */
    public function getCustomizationTheme($realValue = false) {
        $value = $this->appConfig->getValueString($this->appName, $this->_customizationTheme, "theme-system");
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
            $user = \OCP\Server::get(\OCP\IUserSession::class)->getUser();

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
    public function setUnknownAuthor($value): void {
        $this->logger->info("Set unknownAuthor: " . trim($value), ["app" => $this->appName]);
        $this->appConfig->setValueString($this->appName, $this->_unknownAuthor, trim($value));
    }

    /**
     * Get unknownAuthor setting
     *
     * @return string
     */
    public function getUnknownAuthor() {
        return $this->appConfig->getValueString($this->appName, $this->_unknownAuthor, "");
    }

    /**
     * Save watermark settings
     *
     * @param array $settings - watermark settings
     */
    public function setWatermarkSettings(array $settings): void {
        $this->logger->info("Set watermark enabled: " . $settings["enabled"], ["app" => $this->appName]);

        if ($settings["enabled"] !== "true") {
            $this->appConfig->setValueString(AppConfig::WATERMARK_APP_NAMESPACE, "watermark_enabled", "no");
            return;
        }

        $this->appConfig->setValueString(AppConfig::WATERMARK_APP_NAMESPACE, "watermark_text", trim((string) $settings["text"]));

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
                $settings[$key] = [];
            }
            $value = $settings[$key] === "true" ? "yes" : "no";
            $this->appConfig->setValueString(AppConfig::WATERMARK_APP_NAMESPACE, "watermark_" . $key, $value);
        }

        $watermarkLists = [
            "allGroupsList",
            "allTagsList",
            "linkTagsList",
        ];
        foreach ($watermarkLists as $key) {
            if (empty($settings[$key])) {
                $settings[$key] = [];
            }
            $value = implode(",", $settings[$key]);
            $this->appConfig->setValueString(AppConfig::WATERMARK_APP_NAMESPACE, "watermark_" . $key, $value);
        }
    }

    /**
     * Get watermark settings
     *
     * @return bool|array
     */
    public function getWatermarkSettings(): array {
        $result = [
            "text" => $this->appConfig->getValueString(AppConfig::WATERMARK_APP_NAMESPACE, "watermark_text", "{userId}, {date}"),
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

        $trueResult = ["on", "yes", "true"];
        foreach ($watermarkLabels as $key) {
            $value = $this->appConfig->getValueString(AppConfig::WATERMARK_APP_NAMESPACE, "watermark_" . $key, "no");
            $result[$key] = in_array($value, $trueResult);
        }

        $watermarkLists = [
            "allGroupsList",
            "allTagsList",
            "linkTagsList",
        ];

        foreach ($watermarkLists as $key) {
            $value = $this->appConfig->getValueString(AppConfig::WATERMARK_APP_NAMESPACE, "watermark_" . $key, "");
            $result[$key] = !empty($value) ? explode(",", $value) : [];
        }

        return $result;
    }

    /**
     * Save the list of groups
     *
     * @param array $groups - the list of groups
     */
    public function setLimitGroups($groups): void {
        if (!is_array($groups)) {
            $groups = [];
        }
        $value = json_encode($groups);
        $this->logger->info("Set groups: $value", ["app" => $this->appName]);

        $this->appConfig->setValueString($this->appName, $this->_groups, $value);
    }

    /**
     * Get the list of groups
     */
    public function getLimitGroups(): array {
        $value = $this->appConfig->getValueString($this->appName, $this->_groups, "");
        if (empty($value)) {
            return [];
        }
        $groups = json_decode($value, true);
        if (!is_array($groups)) {
            $groups = [];
        }
        return $groups;
    }

    /**
     * Check access for group
     *
     * @param string $userId - user identifier
     */
    public function isUserAllowedToUse($userId = null): bool {
        // no user -> no
        $userSession = \OCP\Server::get(\OCP\IUserSession::class);
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
            $user = \OCP\Server::get(\OCP\IUserManager::class)->get($userId);
            if (empty($user)) {
                return false;
            }
        }

        foreach ($groups as $groupName) {
            // group unknown -> error and allow nobody
            $group = \OCP\Server::get(\OCP\IGroupManager::class)->get($groupName);
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
     * @param bool $verifyPeerOff parameter verification setting
     */
    public function setVerifyPeerOff($verifyPeerOff): void {
        $this->logger->info("setVerifyPeerOff " . json_encode($verifyPeerOff), ["app" => $this->appName]);

        $this->appConfig->setValueString($this->appName, $this->_verification, json_encode($verifyPeerOff));
    }

    /**
     * Get the document service verification setting to the application configuration
     */
    public function getVerifyPeerOff(): bool {
        $turnOff = $this->appConfig->getValueString($this->appName, $this->_verification, "");

        if (!empty($turnOff)) {
            return $turnOff === "true";
        }

        return $this->getSystemValue($this->_verification) === "true";
    }

    /**
     * Get the limit on size document when generating thumbnails
     */
    public function getLimitThumbSize(): int {
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

        $header = $this->appConfig->getValueString($this->appName, $this->_jwtHeader, "");
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
    public function setJwtHeader($value): void {
        $value = trim($value);
        if (empty($value)) {
            $this->logger->info("Clear header key", ["app" => $this->appName]);
        } else {
            $this->logger->info("Set header key " . $value, ["app" => $this->appName]);
        }

        $this->appConfig->setValueString($this->appName, $this->_jwtHeader, $value);
    }

    /**
     * Get the Jwt Leeway
     */
    public function getJwtLeeway(): int {
        return (integer)$this->getSystemValue($this->_jwtLeeway);
    }

    /**
     * Save the status settings
     *
     * @param string $value - error
     */
    public function setSettingsError($value): void {
        $this->appConfig->setValueString($this->appName, $this->_settingsError, $value);
    }

    /**
     * Get the error text of the status settings
     *
     * @param string $value - error
     */
    public function getSettingsError() {
        return $this->appConfig->getValueString($this->appName, $this->_settingsError, "");
    }

    /**
     * Get the status settings
     */
    public function settingsAreSuccessful(): bool {
        return empty($this->getSettingsError());
    }

    /**
     * Get supported formats
     */
    #[NoAdminRequired]
    public function formatsSetting(): array {
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
    public function setCustomizationMacros($value): void {
        $this->logger->info("Set macros enabled: " . json_encode($value), ["app" => $this->appName]);

        $this->appConfig->setValueString($this->appName, $this->_customizationMacros, json_encode($value));
    }

    /**
     * Get macros setting
     */
    public function getCustomizationMacros(): bool {
        return $this->appConfig->getValueString($this->appName, $this->_customizationMacros, "true") === "true";
    }

    /**
     * Save plugins setting
     *
     * @param bool $value - enable macros
     */
    public function setCustomizationPlugins($value): void {
        $this->logger->info("Set plugins enabled: " . json_encode($value), ["app" => $this->appName]);

        $this->appConfig->setValueString($this->appName, $this->_customizationPlugins, json_encode($value));
    }

    /**
     * Get plugins setting
     */
    public function getCustomizationPlugins(): bool {
        return $this->appConfig->getValueString($this->appName, $this->_customizationPlugins, "true") === "true";
    }

    /**
     * Get the disable download value
     */
    public function getDisableDownload(): bool {
        return (bool)$this->getSystemValue($this->_disableDownload);
    }
    /**
     * Get the editors check interval
     */
    public function getEditorsCheckInterval(): int {
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
        return $interval;
    }

    /**
     * Get the JWT expiration
     */
    public function getJwtExpiration(): int {
        $jwtExp = $this->getSystemValue($this->_jwt_expiration);

        if (empty($jwtExp)) {
            return 5;
        }
        return (integer)$jwtExp;
    }

    /**
     * Get ONLYOFFICE formats list
     */
    private function buildOnlyofficeFormats(): array {
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
                            "comment" => in_array("comment", $onlyOfficeFormat["actions"]),
                            "saveas" => $onlyOfficeFormat["convert"],
                            "review" => in_array("review", $onlyOfficeFormat["actions"]),
                            "modifyFilter" => in_array("customfilter", $onlyOfficeFormat["actions"]),
                        ];
                        if (isset($additionalFormats[$onlyOfficeFormat["name"]])) {
                            $result[$onlyOfficeFormat["name"]] = array_merge($result[$onlyOfficeFormat["name"]], $additionalFormats[$onlyOfficeFormat["name"]]);
                        }
                    }
                }
            }
            return $result;
        } catch (Exception $e) {
            $this->logger->error("Format matrix error", ['exception' => $e]);
            return [];
        }
    }

    /**
     * Get the additional format attributes
     */
    private function getAdditionalFormatAttributes(): array {
        return [
            "docx" => [
                "def" => true,
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
            ],
            "xlsx" => [
                "def" => true,
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
    }

    /**
     * Get the formats list from cache or file
     *
     * @return array
     */
    public function getFormats(): mixed {
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
    private array $DEMO_PARAM = [
        "ADDR" => "https://onlinedocs.docs.onlyoffice.com/",
        "HEADER" => "AuthorizationJWT",
        "SECRET" => "sn2puSUF7muF5Jas",
        "TRIAL" => 30
    ];
}
