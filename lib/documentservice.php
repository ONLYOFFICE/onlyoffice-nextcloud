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

use OCP\IL10N;

use OCA\Onlyoffice\AppConfig;

/**
 * Class service connector to Document Service
 *
 * @package OCA\Onlyoffice
 */
class DocumentService {

    /**
     * Application name
     *
     * @var string
     */
    private static $appName = "onlyoffice";

    /**
     * l10n service
     *
     * @var IL10N
     */
    private $trans;

    /**
     * Application configuration
     *
     * @var AppConfig
     */
    private $config;

    /**
     * @param IL10N $trans - l10n service
     * @param AppConfig $config - application configutarion
     */
    public function __construct(IL10N $trans, AppConfig $appConfig) {
        $this->trans = $trans;
        $this->config = $appConfig;
    }

    /**
     * Translation key to a supported form.
     *
     * @param string $expected_key - Expected key
     *
     * @return string
     */
    public static function GenerateRevisionId($expected_key) {
        if (strlen($expected_key) > 20) {
            $expected_key = crc32( $expected_key);
        }
        $key = preg_replace("[^0-9-.a-zA-Z_=]", "_", $expected_key);
        $key = substr($key, 0, min(array(strlen($key), 20)));
        return $key;
    }

    /**
     * The method is to convert the file to the required format and return the result url
     *
     * @param string $document_uri - Uri for the document to convert
     * @param string $from_extension - Document extension
     * @param string $to_extension - Extension to which to convert
     * @param string $document_revision_id - Key for caching on service
     *
     * @return string
     */
    function GetConvertedUri($document_uri, $from_extension, $to_extension, $document_revision_id) {
        $responceFromConvertService = $this->SendRequestToConvertService($document_uri, $from_extension, $to_extension, $document_revision_id, false);

        $errorElement = $responceFromConvertService->Error;
        if ($errorElement->count() > 0) {
            $this->ProcessConvServResponceError($errorElement . "");
        }

        $isEndConvert = $responceFromConvertService->EndConvert;

        if ($isEndConvert !== null && strtolower($isEndConvert) === "true") {
            return $responceFromConvertService->FileUrl;
        }

        return "";
    }

    /**
     * Request for conversion to a service
     *
     * @param string $document_uri - Uri for the document to convert
     * @param string $from_extension - Document extension
     * @param string $to_extension - Extension to which to convert
     * @param string $document_revision_id - Key for caching on service
     * @param bool - $is_async - Perform conversions asynchronously
     *
     * @return array
     */
    function SendRequestToConvertService($document_uri, $from_extension, $to_extension, $document_revision_id, $is_async) {
        $documentServerUrl = $this->config->GetDocumentServerInternalUrl();

        if (empty($documentServerUrl)) {
            throw new \Exception($this->trans->t("ONLYOFFICE app is not configured. Please contact admin"));
        }

        $urlToConverter = $documentServerUrl . "ConvertService.ashx";

        if (empty($document_revision_id)) {
            $document_revision_id = $document_uri;
        }

        $document_revision_id = self::GenerateRevisionId($document_revision_id);

        if (empty($from_extension)) {
            $from_extension = pathinfo($document_uri)["extension"];
        } else {
            $from_extension = trim($from_extension, ".");
        }

        $data = [
            "async" => $is_async,
            "url" => $document_uri,
            "outputtype" => trim($to_extension, "."),
            "filetype" => $from_extension,
            "title" => $document_revision_id . "." . $from_extension,
            "key" => $document_revision_id
        ];

        if ($this->config->UseDemo()) {
            $data["tenant"] = $this->config->GetSystemValue("instanceid", true);
        }

        $opts = [
            "timeout" => "120",
            "headers" => [
                "Content-type" => "application/json"
            ],
            "body" => json_encode($data)
        ];

        if (!empty($this->config->GetDocumentServerSecret())) {
            $params = [
                "payload" => $data
            ];
            $token = \Firebase\JWT\JWT::encode($params, $this->config->GetDocumentServerSecret());
            $opts["headers"][$this->config->JwtHeader()] = "Bearer " . $token;

            $token = \Firebase\JWT\JWT::encode($data, $this->config->GetDocumentServerSecret());
            $data["token"] = $token;
            $opts["body"] = json_encode($data);
        }

        $response_xml_data = $this->Request($urlToConverter, "post", $opts);

        libxml_use_internal_errors(true);
        if (!function_exists("simplexml_load_file")) {
             throw new \Exception($this->trans->t("Server can't read xml"));
        }
        $response_data = simplexml_load_string($response_xml_data);
        if (!$response_data) {
            $exc = $this->trans->t("Bad Response. Errors: ");
            foreach(libxml_get_errors() as $error) {
                $exc = $exc . "\t" . $error->message;
            }
            throw new \Exception ($exc);
        }

        return $response_data;
    }

