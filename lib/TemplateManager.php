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

use OCP\Files\File;
use OCP\Files\NotFoundException;

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
    public static function getGlobalTemplateDir() {
        $dirPath = "appdata_" . \OC::$server->getConfig()->getSystemValue("instanceid", null)
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
    public static function getGlobalTemplates($mimetype = null) {
        $templateDir = self::getGlobalTemplateDir();

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
    public static function getTemplate($templateId) {
        $logger = \OCP\Log\logger('onlyoffice');

        if (empty($templateId)) {
            $logger->info("templateId is empty", ["app" => self::$appName]);
            return null;
        }

        $templateDir = self::getGlobalTemplateDir();
        try {
            $templates = $templateDir->getById($templateId);
        } catch (\Exception $e) {
            $logger->error("getTemplate: $templateId", ['exception' => $e]);
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
    public static function getTypeTemplate($mime) {
        switch ($mime) {
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
    public static function getMimeTemplate($type) {
        switch ($type) {
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
    public static function isTemplateType($name) {
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        switch ($ext) {
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
    public static function isTemplate($fileId) {
        $template = self::getTemplate($fileId);

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
    public static function getEmptyTemplate($name) {
        $ext = strtolower("." . pathinfo($name, PATHINFO_EXTENSION));

        $lang = \OC::$server->getL10NFactory("")->get("")->getLanguageCode();

        $templatePath = self::getEmptyTemplatePath($lang, $ext);
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
    public static function getEmptyTemplatePath($lang, $ext) {
        if (!array_key_exists($lang, self::$localPath)) {
            $lang = "default";
        }

        return dirname(__DIR__) . DIRECTORY_SEPARATOR . "assets" . DIRECTORY_SEPARATOR . "document-templates" . DIRECTORY_SEPARATOR . self::$localPath[$lang] . DIRECTORY_SEPARATOR . "new" . $ext;
    }

    /**
     * Mapping local path to templates
     *
     * @var Array
     */
    private static $localPath = [
        "ar" => "ar-SA",
        "az" => "az-Latn-AZ",
        "bg" => "bg-BG",
        "ca" => "ca-ES",
        "cs" => "cs-CZ",
        "da" => "da-DK",
        "de" => "de-DE",
        "de_DE" => "de-DE",
        "default" => "default",
        "el" => "el-GR",
        "en" => "en-US",
        "en_GB" => "en-GB",
        "es" => "es-ES",
        "eu" => "eu-ES",
        "fi" => "fi-FI",
        "fr" => "fr-FR",
        "gl" => "gl-ES",
        "he" => "he-IL",
        "hu_HU" => "hu-HU",
        "id_ID" => "id-ID",
        "it" => "it-IT",
        "ja" => "ja-JP",
        "ko" => "ko-KR",
        "lv" => "lv-LV",
        "nb" => "nb-NO",
        "nl" => "nl-NL",
        "pl" => "pl-PL",
        "pt_BR" => "pt-BR",
        "pt_PT" => "pt-PT",
        "ro" => "ro-RO",
        "ru" => "ru-RU",
        "sq_AL" => "sq-AL",
        "si" => "si-LK",
        "sk" => "sk-SK",
        "sl" => "sl-SI",
        "sr" => "sr-Latn-RS",
        "sv" => "sv-SE",
        "tr" => "tr-TR",
        "uk" => "uk-UA",
        "ur_PK" => "ur-PK",
        "vi" => "vi-VN",
        "zh_CN" => "zh-CN",
        "zh_TW" => "zh-TW"
    ];
}
