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

use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\DirectEditing\IEditor;
use OCP\DirectEditing\IToken;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IURLGenerator;

use OCA\Onlyoffice\AppConfig;
use OCA\Onlyoffice\Crypt;
use OCA\Onlyoffice\FileCreator;

/**
 * Direct Editor
 *
 * @package OCA\Onlyoffice
 */
class DirectEditor implements IEditor {

    /**
     * Application name
     *
     * @var string
     */
    private $appName;

    /**
     * Url generator service
     *
     * @var IURLGenerator
     */
    private $urlGenerator;

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
     * Hash generator
     *
     * @var Crypt
     */
    private $crypt;

    /**
     * @param string $AppName - application name
     * @param IURLGenerator $urlGenerator - url generator service
     * @param IL10N $trans - l10n service
     * @param ILogger $logger - logger
     * @param AppConfig $config - application configuration
     * @param Crypt $crypt - hash generator
     */
    public function __construct($AppName,
                                IURLGenerator $urlGenerator,
                                IL10N $trans,
                                ILogger $logger,
                                AppConfig $config,
                                Crypt $crypt) {
        $this->appName = $AppName;
        $this->urlGenerator = $urlGenerator;
        $this->trans = $trans;
        $this->logger = $logger;
        $this->config = $config;
        $this->crypt = $crypt;
    }

    /**
     * Return a unique identifier for the editor
     *
     * @return string
     */
    public function getId(): string {
        return $this->appName;
    }

    /**
     * Return a readable name for the editor
     *
     * @return string
     */
    public function getName(): string {
        return "ONLYOFFICE";
    }

    /**
     * A list of mimetypes that should open the editor by default
     *
     * @return array
     */
    public function getMimetypes(): array {
        $mimes = array();
        if (!$this->config->isUserAllowedToUse()) {
            return $mimes;
        }

        $formats = $this->config->FormatsSetting();
        foreach ($formats as $format => $setting) {
            if (array_key_exists("edit", $setting) && $setting["edit"]
                && array_key_exists("def", $setting) && $setting["def"]) {
                array_push($mimes, $setting["mime"]);
            }
        }

        return $mimes;
    }

    /**
     * A list of mimetypes that can be opened in the editor optionally
     *
     * @return array
     */
    public function getMimetypesOptional(): array {
        $mimes = array();
        if (!$this->config->isUserAllowedToUse()) {
            return $mimes;
        }

        $formats = $this->config->FormatsSetting();
        foreach ($formats as $format => $setting) {
            if (array_key_exists("edit", $setting) && $setting["edit"]
                && (!array_key_exists("def", $setting) || !$setting["def"])) {
                array_push($mimes, $setting["mime"]);
            }
        }

        return $mimes;
    }

    /**
     * Return a list of file creation options to be presented to the user
     *
     * @return array of ACreateFromTemplate|ACreateEmpty
     */
    public function getCreators(): array {
        if (!$this->config->isUserAllowedToUse()) {
            return array();
        }

        return [
            new FileCreator($this->appName, $this->trans, $this->logger, "docx"),
            new FileCreator($this->appName, $this->trans, $this->logger, "xlsx"),
            new FileCreator($this->appName, $this->trans, $this->logger, "pptx")
        ];
    }

    /**
     * Return if the view is able to securely view a file without downloading it to the browser
     *
     * @return bool
     */
    public function isSecure(): bool {
        return true;
    }

    /**
     * Return a template response for displaying the editor
     *
     * open can only be called once when the client requests the editor with a one-time-use token
     * For handling editing and later requests, editors need to implement their own token handling
     * and take care of invalidation
     *
     * @param IToken $token - one time token
     *
     * @return Response
     */
    public function open(IToken $token): Response {
        try {
            $token->useTokenScope();
            $file = $token->getFile();
            $fileId = $file->getId();
            $this->logger->debug("DirectEditor open: $fileId", ["app" => $this->appName]);

            $documentServerUrl = $this->config->GetDocumentServerUrl();

            if (empty($documentServerUrl)) {
                $this->logger->error("documentServerUrl is empty", ["app" => $this->appName]);
                return $this->renderError($this->trans->t("ONLYOFFICE app is not configured. Please contact admin"));
            }

            $userId = $token->getUser();
            $directToken = $this->crypt->GetHash([
                "userId" => $userId,
                "fileId" => $fileId,
                "action" => "direct",
                "iat" => time(),
                "exp" => time() + 30
            ]);

            $filePath = $file->getPath();
            $filePath = preg_replace("/^\/" . $userId . "\/files/", "", $filePath);

            $params = [
                "documentServerUrl" => $documentServerUrl,
                "fileId" => null,
                "filePath" => $filePath,
                "shareToken" => null,
                "directToken" => $directToken,
                "inframe" => false
            ];

            $response = new TemplateResponse($this->appName, "editor", $params, "base");

            $csp = new ContentSecurityPolicy();
            $csp->allowInlineScript(true);

            if (preg_match("/^https?:\/\//i", $documentServerUrl)) {
                $csp->addAllowedScriptDomain($documentServerUrl);
                $csp->addAllowedFrameDomain($documentServerUrl);
            } else {
                $csp->addAllowedFrameDomain("'self'");
            }
            $response->setContentSecurityPolicy($csp);

            return $response;
        } catch (\Exception $e) {
            $this->logger->logException($e, ["DirectEditor open", "app" => $this->appName]);
            return $this->renderError($e->getMessage());
        }
    }

    /**
     * Print error page
     *
     * @param string $error - error message
     * @param string $hint - error hint
     *
     * @return TemplateResponse
     */
    private function renderError($error, $hint = "") {
        return new TemplateResponse("", "error", [
                "errors" => [
                    [
                        "error" => $error,
                        "hint" => $hint
                    ]
                ]
            ], "error");
    }
}