    /**
     * Generate an error code table of convertion
     *
     * @param string $errorCode - Error code
     *
     * @return null
     */
    function ProcessConvServResponceError($errorCode) {
        $errorMessageTemplate = $this->trans->t("Error occurred in the document service");
        $errorMessage = "";

        switch ($errorCode) {
            case -20:
                $errorMessage = $errorMessageTemplate . ": Error encrypt signature";
                break;
            case -8:
                $errorMessage = $errorMessageTemplate . ": Invalid token";
                break;
            case -7:
                $errorMessage = $errorMessageTemplate . ": Error document request";
                break;
            case -6:
                $errorMessage = $errorMessageTemplate . ": Error while accessing the conversion result database";
                break;
            case -5:
                $errorMessage = $errorMessageTemplate . ": Incorrect password";
                break;
            case -4:
                $errorMessage = $errorMessageTemplate . ": Error while downloading the document file to be converted.";
                break;
            case -3:
                $errorMessage = $errorMessageTemplate . ": Conversion error";
                break;
            case -2:
                $errorMessage = $errorMessageTemplate . ": Timeout conversion error";
                break;
            case -1:
                $errorMessage = $errorMessageTemplate . ": Unknown error";
                break;
            case 0:
                break;
            default:
                $errorMessage = $errorMessageTemplate . ": ErrorCode = " . $errorCode;
                break;
        }

        throw new \Exception($errorMessage);
    }

    /**
     * Request health status
     *
     * @return bool
     */
    function HealthcheckRequest() {

        $documentServerUrl = $this->config->GetDocumentServerInternalUrl();

        if (empty($documentServerUrl)) {
            throw new \Exception($this->trans->t("ONLYOFFICE app is not configured. Please contact admin"));
        }

        $urlHealthcheck = $documentServerUrl . "healthcheck";

        $response = $this->Request($urlHealthcheck);

        return $response === "true";
    }

    /**
     * Send command
     *
     * @param string $method - type of command
     *
     * @return array
     */
    function CommandRequest($method) {

        $documentServerUrl = $this->config->GetDocumentServerInternalUrl();

        if (empty($documentServerUrl)) {
            throw new \Exception($this->trans->t("ONLYOFFICE app is not configured. Please contact admin"));
        }

        $urlCommand = $documentServerUrl . "coauthoring/CommandService.ashx";

        $data = [
            "c" => $method
        ];

        $opts = [
            "headers" => [
                "Content-type" => "application/json"
            ],
            "body" => json_encode($data)
        ];

        if (!empty($this->config->GetDocumentServerSecret())) {
            $params = [
                "payload" => $data
            ];
            $token = \Firebase\JWT\JWT::encode($params, $this->config->GetDocumentServerSecret());
            $opts["headers"][$this->config->JwtHeader()] = "Bearer " . $token;

            $token = \Firebase\JWT\JWT::encode($data, $this->config->GetDocumentServerSecret());
            $data["token"] = $token;
            $opts["body"] = json_encode($data);
        }

        $response = $this->Request($urlCommand, "post", $opts);

        $data = json_decode($response);

        $this->ProcessCommandServResponceError($data->error);

        return $data;
    }

    /**
     * Generate an error code table of command
     *
     * @param string $errorCode - Error code
     *
     * @return null
     */
    function ProcessCommandServResponceError($errorCode) {
        $errorMessageTemplate = $this->trans->t("Error occurred in the document service");
        $errorMessage = "";

        switch ($errorCode) {
            case 6:
                $errorMessage = $errorMessageTemplate . ": Invalid token";
                break;
            case 5:
                $errorMessage = $errorMessageTemplate . ": Command not correсt";
                break;
            case 3:
                $errorMessage = $errorMessageTemplate . ": Internal server error";
                break;
            case 0:
                return;
            default:
                $errorMessage = $errorMessageTemplate . ": ErrorCode = " . $errorCode;
                break;
        }

        throw new \Exception($errorMessage);
    }

