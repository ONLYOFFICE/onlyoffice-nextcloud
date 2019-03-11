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
        $data = [
            "documentserver" => $this->config->GetDocumentServerUrl(),
            "documentserverInternal" => $this->config->GetDocumentServerInternalUrl(true),
            "storageUrl" => $this->config->GetStorageUrl(),
            "secret" => $this->config->GetDocumentServerSecret(),
            "currentServer" => $this->urlGenerator->getAbsoluteURL("/"),
            "formats" => $this->config->FormatsSetting(),
            "sameTab" => $this->config->GetSameTab(),
            "encryption" => $this->checkEncryptionModule(),
            "limitGroups" => $this->config->GetLimitGroups()
        ];
        return new TemplateResponse($this->appName, "settings", $data, "blank");
    }

    /**
     * Save app settings
     *
     * @param string $documentserver - document service address
     * @param string $documentserverInternal - document service address available from Nextcloud
     * @param string $storageUrl - Nextcloud address available from document server
     * @param string $secret - secret key for signature
     * @param array $defFormats - formats array with default action
     * @param array $editFormats - editable formats array
     * @param bool $sameTab - open in same tab
     * @param array $limitGroups - list of groups
     *
     * @return array
     */
    public function SaveSettings($documentserver,
                                    $documentserverInternal,
                                    $storageUrl,
                                    $secret,
                                    $defFormats,
                                    $editFormats,
                                    $sameTab,
                                    $limitGroups
                                    ) {
        $this->config->SetDocumentServerUrl($documentserver);
        $this->config->SetDocumentServerInternalUrl($documentserverInternal);
        $this->config->SetStorageUrl($storageUrl);
        $this->config->SetDocumentServerSecret($secret);

        $documentserver = $this->config->GetDocumentServerUrl();
        $error = NULL;
        if (!empty($documentserver)) {
            $error = $this->checkDocServiceUrl();
            $this->config->SetSettingsError($error);
        }

        $this->config->SetDefaultFormats($defFormats);
        $this->config->SetEditableFormats($editFormats);
        $this->config->SetSameTab($sameTab);
        $this->config->SetLimitGroups($limitGroups);

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
     * @PublicPage
     */
    public function GetSettings() {
        $result = [
            "formats" => $this->config->FormatsSetting(),
            "sameTab" => $this->config->GetSameTab()
        ];
        return $result;
    }


    /**
     * Checking document service location
     *
     * @return string
     */
    private function checkDocServiceUrl() {

        try {
            if (preg_match("/^https:\/\//i", $this->urlGenerator->getAbsoluteURL("/"))
                && preg_match("/^http:\/\//i", $this->config->GetDocumentServerUrl())) {
                throw new \Exception($this->trans->t("Mixed Active Content is not allowed. HTTPS address for Document Server is required."));
            }

        } catch (\Exception $e) {
            $this->logger->error("Protocol on check error: " . $e->getMessage(), array("app" => $this->appName));
            return $e->getMessage();
        }

        try {

            $documentService = new DocumentService($this->trans, $this->config);

            $healthcheckResponse = $documentService->HealthcheckRequest();
            if (!$healthcheckResponse) {
                throw new \Exception($this->trans->t("Bad healthcheck status"));
            }

        } catch (\Exception $e) {
            $this->logger->error("HealthcheckRequest on check error: " . $e->getMessage(), array("app" => $this->appName));
            return $e->getMessage();
        }

        try {

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

        } catch (\Exception $e) {
            $this->logger->error("CommandRequest on check error: " . $e->getMessage(), array("app" => $this->appName));
            return $e->getMessage();
        }

        $convertedFileUri;
        try {

            $hashUrl = $this->crypt->GetHash(["action" => "empty"]);
            $fileUrl = $this->urlGenerator->linkToRouteAbsolute($this->appName . ".callback.emptyfile", ["doc" => $hashUrl]);
            if (!empty($this->config->GetStorageUrl())) {
                $fileUrl = str_replace($this->urlGenerator->getAbsoluteURL("/"), $this->config->GetStorageUrl(), $fileUrl);
            }

            $convertedFileUri = $documentService->GetConvertedUri($fileUrl, "docx", "docx", "check_" . rand());

        } catch (\Exception $e) {
            $this->logger->error("GetConvertedUri on check error: " . $e->getMessage(), array("app" => $this->appName));
            return $e->getMessage();
        }

        try {
            $documentService->Request($convertedFileUri);
        } catch (\Exception $e) {
            $this->logger->error("Request converted file on check error: " . $convertedFileUri . " " . $e->getMessage(), array("app" => $this->appName));
            return $e->getMessage();
        }

        return "";
    }

    /**
     * Checking encryption enabled
     *
     * @return bool
    */
    private function checkEncryptionModule() {
        if (!App::isEnabled("encryption")) {
            return false;
        }
        if (!\OC::$server->getEncryptionManager()->isEnabled()) {
            return false;
        }

        $crypt = new \OCA\Encryption\Crypto\Crypt(\OC::$server->getLogger(), \OC::$server->getUserSession(), \OC::$server->getConfig(), \OC::$server->getL10N("encryption"));
        $util = new \OCA\Encryption\Util(new \OC\Files\View(), $crypt, \OC::$server->getLogger(), \OC::$server->getUserSession(), \OC::$server->getConfig(), \OC::$server->getUserManager());
        if ($util->isMasterKeyEnabled()) {
            return false;
        }

        return true;
    }
}
