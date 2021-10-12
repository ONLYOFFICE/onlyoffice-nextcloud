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

use OCP\Files\NotFoundException;

use OCP\Files\File;

/**
 * Template manager
 *
 * @package OCA\Onlyoffice
 */
class TemplateManager {

    /**
     * Application name
     *
     * @var string
     */
    private static $appName = "onlyoffice";

    /**
     * Template folder name
     *
     * @var string
     */
    private static $templateFolderName = "template";

    /**
     * Get global template directory
     *
     * @return Folder
     */
    public static function GetGlobalTemplateDir() {
        $dirPath = "appdata_" . \OC::$server->getConfig()->GetSystemValue("instanceid", null)
                                . "/" . self::$appName
                                . "/" . self::$templateFolderName;

        $rootFolder = \OC::$server->getRootFolder();
        $templateDir = null;
        try {
            $templateDir = $rootFolder->get($dirPath);
        } catch (NotFoundException $e) {
            $templateDir = $rootFolder->newFolder($dirPath);
        }

        return $templateDir;
    }

    /**
     * Get global templates
     *
     * @param string $mimetype - mimetype of the template
     *
     * @return array
     */
    public static function GetGlobalTemplates($mimetype = null) {
        $templateDir = self::GetGlobalTemplateDir();

        $templatesList = $templateDir->getDirectoryListing();
        if (!empty($mimetype)
            && is_array($templatesList) && count($templatesList) > 0) {
            $templatesList = $templateDir->searchByMime($mimetype);
        }

        return $templatesList;
    }

    /**
     * Get template file
     *
     * @param string $templateId - identifier of the template
     *
     * @return File
     */
    public static function GetTemplate($templateId) {
        $logger = \OC::$server->getLogger();

        if (empty($templateId)) {
            $logger->info("templateId is empty", ["app" => self::$appName]);
            return null;
        }

        $templateDir = self::GetGlobalTemplateDir();
        try {
            $templates = $templateDir->getById($templateId);
        } catch (\Exception $e) {
            $logger->logException($e, ["message" => "GetTemplate: $templateId", "app" => self::$appName]);
            return null;
        }

        if (empty($templates)) {
            return null;
        }

        return $templates[0];
    }

    /**
     * Get type template from mimetype
     *
     * @param string $mime - mimetype
     *
     * @return string
     */
    public static function GetTypeTemplate($mime) {
        switch($mime) {
            case "application/vnd.openxmlformats-officedocument.wordprocessingml.document":
                return "document";
            case "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet":
                return "spreadsheet";
            case "application/vnd.openxmlformats-officedocument.presentationml.presentation":
                return "presentation";
        }

        return "";
    }

    /**
     * Get mimetype template from format type
     *
     * @param string $type - format type
     *
     * @return string
     */
    public static function GetMimeTemplate($type) {
        switch($type) {
            case "document":
                return "application/vnd.openxmlformats-officedocument.wordprocessingml.document";
            case "spreadsheet":
                return "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet";
            case "presentation":
                return "application/vnd.openxmlformats-officedocument.presentationml.presentation";
        }

        return "";
    }

    /**
     * Check template type
     *
     * @param string $name - template name
     *
     * @return bool
     */
    public static function IsTemplateType($name) {
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        switch($ext) {
            case "docx":
            case "xlsx":
            case "pptx":
                return true;
        }

        return false;
    }

    /**
     * Check file if it's template
     *
     * @param int $fileId - identifier file
     *
     * @return bool
     */
    public static function IsTemplate($fileId) {
        $template = self::GetTemplate($fileId);

        if (empty($template)) {
            return false;
        }

        return true;
    }

    /**
     * Get template
     *
     * @param string $name - file name
     *
     * @return string
     */
    public static function GetEmptyTemplate($name) {
        $ext = strtolower("." . pathinfo($name, PATHINFO_EXTENSION));

        $lang = \OC::$server->getL10NFactory("")->get("")->getLanguageCode();

        $templatePath = self::GetEmptyTemplatePath($lang, $ext);
        if (!file_exists($templatePath)) {
            return false;
        }

        $template = file_get_contents($templatePath);
        return $template;
    }

    /**
     * Get template path
     *
     * @param string $lang - language
     * @param string $ext - file extension
     *
     * @return string
     */
    public static function GetEmptyTemplatePath($lang, $ext) {
        if (!array_key_exists($lang, self::$localPath)) {
            $lang = "en";
        }

        return dirname(__DIR__) . DIRECTORY_SEPARATOR . "assets" . DIRECTORY_SEPARATOR . self::$localPath[$lang] . DIRECTORY_SEPARATOR . "new" . $ext;
    }

    /**
     * Mapping local path to templates
     *
     * @var Array
     */
    private static $localPath = [
        "az" => "az-Latn-AZ",
        "bg" => "bg-BG",
        "cs" => "cs-CZ",
        "de" => "de-DE",
        "de_DE" => "de-DE",
        "el" => "el-GR",
        "en" => "en-US",
        "en_GB" => "en-GB",
        "es" => "es-ES",
        "fr" => "fr-FR",
        "it" => "it-IT",
        "ja" => "ja-JP",
        "ko" => "ko-KR",
        "lv" => "lv-LV",
        "nl" => "nl-NL",
        "pl" => "pl-PL",
        "pt_BR" => "pt-BR",
        "pt_PT" => "pt-PT",
        "ru" => "ru-RU",
        "sk" => "sk-SK",
        "sv" => "sv-SE",
        "uk" => "uk-UA",
        "vi" => "vi-VN",
        "zh_CN" => "zh-CN"
    ];
}