    /**
     * Request to Document Server with turn off verification
     *
     * @param string $url - request address
     * @param array $method - request method
     * @param array $opts - request options
     *
     * @return string
     */
    public function Request($url, $method = "get", $opts = null) {
        $httpClientService = \OC::$server->getHTTPClientService();
        $client = $httpClientService->newClient();

        if (null === $opts) {
            $opts = array();
        }
        if (substr($url, 0, strlen("https")) === "https" && $this->config->GetVerifyPeerOff()) {
            $opts["verify"] = false;
        }
        if (!array_key_exists("timeout", $opts)) {
            $opts["timeout"] = 60;
        }

        if ($method === "post") {
            $response = $client->post($url, $opts);
        } else {
            $response = $client->get($url, $opts);
        }

        return $response->getBody();
    }

    /**
     * Checking document service location
     *
     * @param OCP\IURLGenerator $urlGenerator - url generator
     * @param OCA\Onlyoffice\Crypt $crypt -crypt
     *
     * @return array
     */
    public function checkDocServiceUrl($urlGenerator, $crypt) {
        $logger = \OC::$server->getLogger();
        $version = null;

        try {

            if (preg_match("/^https:\/\//i", $urlGenerator->getAbsoluteURL("/"))
                && preg_match("/^http:\/\//i", $this->config->GetDocumentServerUrl())) {
                throw new \Exception($this->trans->t("Mixed Active Content is not allowed. HTTPS address for ONLYOFFICE Docs is required."));
            }

        } catch (\Exception $e) {
            $logger->logException($e, ["message" => "Protocol on check error", "app" => self::$appName]);
            return [$e->getMessage(), $version];
        }

        try {

            $healthcheckResponse = $this->HealthcheckRequest();
            if (!$healthcheckResponse) {
                throw new \Exception($this->trans->t("Bad healthcheck status"));
            }

        } catch (\Exception $e) {
            $logger->logException($e, ["message" => "HealthcheckRequest on check error", "app" => self::$appName]);
            return [$e->getMessage(), $version];
        }

        try {

            $commandResponse = $this->CommandRequest("version");

            $logger->debug("CommandRequest on check: " . json_encode($commandResponse), ["app" => self::$appName]);

            if (empty($commandResponse)) {
                throw new \Exception($this->trans->t("Error occurred in the document service"));
            }

            $version = $commandResponse->version;
            $versionF = floatval($version);
            if ($versionF > 0.0 && $versionF <= 6.0) {
                throw new \Exception($this->trans->t("Not supported version"));
            }

        } catch (\Exception $e) {
            $logger->logException($e, ["message" => "CommandRequest on check error", "app" => self::$appName]);
            return [$e->getMessage(), $version];
        }

        $convertedFileUri = null;
        try {

            $hashUrl = $crypt->GetHash(["action" => "empty"]);
            $fileUrl = $urlGenerator->linkToRouteAbsolute(self::$appName . ".callback.emptyfile", ["doc" => $hashUrl]);
            if (!empty($this->config->GetStorageUrl())) {
                $fileUrl = str_replace($urlGenerator->getAbsoluteURL("/"), $this->config->GetStorageUrl(), $fileUrl);
            }

            $convertedFileUri = $this->GetConvertedUri($fileUrl, "docx", "docx", "check_" . rand());

            if (strcmp($convertedFileUri, $fileUrl) === 0) {
                $logger->debug("GetConvertedUri skipped", ["app" => self::$appName]);
            }

        } catch (\Exception $e) {
            $logger->logException($e, ["message" => "GetConvertedUri on check error", "app" => self::$appName]);
            return [$e->getMessage(), $version];
        }

        try {
            $this->Request($convertedFileUri);
        } catch (\Exception $e) {
            $logger->logException($e, ["message" => "Request converted file on check error", "app" => self::$appName]);
            return [$e->getMessage(), $version];
        }

        return ["", $version];
    }
}
