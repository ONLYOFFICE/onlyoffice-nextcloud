<?php
/**
 *
 * (c) Copyright Ascensio System SIA 2020
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
     * @var AppConfig
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
     * @var Crypt
     */
    private $crypt;

    /**
     * @param string $AppName - application name
     * @param IRequest $request - request object
     * @param IURLGenerator $urlGenerator - url generator service
     * @param IL10N $trans - l10n service
     * @param ILogger $logger - logger
     * @param AppConfig $config - application configuration
     * @param Crypt $crypt - hash generator
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
            "documentserver" => $this->config->GetDocumentServerUrl(true),
            "documentserverInternal" => $this->config->GetDocumentServerInternalUrl(true),
            "storageUrl" => $this->config->GetStorageUrl(),
            "verifyPeerOff" => $this->config->GetVerifyPeerOff(),
            "secret" => $this->config->GetDocumentServerSecret(true),
            "demo" => $this->config->GetDemoData(),
            "currentServer" => $this->urlGenerator->getAbsoluteURL("/"),
            "formats" => $this->config->FormatsSetting(),
            "sameTab" => $this->config->GetSameTab(),
            "limitGroups" => $this->config->GetLimitGroups(),
            "chat" => $this->config->GetCustomizationChat(),
            "compactHeader" => $this->config->GetCustomizationCompactHeader(),
            "feedback" => $this->config->GetCustomizationFeedback(),
            "forcesave" => $this->config->GetCustomizationForcesave(),
            "help" => $this->config->GetCustomizationHelp(),
            "toolbarNoTabs" => $this->config->GetCustomizationToolbarNoTabs(),
            "successful" => $this->config->SettingsAreSuccessful(),
            "watermark" => $this->config->GetWatermarkSettings(),
            "tagsEnabled" => App::isEnabled("systemtags"),
            "reviewDisplay" => $this->config->GetCustomizationReviewDisplay()
        ];
        return new TemplateResponse($this->appName, "settings", $data, "blank");
    }

    /**
     * Save address settings
     *
     * @param string $documentserver - document service address
     * @param string $documentserverInternal - document service address available from Nextcloud
     * @param string $storageUrl - Nextcloud address available from document server
     * @param bool $verifyPeerOff - parameter verification setting
     * @param string $secret - secret key for signature
     * @param bool $demo - use demo server
     *
     * @return array
     */
    public function SaveAddress($documentserver,
                                    $documentserverInternal,
                                    $storageUrl,
                                    $verifyPeerOff,
                                    $secret,
                                    $demo
                                    ) {
        $error = null;
        if (!$this->config->SelectDemo($demo === true)) {
            $error = $this->trans->t("The 30-day test period is over, you can no longer connect to demo ONLYOFFICE Document Server.");
        }
        if ($demo !== true) {
            $this->config->SetDocumentServerUrl($documentserver);
            $this->config->SetVerifyPeerOff($verifyPeerOff);
            $this->config->SetDocumentServerInternalUrl($documentserverInternal);
            $this->config->SetDocumentServerSecret($secret);
        }
        $this->config->SetStorageUrl($storageUrl);

        $version = null;
        if (empty($error)) {
            $documentserver = $this->config->GetDocumentServerUrl();
            if (!empty($documentserver)) {
                list ($error, $version) = $this->checkDocServiceUrl();
                $this->config->SetSettingsError($error);
            }
        }

        return [
            "documentserver" => $this->config->GetDocumentServerUrl(true),
            "verifyPeerOff" => $this->config->GetVerifyPeerOff(),
            "documentserverInternal" => $this->config->GetDocumentServerInternalUrl(true),
            "storageUrl" => $this->config->GetStorageUrl(),
            "secret" => $this->config->GetDocumentServerSecret(true),
            "error" => $error,
            "version" => $version,
            ];
    }

    /**
     * Save common settings
     *
     * @param array $defFormats - formats array with default action
     * @param array $editFormats - editable formats array
     * @param bool $sameTab - open in the same tab
     * @param array $limitGroups - list of groups
     * @param bool $chat - display chat
     * @param bool $compactHeader - display compact header
     * @param bool $feedback - display feedback
     * @param bool $forcesave - forcesave
     * @param bool $help - display help
     * @param bool $toolbarNoTabs - display toolbar tab
     * @param string $reviewDisplay - review viewing mode
     *
     * @return array
     */
    public function SaveCommon($defFormats,
                                    $editFormats,
                                    $sameTab,
                                    $limitGroups,
                                    $chat,
                                    $compactHeader,
                                    $feedback,
                                    $forcesave,
                                    $help,
                                    $toolbarNoTabs,
                                    $reviewDisplay
                                    ) {

        $this->config->SetDefaultFormats($defFormats);
        $this->config->SetEditableFormats($editFormats);
        $this->config->SetSameTab($sameTab);
        $this->config->SetLimitGroups($limitGroups);
        $this->config->SetCustomizationChat($chat);
        $this->config->SetCustomizationCompactHeader($compactHeader);
        $this->config->SetCustomizationFeedback($feedback);
        $this->config->SetCustomizationForcesave($forcesave);
        $this->config->SetCustomizationHelp($help);
        $this->config->SetCustomizationToolbarNoTabs($toolbarNoTabs);
        $this->config->SetCustomizationReviewDisplay($reviewDisplay);

        return [
            ];
    }

    /**
     * Save watermark settings
     *
     * @param array $settings - watermark settings
     *
     * @return array
     */
    public function SaveWatermark($settings) {

        if ($settings["enabled"] === "true") {
            $settings["text"] = trim($settings["text"]);
            if (empty($settings["text"])) {
                $settings["text"] = $this->trans->t("DO NOT SHARE THIS") . " {userId} {date}";
            }
        }

        $this->config->SetWatermarkSettings($settings);

        return [
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
     * @return array
     */
    private function checkDocServiceUrl() {
        $version = null;

        try {

            if (preg_match("/^https:\/\//i", $this->urlGenerator->getAbsoluteURL("/"))
                && preg_match("/^http:\/\//i", $this->config->GetDocumentServerUrl())) {
                throw new \Exception($this->trans->t("Mixed Active Content is not allowed. HTTPS address for Document Server is required."));
            }

        } catch (\Exception $e) {
            $this->logger->logException($e, ["message" => "Protocol on check error", "app" => $this->appName]);
            return [$e->getMessage(), $version];
        }

        try {

            $documentService = new DocumentService($this->trans, $this->config);

            $healthcheckResponse = $documentService->HealthcheckRequest();
            if (!$healthcheckResponse) {
                throw new \Exception($this->trans->t("Bad healthcheck status"));
            }

        } catch (\Exception $e) {
            $this->logger->logException($e, ["message" => "HealthcheckRequest on check error", "app" => $this->appName]);
            return [$e->getMessage(), $version];
        }

        try {

            $documentService = new DocumentService($this->trans, $this->config);

            $commandResponse = $documentService->CommandRequest("version");

            $this->logger->debug("CommandRequest on check: " . json_encode($commandResponse), ["app" => $this->appName]);

            if (empty($commandResponse)) {
                throw new \Exception($this->trans->t("Error occurred in the document service"));
            }

            $version = $commandResponse->version;

        } catch (\Exception $e) {
            $this->logger->logException($e, ["message" => "CommandRequest on check error", "app" => $this->appName]);
            return [$e->getMessage(), $version];
        }

        $convertedFileUri = null;
        try {

            $hashUrl = $this->crypt->GetHash(["action" => "empty"]);
            $fileUrl = $this->urlGenerator->linkToRouteAbsolute($this->appName . ".callback.emptyfile", ["doc" => $hashUrl]);
            if (!empty($this->config->GetStorageUrl())) {
                $fileUrl = str_replace($this->urlGenerator->getAbsoluteURL("/"), $this->config->GetStorageUrl(), $fileUrl);
            }

            $convertedFileUri = $documentService->GetConvertedUri($fileUrl, "docx", "docx", "check_" . rand());

            if (strcmp($convertedFileUri, $fileUrl) === 0) {
                $this->logger->debug("GetConvertedUri skipped", ["app" => $this->appName]);
            }

        } catch (\Exception $e) {
            $this->logger->logException($e, ["message" => "GetConvertedUri on check error", "app" => $this->appName]);
            return [$e->getMessage(), $version];
        }

        try {
            $documentService->Request($convertedFileUri);
        } catch (\Exception $e) {
            $this->logger->logException($e, ["message" => "Request converted file on check error", "app" => $this->appName]);
            return [$e->getMessage(), $version];
        }

        return ["", $version];
    }
}
