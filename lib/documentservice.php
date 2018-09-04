<?php
/**
 *
 * (c) Copyright Ascensio System Limited 2010-2018
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
     * l10n service
     *
     * @var IL10N
     */
    private $trans;

    /**
     * Application configuration
     *
     * @var OCA\Onlyoffice\AppConfig
     */
    private $config;

    /**
     * @param IL10N $trans - l10n service
     * @param OCA\Onlyoffice\AppConfig $config - application configutarion
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

        if ($isEndConvert !== NULL && strtolower($isEndConvert) === "true") {
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
        if (empty($from_extension)) {
            $path_parts = pathinfo($document_uri);
            $from_extension = $path_parts["extension"];
        }

        $title = basename($document_uri);
        if (empty($title)) {
            $title = $document_revision_id . $from_extension;
        }

        if (empty($document_revision_id)) {
            $document_revision_id = $document_uri;
        }

        $document_revision_id = self::GenerateRevisionId($document_revision_id);

        $documentServerUrl = $this->config->GetDocumentServerInternalUrl(false);

        if (empty($documentServerUrl)) {
            throw new \Exception($this->trans->t("ONLYOFFICE app is not configured. Please contact admin"));
        }

        $urlToConverter = $documentServerUrl . "ConvertService.ashx";

        $data = json_encode(
            array(
                "async" => $is_async,
                "url" => $document_uri,
                "outputtype" => trim($to_extension, "."),
                "filetype" => trim($from_extension, "."),
                "title" => $title,
                "key" => $document_revision_id
            )
        );

        $response_xml_data;
        $countTry = 0;

        $opts = array("http" => array(
                    "method"  => "POST",
                    "timeout" => "120",
                    "header"=> "Content-type: application/json\r\n",
                    "content" => $data
                )
            );

        if (!empty($this->config->GetDocumentServerSecret())) {
            $params = [
                "payload" => $data
            ];
            $token = \Firebase\JWT\JWT::encode($params, $this->config->GetDocumentServerSecret());
            $opts["http"]["header"] = $opts["http"]["header"] . $this->config->JwtHeader() . ": Bearer " . $token . "\r\n";
        }

        $ServiceConverterMaxTry = 3;
        while ($countTry < $ServiceConverterMaxTry) {
            $countTry = $countTry + 1;
            $response_xml_data = $this->Request($urlToConverter, $opts);
            if ($response_xml_data !== false) { break; }
        }

        if ($countTry === $ServiceConverterMaxTry) {
            throw new \Exception ($this->trans->t("Bad Request or timeout error"));
        }

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
                $errorMessage = $errorMessageTemplate . ": Error unexpected guid";
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
     * @return boolean
     */
    function HealthcheckRequest() {

        $documentServerUrl = $this->config->GetDocumentServerInternalUrl(false);

        if (empty($documentServerUrl)) {
            throw new \Exception($this->trans->t("ONLYOFFICE app is not configured. Please contact admin"));
        }

        $urlHealthcheck = $documentServerUrl . "healthcheck";

        $opts = array("http" => array(
                    "timeout" => "60"
                )
            );

        if (($response = $this->Request($urlHealthcheck, $opts)) === false) {
            throw new \Exception ($this->trans->t("Bad Request or timeout error"));
        }

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

        $documentServerUrl = $this->config->GetDocumentServerInternalUrl(false);

        if (empty($documentServerUrl)) {
            throw new \Exception($this->trans->t("ONLYOFFICE app is not configured. Please contact admin"));
        }

        $urlCommand = $documentServerUrl . "coauthoring/CommandService.ashx";

        $data = json_encode(
            array(
                "c" => $method
            )
        );

        $opts = array("http" => array(
                    "method"  => "POST",
                    "timeout" => "60",
                    "header"=> "Content-type: application/json\r\n",
                    "content" => $data
                )
            );

        if (!empty($this->config->GetDocumentServerSecret())) {
            $params = [
                "payload" => $data
            ];
            $token = \Firebase\JWT\JWT::encode($params, $this->config->GetDocumentServerSecret());
            $opts["http"]["header"] = $opts["http"]["header"] . $this->config->JwtHeader() . ": Bearer " . $token . "\r\n";
        }

        if (($response = $this->Request($urlCommand, $opts)) === false) {
            throw new \Exception ($this->trans->t("Bad Request or timeout error"));
        }

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
     * @param array $opts - stream context options
     *
     * @return string
     */
    public function Request($url, $opts = NULL) {
        if (NULL === $opts) {
            $opts = array();
        }

        if (substr($url, 0, strlen("https")) === "https" && $this->config->TurnOffVerification()) {
            $opts["ssl"] = array(
                "verify_peer" => false,
                "verify_peer_name" => false
            );
        }

        $context  = stream_context_create($opts);

        return file_get_contents($url, false, $context);
    }
}
