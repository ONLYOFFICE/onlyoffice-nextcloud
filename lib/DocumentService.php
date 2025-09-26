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

namespace OCA\Onlyoffice;

use OCP\Http\Client\IClientService;
use OCP\IL10N;

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
    public static function generateRevisionId($expected_key) {
        if (strlen($expected_key) > 20) {
            $expected_key = crc32($expected_key);
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
     * @param string $region - Region
     * @param bool $toForm - Convert to form
     *
     * @return string
     */
    public function getConvertedUri($document_uri, $from_extension, $to_extension, $document_revision_id, $region = null, $toForm = false) {
        $responceFromConvertService = $this->sendRequestToConvertService($document_uri, $from_extension, $to_extension, $document_revision_id, false, $region, $toForm);

        if (isset($responceFromConvertService->error)) {
            $this->processConvServResponceError($responceFromConvertService->error);
        }

        if (isset($responceFromConvertService->endConvert) && $responceFromConvertService->endConvert === true) {
            return (string)$responceFromConvertService->fileUrl;
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
     * @param bool $is_async - Perform conversions asynchronously
     * @param string $region - Region
     * @param bool $toForm - Convert to form
     *
     * @return array
     */
    public function sendRequestToConvertService($document_uri, $from_extension, $to_extension, $document_revision_id, $is_async, $region = null, $toForm = false) {
        $documentServerUrl = $this->config->getDocumentServerInternalUrl();

        if (empty($documentServerUrl)) {
            throw new \Exception($this->trans->t("ONLYOFFICE app is not configured. Please contact admin"));
        }

        $urlToConverter = $documentServerUrl . "converter";

        if (empty($document_revision_id)) {
            $document_revision_id = $document_uri;
        }

        $document_revision_id = self::generateRevisionId($document_revision_id);
        $urlToConverter = $urlToConverter . "?shardKey=" . $document_revision_id;

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

        if (!is_null($region)) {
            $data["region"] = $region;
        }

        if ($this->config->useDemo()) {
            $data["tenant"] = $this->config->getSystemValue("instanceid", true);
        }

        if ($toForm) {
            $data["pdf"] = [
                "form" => true
            ];
        }

        $opts = [
            "timeout" => "120",
            "headers" => [
                "Content-type" => "application/json"
            ],
            "body" => json_encode($data)
        ];

        if (!empty($this->config->getDocumentServerSecret())) {
            $now = time();
            $iat = $now;
            $exp = $now + $this->config->getJwtExpiration() * 60;
            $params = [
                "payload" => $data,
                "iat" => $iat,
                "exp" => $exp
            ];
            $token = \Firebase\JWT\JWT::encode($params, $this->config->getDocumentServerSecret(), "HS256");
            $opts["headers"][$this->config->jwtHeader()] = "Bearer " . $token;

            $data["iat"] = $iat;
            $data["exp"] = $exp;
            $token = \Firebase\JWT\JWT::encode($data, $this->config->getDocumentServerSecret(), "HS256");
            $data["token"] = $token;
            $opts["body"] = json_encode($data);
        }

        $responseJsonData = $this->request($urlToConverter, "post", $opts);
        $responseData = json_decode($responseJsonData);
        if (json_last_error() !== 0) {
            $exc = $this->trans->t("Bad Response. JSON error: " . json_last_error_msg());
            throw new \Exception($exc);
        }

        return $responseData;
    }

    /**
     * Generate an error code table of convertion
     *
     * @param string $errorCode - Error code
     *
     * @return null
     */
    public function processConvServResponceError($errorCode) {
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
    public function healthcheckRequest() {

        $documentServerUrl = $this->config->getDocumentServerInternalUrl();

        if (empty($documentServerUrl)) {
            throw new \Exception($this->trans->t("ONLYOFFICE app is not configured. Please contact admin"));
        }

        $urlHealthcheck = $documentServerUrl . "healthcheck";

        $response = $this->request($urlHealthcheck);

        return $response === "true";
    }

    /**
     * Send command
     *
     * @param string $method - type of command
     *
     * @return array
     */
    public function commandRequest($method) {

        $documentServerUrl = $this->config->getDocumentServerInternalUrl();

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

        if (!empty($this->config->getDocumentServerSecret())) {
            $now = time();
            $iat = $now;
            $exp = $now + $this->config->getJwtExpiration() * 60;
            $params = [
                "payload" => $data,
                "iat" => $iat,
                "exp" => $exp
            ];

            $token = \Firebase\JWT\JWT::encode($params, $this->config->getDocumentServerSecret(), "HS256");
            $opts["headers"][$this->config->jwtHeader()] = "Bearer " . $token;

            $data["iat"] = $iat;
            $data["exp"] = $exp;
            $token = \Firebase\JWT\JWT::encode($data, $this->config->getDocumentServerSecret(), "HS256");
            $data["token"] = $token;
            $opts["body"] = json_encode($data);
        }

        $response = $this->request($urlCommand, "post", $opts);

        $data = json_decode($response);

        $this->processCommandServResponceError($data->error);

        return $data;
    }

    /**
     * Generate an error code table of command
     *
     * @param string $errorCode - Error code
     *
     * @return null
     */
    public function processCommandServResponceError($errorCode) {
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
    public function request($url, $method = "get", $opts = null) {
        $httpClientService = \OC::$server->get(IClientService::class);
        $client = $httpClientService->newClient();

        if (null === $opts) {
            $opts = array();
        }
        if (substr($url, 0, strlen("https")) === "https" && $this->config->getVerifyPeerOff()) {
            $opts["verify"] = false;
        }
        if (!array_key_exists("timeout", $opts)) {
            $opts["timeout"] = 60;
        }

        $opts['nextcloud'] = [
            'allow_local_address' => true,
        ];

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
        $logger = \OCP\Log\logger('onlyoffice');
        $version = null;

        try {
            if (preg_match("/^https:\/\//i", $urlGenerator->getAbsoluteURL("/"))
                && preg_match("/^http:\/\//i", $this->config->getDocumentServerUrl())) {
                throw new \Exception($this->trans->t("Mixed Active Content is not allowed. HTTPS address for ONLYOFFICE Docs is required."));
            }
        } catch (\Exception $e) {
            $logger->error("Protocol on check error", ['exception' => $e]);
            return [$e->getMessage(), $version];
        }

        try {
            $healthcheckResponse = $this->healthcheckRequest();
            if (!$healthcheckResponse) {
                throw new \Exception($this->trans->t("Bad healthcheck status"));
            }
        } catch (\Exception $e) {
            $logger->error("healthcheckRequest on check error", ['exception' => $e]);
            return [$e->getMessage(), $version];
        }

        try {
            $commandResponse = $this->commandRequest("version");
            $logger->debug("commandRequest on check: " . json_encode($commandResponse), ["app" => self::$appName]);
            if (empty($commandResponse)) {
                throw new \Exception($this->trans->t("Error occurred in the document service"));
            }
            $version = $commandResponse->version;
            $versionF = floatval($version);
            if ($versionF > 0.0 && $versionF <= 6.0) {
                throw new \Exception($this->trans->t("Not supported version"));
            }
        } catch (\Exception $e) {
            $logger->error("commandRequest on check error", ['exception' => $e]);
            return [$e->getMessage(), $version];
        }

        $convertedFileUri = null;
        try {
            $hashUrl = $crypt->getHash(["action" => "empty"]);
            $fileUrl = $urlGenerator->linkToRouteAbsolute(self::$appName . ".callback.emptyfile", ["doc" => $hashUrl]);
            if (!$this->config->useDemo() && !empty($this->config->getStorageUrl())) {
                $fileUrl = str_replace($urlGenerator->getAbsoluteURL("/"), $this->config->getStorageUrl(), $fileUrl);
            }

            $convertedFileUri = $this->getConvertedUri($fileUrl, "docx", "docx", "check_" . rand());

            if (strcmp($convertedFileUri, $fileUrl) === 0) {
                $logger->debug("getConvertedUri skipped", ["app" => self::$appName]);
            }
        } catch (\Exception $e) {
            $logger->error("getConvertedUri on check error", ['exception' => $e]);
            return [$e->getMessage(), $version];
        }

        try {
            $this->request($convertedFileUri);
        } catch (\Exception $e) {
            $logger->error("Request converted file on check error", ['exception' => $e]);
            return [$e->getMessage(), $version];
        }

        return ["", $version];
    }
}
