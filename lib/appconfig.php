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

namespace OCA\Onlyoffice;

use OCP\IConfig;

/**
 * Application configutarion
 *
 * @package OCA\Onlyoffice
 */
class AppConfig {

    /**
     * Application name
     *
     * @var string
     */
    private $appName;

    /**
     * Config service
     *
     * @var OCP\IConfig
     */
    private $config;

    /**
     * The config key for the document server address
     *
     * @var string
     */
    private $_documentserver = "DocumentServerUrl";

    /**
     * The config key for the secret key
     *
     * @var string
     */
    private $_cryptSecret = "skey";

    /**
     * @param string $AppName application name
     */
    public function __construct($AppName) {
        
        $this->appName = $AppName;
        $this->config = \OC::$server->getConfig();
    }

    /**
     * Save the document service address to the application configuration
     *
     * @param string $documentServer - document service address
     */
    public function SetDocumentServerUrl($documentServer) {
        $documentServer = rtrim(trim($documentServer), "/");
        $this->config->setAppValue($this->appName, $this->_documentserver, $documentServer);
        $this->DropSKey();
    }

    /**
     * Get the document service address from the application configuration
     *
     * @return string
     */
    public function GetDocumentServerUrl() {
        return $this->config->getAppValue($this->appName, $this->_documentserver, "");
    }

    /**
     * Get the secret key from the application configuration
     *
     * @return string
     */
    public function GetSKey() {
        $skey = $this->config->getAppValue($this->appName, $this->_cryptSecret, "");
        if (empty($skey)) {
            $skey = number_format(round(microtime(true) * 1000), 0, ".", "");
            $this->config->setAppValue($this->appName, $this->_cryptSecret, $skey);
        }
        return $skey;
    }

    /**
     * Regenerate the secret key
     *
     * @return string
     */
    private function DropSKey() {
        $skey = $this->config->getAppValue($this->appName, $this->_cryptSecret, "");
        if (!empty($skey)) {
            $skey = number_format(round(microtime(true) * 1000), 0, ".", "");
            $this->config->setAppValue($this->appName, $this->_cryptSecret, $skey);
        }
    }


    /**
     * Additional data about formats
     *
     * @var array
     */
    public $formats = [
            "docx" => [ "mime" => "application/vnd.openxmlformats-officedocument.wordprocessingml.document", "type" => "text", "edit" => true ],
            "xlsx" => [ "mime" => "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet", "type" => "spreadsheet", "edit" => true ],
            "pptx" => [ "mime" => "application/vnd.openxmlformats-officedocument.presentationml.presentation", "type" => "presentation", "edit" => true ],
            "ppsx" => [ "mime" => "application/vnd.openxmlformats-officedocument.presentationml.slideshow", "type" => "presentation", "edit" => true ],
            "txt" => [ "mime" => "text/plain", "type" => "text", "edit" => true ],
            "csv" => [ "mime" => "text/csv", "type" => "spreadsheet"/*, "edit" => true*/ ],
            "odt" => [ "mime" => "application/vnd.oasis.opendocument.text", "type" => "text", "conv" => true ],
            "ods" => [ "mime" => "application/vnd.oasis.opendocument.spreadsheet", "type" => "spreadsheet", "conv" => true ],
            "odp" => [ "mime" => "application/vnd.oasis.opendocument.presentation", "type" => "presentation", "conv" => true ],
            "doc" => [ "mime" => "application/msword", "type" => "text", "conv" => true ],
            "xls" => [ "mime" => "application/vnd.ms-excel", "type" => "spreadsheet", "conv" => true ],
            "ppt" => [ "mime" => "application/vnd.ms-powerpoint", "type" => "presentation", "conv" => true ],
            "pps" => [ "mime" => "application/vnd.ms-powerpoint", "type" => "presentation", "conv" => true ],
            "epub" => [ "mime" => "application/epub+zip", "type" => "text", "conv" => true ],
            "rtf" => [ "mime" => "text/rtf", "type" => "text", "type" => "text", "conv" => true ],
            "mht" => [ "mime" => "message/rfc822", "conv" => true ],
            "html" => [ "mime" => "text/html", "type" => "text", "conv" => true ],
            "htm" => [ "mime" => "text/html", "type" => "text", "conv" => true ],
            "xps" => [ "mime" => "application/vnd.ms-xpsdocument", "type" => "text" ],
            "pdf" => [ "mime" => "application/pdf", "type" => "text" ],
            "djvu" => [ "mime" => "image/vnd.djvu", "type" => "text" ]
        ];
}
