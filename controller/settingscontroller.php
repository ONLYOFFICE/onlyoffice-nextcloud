<?php
/**
 *
 * (c) Copyright Ascensio System SIA 2022
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
use OCA\Onlyoffice\FileVersions;
use OCA\Onlyoffice\TemplateManager;

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
            "preview" => $this->config->GetPreview(),
            "advanced" => $this->config->GetAdvanced(),
            "versionHistory" => $this->config->GetVersionHistory(),
            "limitGroups" => $this->config->GetLimitGroups(),
            "chat" => $this->config->GetCustomizationChat(),
            "compactHeader" => $this->config->GetCustomizationCompactHeader(),
            "feedback" => $this->config->GetCustomizationFeedback(),
            "forcesave" => $this->config->GetCustomizationForcesave(),
            "mentionShare" => $this->config->GetCustomizationMentionShare(),
            "help" => $this->config->GetCustomizationHelp(),
            "toolbarNoTabs" => $this->config->GetCustomizationToolbarNoTabs(),
            "successful" => $this->config->SettingsAreSuccessful(),
            "watermark" => $this->config->GetWatermarkSettings(),
            "macros" => $this->config->GetCustomizationMacros(),
            "tagsEnabled" => App::isEnabled("systemtags"),
            "reviewDisplay" => $this->config->GetCustomizationReviewDisplay(),
            "theme" => $this->config->GetCustomizationTheme(),
            "templates" => $this->GetGlobalTemplates(),
            "floatingVersion" => $this->config->GetFloatingVersion()
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
            $error = $this->trans->t("The 30-day test period is over, you can no longer connect to demo ONLYOFFICE Docs server.");
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
                $documentService = new DocumentService($this->trans, $this->config);
                list ($error, $version) = $documentService->checkDocServiceUrl($this->urlGenerator, $this->crypt);
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
     * @param bool $preview - generate preview files
     * @param bool $advanced - use advanced tab
     * @param bool $versionHistory - keep version history
     * @param array $limitGroups - list of groups
     * @param bool $chat - display chat
     * @param bool $compactHeader - display compact header
     * @param bool $feedback - display feedback
     * @param string $unknownAuthor - unknownAuthor display name
     * @param bool $forcesave - forcesave
     * @param bool $mentionShare - mentionShare
     * @param bool $help - display help
     * @param bool $toolbarNoTabs - display toolbar tab
     * @param string $reviewDisplay - review viewing mode
     * @param bool $floatingVersion - floatingVersion
     *
     * @return array
     */
    public function SaveCommon($defFormats,
                               $editFormats,
                               $sameTab,
                               $preview,
                               $advanced,
                               $versionHistory,
                               $limitGroups,
                               $chat,
                               $compactHeader,
                               $feedback,
<<<<<<< HEAD
                               $unknownAuthor,
=======
>>>>>>> 9b2012e467c843e53d9fee28c0d01919756185a4
                               $forcesave,
                               $mentionShare,
                               $help,
                               $toolbarNoTabs,
                               $reviewDisplay,
                               $theme,
                               $floatingVersion
    ) {

        $this->config->SetDefaultFormats($defFormats);
        $this->config->SetEditableFormats($editFormats);
        $this->config->SetSameTab($sameTab);
        $this->config->SetPreview($preview);
        $this->config->SetAdvanced($advanced);
        $this->config->SetVersionHistory($versionHistory);
        $this->config->SetLimitGroups($limitGroups);
        $this->config->SetCustomizationChat($chat);
        $this->config->SetCustomizationCompactHeader($compactHeader);
        $this->config->SetCustomizationFeedback($feedback);
        $this->config->SetUnknownAuthor($unknownAuthor);
        $this->config->SetCustomizationForcesave($forcesave);
        $this->config->SetCustomizationMentionShare($mentionShare);
        $this->config->SetCustomizationHelp($help);
        $this->config->SetCustomizationToolbarNoTabs($toolbarNoTabs);
        $this->config->SetCustomizationReviewDisplay($reviewDisplay);
        $this->config->SetCustomizationTheme($theme);
        $this->config->SetFloatingVersion($floatingVersion);

        return [
        ];
    }

    /**
     * Save security settings
     *
     * @param array $watermarks - watermark settings
     * @param bool $macros - run document macros
     *
     * @return array
     */
    public function SaveSecurity($watermarks,
                                 $macros
    ) {

        if ($watermarks["enabled"] === "true") {
            $watermarks["text"] = trim($watermarks["text"]);
            if (empty($watermarks["text"])) {
                $watermarks["text"] = $this->trans->t("DO NOT SHARE THIS") . " {userId} {date}";
            }
        }

        $this->config->SetWatermarkSettings($watermarks);
        $this->config->SetCustomizationMacros($macros);

        return [
        ];
    }

    /**
     * Clear all version history
     *
     * @return array
     */
    public function ClearHistory() {

        FileVersions::clearHistory();

        return [
        ];
    }

    /**
     * Get global templates
     *
     * @return array
     */
    private function GetGlobalTemplates() {
        $templates = [];
        $templatesList = TemplateManager::GetGlobalTemplates();

        foreach ($templatesList as $templatesItem) {
            $template = [
                "id" => $templatesItem->getId(),
                "name" => $templatesItem->getName(),
                "type" => TemplateManager::GetTypeTemplate($templatesItem->getMimeType())
            ];
            array_push($templates, $template);
        }

        return $templates;
    }
}
