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

namespace OCA\Onlyoffice\Controller;

use OCP\App;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IRequest;
use OCP\IURLGenerator;

use OCA\Onlyoffice\AppConfig;
use OCA\Onlyoffice\Crypt;
use OCA\Onlyoffice\DocumentService;

/**
 * Settings controller for the administration page
 */
class SettingsController extends Controller {

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
     * Application configuration
     *
     * @var OCA\Onlyoffice\AppConfig
     */
    private $config;

    /**
     * Url generator service
     *
     * @var IURLGenerator
     */
    private $urlGenerator;

    /**
     * Hash generator
     *
     * @var OCA\Onlyoffice\Crypt
     */
    private $crypt;

    /**
     * @param string $AppName - application name
     * @param IRequest $request - request object
     * @param IURLGenerator $urlGenerator - url generator service
     * @param IL10N $trans - l10n service
     * @param ILogger $logger - logger
     * @param OCA\Onlyoffice\AppConfig $config - application configuration
     * @param OCA\Onlyoffice\Crypt $crypt - hash generator
     */
    public function __construct($AppName,
                                    IRequest $request,
                                    IURLGenerator $urlGenerator,
                                    IL10N $trans,
                                    ILogger $logger,
                                    AppConfig $config,
                                    Crypt $crypt
                                    ) {
        parent::__construct($AppName, $request);

        $this->urlGenerator = $urlGenerator;
        $this->trans = $trans;
        $this->logger = $logger;
        $this->config = $config;
        $this->crypt = $crypt;
    }

    /**
     * Print config section
     *
     * @return TemplateResponse
     */
    public function index() {
        $formats = $this->formats();
        $defFormats = array();
        foreach ($formats as $format => $setting) {
            if (array_key_exists("edit", $setting) && $setting["edit"]) {
                $defFormats[$format] = array_key_exists("def", $setting) && $setting["def"];
            }
        }

        $data = [
            "documentserver" => $this->config->GetDocumentServerUrl(),
            "documentserverInternal" => $this->config->GetDocumentServerInternalUrl(true),
            "storageUrl" => $this->config->GetStorageUrl(),
            "secret" => $this->config->GetDocumentServerSecret(),
            "currentServer" => $this->urlGenerator->getAbsoluteURL("/"),
            "defFormats" => $defFormats,
            "sameTab" => $this->config->GetSameTab(),
            "encryption" => $this->checkEncryptionModule()
        ];
        return new TemplateResponse($this->appName, "settings", $data, "blank");
    }

    /**
     * Save app settings
     *
     * @param string $documentserver - document service address
     * @param string $documentserverInternal - document service address available from ownCloud
     * @param string $storageUrl - ownCloud address available from document server
     * @param string $secret - secret key for signature
     * @param string $defFormats - formats array with default action
     *
     * @return array
     */
    public function SaveSettings($documentserver,
                                    $documentserverInternal,
                                    $storageUrl,
                                    $secret,
                                    $defFormats,
                                    $sameTab
                                    ) {
        $this->config->SetDocumentServerUrl($documentserver);
        $this->config->SetDocumentServerInternalUrl($documentserverInternal);
        $this->config->SetStorageUrl($storageUrl);
        $this->config->SetDocumentServerSecret($secret);

        $documentserver = $this->config->GetDocumentServerUrl();
        if (!empty($documentserver)) {
            $error = $this->checkDocServiceUrl();
            $this->config->SetSettingsError($error);
        }

        $this->config->DropSKey();

        $this->config->SetDefaultFormats($defFormats);
        $this->config->SetSameTab($sameTab);

        if ($this->checkEncryptionModule()) {
            $this->logger->info("SaveSettings when encryption is enabled", array("app" => $this->appName));
        }

        return [
            "documentserver" => $this->config->GetDocumentServerUrl(),
            "documentserverInternal" => $this->config->GetDocumentServerInternalUrl(true),
            "storageUrl" => $this->config->GetStorageUrl(),
            "secret" => $this->config->GetDocumentServerSecret(),
            "error" => $error
            ];
    }

    /**
     * Get app settings
     *
     * @return array
     *
     * @NoAdminRequired
     */
    public function GetSettings() {
        $result = [
            "formats" => $this->formats(),
            "sameTab" => $this->config->GetSameTab()
        ];
        return $result;
    }

    /**
     * Get supported formats
     *
     * @return array
     *
     * @NoAdminRequired
     */
    private function formats() {
        $defFormats = $this->config->GetDefaultFormats();

        $result = $this->config->formats;
        foreach ($result as $format => $setting) {
            if (array_key_exists("edit", $setting) && $setting["edit"]
                && array_key_exists($format, $defFormats)) {
                $result[$format]["def"] = ($defFormats[$format] === true || $defFormats[$format] === "true");
            }
        }

        return $result;
    }


    /**
     * Checking document service location
     *
     * @param string $documentServer - document service address
     *
     * @return string
     */
    private function checkDocServiceUrl() {

        try {
            if (substr($this->urlGenerator->getAbsoluteURL("/"), 0, strlen("https")) === "https"
                && substr($this->config->GetDocumentServerUrl("/"), 0, strlen("https")) !== "https") {
                throw new \Exception($this->trans->t("Mixed Active Content is not allowed. HTTPS address for Document Server is required."));
            }

            $documentService = new DocumentService($this->trans, $this->config);

            $commandResponse = $documentService->CommandRequest("version");

            $this->logger->debug("CommandRequest on check: " . json_encode($commandResponse), array("app" => $this->appName));

            if (empty($commandResponse)) {
                throw new \Exception($this->trans->t("Error occurred in the document service"));
            }

            $version = floatval($commandResponse->version);
            if ($version > 0.0 && $version < 4.2) {
                throw new \Exception($this->trans->t("Not supported version"));
            }

            $hashUrl = $this->crypt->GetHash(["action" => "empty"]);
            $fileUrl = $this->urlGenerator->linkToRouteAbsolute($this->appName . ".callback.emptyfile", ["doc" => $hashUrl]);
            if (!empty($this->config->GetStorageUrl())) {
                $fileUrl = str_replace($this->urlGenerator->getAbsoluteURL("/"), $this->config->GetStorageUrl(), $fileUrl);
            }

            $documentService->GetConvertedUri($fileUrl, "docx", "docx", "check_" . rand());

        } catch (\Exception $e) {
            $this->logger->error("CommandRequest on check error: " . $e->getMessage(), array("app" => $this->appName));
            return $e->getMessage();
        }

        return "";
    }

    /**
     * Checking encryption enabled
    */
    private function checkEncryptionModule() {
        if (!App::isEnabled("encryption")) {
            return false;
        }
        if (!\OC::$server->getEncryptionManager()->isEnabled()) {
            return false;
        }

        $crypt = new \OCA\Encryption\Crypto\Crypt(\OC::$server->getLogger(), \OC::$server->getUserSession(), \OC::$server->getConfig(), \OC::$server->getL10N('encryption'));
        $util = new \OCA\Encryption\Util(new \OC\Files\View(), $crypt, \OC::$server->getLogger(), \OC::$server->getUserSession(), \OC::$server->getConfig(), \OC::$server->getUserManager());
        if ($util->isMasterKeyEnabled()) {
            return false;
        }

        return true;
    }
}
