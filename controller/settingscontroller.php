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

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IRequest;

use OCA\Onlyoffice\AppConfig;
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
     * @param string $AppName - application name
     * @param IRequest $request - request object
     * @param IL10N $trans - l10n service
     * @param ILogger $logger - logger
     * @param OCA\Onlyoffice\AppConfig $config - application configuration
     */
    public function __construct($AppName,
                                    IRequest $request,
                                    IL10N $trans,
                                    ILogger $logger,
                                    AppConfig $config
                                    ) {
        parent::__construct($AppName, $request);

        $this->trans = $trans;
        $this->logger = $logger;
        $this->config = $config;
    }

    /**
     * Print config section
     *
     * @return TemplateResponse
     */
    public function index() {
        $data = [
            "documentserver" => $this->config->GetDocumentServerUrl(),
            "documentserverInternal" => $this->config->GetDocumentServerInternalUrl(),
            "secret" => $this->config->GetDocumentServerSecret()
        ];
        return new TemplateResponse($this->appName, "settings", $data, "blank");
    }

    /**
     * Save the document server address
     *
     * @param string $documentserver - document service address
     * @param string $secret - secret key for signature
     *
     * @return array
     */
    public function settings($documentserver, $documentserverInternal, $secret) {
        $this->config->SetDocumentServerUrl($documentserver);
        $this->config->SetDocumentServerInternalUrl($documentserverInternal);
        $this->config->SetDocumentServerSecret($secret);

        $documentserver = $this->config->GetDocumentServerUrl();
        if (!empty($documentserver)) {
            $error = $this->checkDocServiceUrl();
        }

        return [
            "documentserver" => $this->config->GetDocumentServerUrl(),
            "documentserverInternal" => $this->config->GetDocumentServerInternalUrl(),
            "error" => $error
            ];
    }

    /**
     * Get supported formats
     *
     * @return array
     *
     * @NoAdminRequired
     */
    public function formats(){
        return $this->config->formats;
    }


    /**
     * Checking document service location
     *
     * @param string $documentServer - document service address
     *
     * @return string
     */
    private function checkDocServiceUrl() {

        $documentService = new DocumentService($this->trans, $this->config);

        try {
            $commandResponse = $documentService->CommandRequest("version");

            $this->logger->debug("CommandRequest on check: " . json_encode($commandResponse), array("app" => $this->appName));

            if (empty($commandResponse)) {
                throw new \Exception($this->trans->t("Error occurred in the document service"));
            }

            $version = floatval($commandResponse->version);
            if ($version < 4.2) {
                throw new \Exception($this->trans->t("Not supported version"));
            }
        } catch (\Exception $e) {
            $this->logger->error("CommandRequest on check error: " . $e->getMessage(), array("app" => $this->appName));
            return $e->getMessage();
        }

        return "";
    }
}
