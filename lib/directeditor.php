<?php
/**
 *
 * (c) Copyright Ascensio System SIA 2021
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
            $userId = $token->getUser();

            $this->logger->debug("DirectEditor open: $fileId", ["app" => $this->appName]);

            if (!$this->config->isUserAllowedToUse($userId)) {
                return $this->renderError($this->trans->t("Not permitted"));
            }

            $documentServerUrl = $this->config->GetDocumentServerUrl();

            if (empty($documentServerUrl)) {
                $this->logger->error("documentServerUrl is empty", ["app" => $this->appName]);
                return $this->renderError($this->trans->t("ONLYOFFICE app is not configured. Please contact admin"));
            }

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
                "version" => 0,
                "isTemplate" => false,
                "inframe" => false,
                "anchor" => null
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
            $this->logger->logException($e, ["message" => "DirectEditor open", "app" => $this->appName]);
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
