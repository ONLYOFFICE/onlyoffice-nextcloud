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

namespace OCA\Onlyoffice\Controller;

use OCA\Onlyoffice\AppConfig;
use OCA\Onlyoffice\Crypt;
use OCA\Onlyoffice\DocumentService;
use OCA\Onlyoffice\FileVersions;
use OCA\Onlyoffice\TemplateManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\Preview\IMimeIconProvider;
use Psr\Log\LoggerInterface;

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
     * @var LoggerInterface
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
     * Mime icon provider
     *
     * @var IMimeIconProvider
     */
    private $mimeIconProvider;

    /**
     * @param string $AppName - application name
     * @param IRequest $request - request object
     * @param IURLGenerator $urlGenerator - url generator service
     * @param IL10N $trans - l10n service
     * @param LoggerInterface $logger - logger
     * @param AppConfig $config - application configuration
     * @param Crypt $crypt - hash generator
     * @param IMimeIconProvider $mimeIconProvider - mime icon provider
     */
    public function __construct(
        $AppName,
        IRequest $request,
        IURLGenerator $urlGenerator,
        IL10N $trans,
        LoggerInterface $logger,
        AppConfig $config,
        Crypt $crypt,
        IMimeIconProvider $mimeIconProvider,
    ) {
        parent::__construct($AppName, $request);

        $this->urlGenerator = $urlGenerator;
        $this->trans = $trans;
        $this->logger = $logger;
        $this->config = $config;
        $this->crypt = $crypt;
        $this->mimeIconProvider = $mimeIconProvider;
    }

    /**
     * Print config section
     *
     * @return TemplateResponse
     */
    public function index() {
        $data = [
            "documentserver" => $this->config->getDocumentServerUrl(true),
            "documentserverInternal" => $this->config->getDocumentServerInternalUrl(true),
            "storageUrl" => $this->config->getStorageUrl(),
            "verifyPeerOff" => $this->config->getVerifyPeerOff(),
            "secret" => $this->config->getDocumentServerSecret(true),
            "jwtHeader" => $this->config->jwtHeader(true),
            "demo" => $this->config->getDemoData(),
            "currentServer" => $this->urlGenerator->getAbsoluteURL("/"),
            "formats" => $this->config->formatsSetting(),
            "sameTab" => $this->config->getSameTab(),
            "enableSharing" => $this->config->getEnableSharing(),
            "preview" => $this->config->getPreview(),
            "advanced" => $this->config->getAdvanced(),
            "cronChecker" => $this->config->getCronChecker(),
            "emailNotifications" => $this->config->getEmailNotifications(),
            "versionHistory" => $this->config->getVersionHistory(),
            "protection" => $this->config->getProtection(),
            "limitGroups" => $this->config->getLimitGroups(),
            "chat" => $this->config->getCustomizationChat(),
            "compactHeader" => $this->config->getCustomizationCompactHeader(),
            "feedback" => $this->config->getCustomizationFeedback(),
            "forcesave" => $this->config->getCustomizationForcesave(),
            "liveViewOnShare" => $this->config->getLiveViewOnShare(),
            "help" => $this->config->getCustomizationHelp(),
            "successful" => $this->config->settingsAreSuccessful(),
            "settingsError" => $this->config->getSettingsError(),
            "watermark" => $this->config->getWatermarkSettings(),
            "plugins" => $this->config->getCustomizationPlugins(),
            "macros" => $this->config->getCustomizationMacros(),
            "tagsEnabled" => \OC::$server->getAppManager()->isEnabledForUser("systemtags"),
            "reviewDisplay" => $this->config->getCustomizationReviewDisplay(),
            "theme" => $this->config->getCustomizationTheme(true),
            "templates" => $this->getGlobalTemplates(),
            "unknownAuthor" => $this->config->getUnknownAuthor()
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
     * @param string $jwtHeader - jwt header
     * @param bool $demo - use demo server
     *
     * @return array
     */
    public function saveAddress(
        $documentserver,
        $documentserverInternal,
        $storageUrl,
        $verifyPeerOff,
        $secret,
        $jwtHeader,
        $demo
    ) {
        $error = null;
        if (!$this->config->selectDemo($demo === true)) {
            $error = $this->trans->t("The 30-day test period is over, you can no longer connect to demo ONLYOFFICE Docs server.");
        }
        if ($demo !== true) {
            $this->config->setDocumentServerUrl($documentserver);
            $this->config->setVerifyPeerOff($verifyPeerOff);
            $this->config->setDocumentServerInternalUrl($documentserverInternal);
            $this->config->setDocumentServerSecret($secret);
            $this->config->setJwtHeader($jwtHeader);
        }
        $this->config->setStorageUrl($storageUrl);

        $version = null;
        if (empty($error)) {
            $documentserver = $this->config->getDocumentServerUrl();
            if (!empty($documentserver)) {
                $documentService = new DocumentService($this->trans, $this->config);
                list($error, $version) = $documentService->checkDocServiceUrl($this->urlGenerator, $this->crypt);
                $this->config->setSettingsError($error);
            }
        }

        return [
            "documentserver" => $this->config->getDocumentServerUrl(true),
            "verifyPeerOff" => $this->config->getVerifyPeerOff(),
            "documentserverInternal" => $this->config->getDocumentServerInternalUrl(true),
            "storageUrl" => $this->config->getStorageUrl(),
            "secret" => $this->config->getDocumentServerSecret(true),
            "jwtHeader" => $this->config->jwtHeader(true),
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
     * @param bool $enableSharing - enable sharingsameTab
     * @param bool $preview - generate preview files
     * @param bool $advanced - use advanced tab
     * @param bool $cronChecker - disable cron checker
     * @param bool $emailNotifications - notifications via e-mail
     * @param bool $versionHistory - keep version history
     * @param array $limitGroups - list of groups
     * @param bool $chat - display chat
     * @param bool $compactHeader - display compact header
     * @param bool $feedback - display feedback
     * @param bool $forcesave - forcesave
     * @param bool $liveViewOnShare - live view on share
     * @param bool $help - display help
     * @param string $reviewDisplay - review viewing mode
     * @param string $unknownAuthor - display unknown author
     *
     * @return array
     */
    public function saveCommon(
        $defFormats,
        $editFormats,
        $sameTab,
        $enableSharing,
        $preview,
        $advanced,
        $cronChecker,
        $emailNotifications,
        $versionHistory,
        $limitGroups,
        $chat,
        $compactHeader,
        $feedback,
        $forcesave,
        $liveViewOnShare,
        $help,
        $reviewDisplay,
        $theme,
        $unknownAuthor
    ) {

        $this->config->setDefaultFormats($defFormats);
        $this->config->setEditableFormats($editFormats);
        $this->config->setEnableSharing($enableSharing);
        $this->config->setSameTab($sameTab);
        $this->config->setPreview($preview);
        $this->config->setAdvanced($advanced);
        $this->config->setCronChecker($cronChecker);
        $this->config->setEmailNotifications($emailNotifications);
        $this->config->setVersionHistory($versionHistory);
        $this->config->setLimitGroups($limitGroups);
        $this->config->setCustomizationChat($chat);
        $this->config->setCustomizationCompactHeader($compactHeader);
        $this->config->setCustomizationFeedback($feedback);
        $this->config->setCustomizationForcesave($forcesave);
        $this->config->setLiveViewOnShare($liveViewOnShare);
        $this->config->setCustomizationHelp($help);
        $this->config->setCustomizationReviewDisplay($reviewDisplay);
        $this->config->setCustomizationTheme($theme);
        $this->config->setUnknownAuthor($unknownAuthor);

        return [
        ];
    }

    /**
     * Save security settings
     *
     * @param array $watermarks - watermark settings
     * @param bool $plugins - enable plugins
     * @param bool $macros - run document macros
     * @param string $protection - protection
     *
     * @return array
     */
    public function saveSecurity(
        $watermarks,
        $plugins,
        $macros,
        $protection
    ) {

        if ($watermarks["enabled"] === "true") {
            $watermarks["text"] = trim($watermarks["text"]);
            if (empty($watermarks["text"])) {
                $watermarks["text"] = $this->trans->t("DO NOT SHARE THIS") . " {userId} {date}";
            }
        }

        $this->config->setWatermarkSettings($watermarks);
        $this->config->setCustomizationPlugins($plugins);
        $this->config->setCustomizationMacros($macros);
        $this->config->setProtection($protection);

        return [
        ];
    }

    /**
     * Clear all version history
     *
     * @return array
     */
    public function clearHistory() {

        FileVersions::clearHistory();

        return [
        ];
    }

    /**
     * Get global templates
     *
     * @return array
     */
    private function getGlobalTemplates() {
        $templates = [];
        $templatesList = TemplateManager::getGlobalTemplates();

        foreach ($templatesList as $templatesItem) {
            $template = [
                "id" => $templatesItem->getId(),
                "name" => $templatesItem->getName(),
                "type" => TemplateManager::getTypeTemplate($templatesItem->getMimeType()),
                "icon" => $this->mimeIconProvider->getMimeIconUrl($templatesItem->getMimeType())
            ];
            array_push($templates, $template);
        }

        return $templates;
    }
}
