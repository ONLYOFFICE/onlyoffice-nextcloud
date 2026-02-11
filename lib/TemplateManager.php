<?php
/**
 *
 * (c) Copyright Ascensio System SIA 2026
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
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IConfig;
use OCP\L10N\IFactory;
use OCP\Server;

/**
 * Template manager
 *
 * @package OCA\Onlyoffice
 */
class TemplateManager {

    /**
     * Application name
     */
    private static string $appName = "onlyoffice";

    /**
     * Template folder name
     */
    private static string $templateFolderName = "template";

    /**
     * Get global template directory
     */
    public static function getGlobalTemplateDir(): Folder {
        $dirPath = "appdata_" . Server::get(IConfig::class)->getSystemValue("instanceid", null)
                                . "/" . self::$appName
                                . "/" . self::$templateFolderName;

        $rootFolder = Server::get(IRootFolder::class);
        $templateDir = null;
        try {
            $templateDir = $rootFolder->get($dirPath);
        } catch (NotFoundException) {
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
    public static function getGlobalTemplates($mimetype = null): array {
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
     * @return ?File
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
     */
    public static function getTypeTemplate(string $mime): string
    {
        return match ($mime) {
            "application/vnd.openxmlformats-officedocument.wordprocessingml.document" => "document",
            "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" => "spreadsheet",
            "application/vnd.openxmlformats-officedocument.presentationml.presentation" => "presentation",
            default => "",
        };
    }

    /**
     * Get mimetype template from format type
     */
    public static function getMimeTemplate(string $type): string
    {
        return match ($type) {
            "document" => "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
            "spreadsheet" => "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
            "presentation" => "application/vnd.openxmlformats-officedocument.presentationml.presentation",
            default => "",
        };
    }

    /**
     * Check template type
     */
    public static function isTemplateType(string $name): bool {
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        return match ($ext) {
            "docx", "xlsx", "pptx" => true,
            default => false,
        };
    }

    /**
     * Check file if it's template
     *
     * @param int $fileId - identifier file
     */
    public static function isTemplate($fileId): bool {
        $template = self::getTemplate($fileId);
        return !empty($template);
    }

    /**
     * Get template by file name
     */
    public static function getEmptyTemplate(string $name): false|string {
        $ext = strtolower("." . pathinfo($name, PATHINFO_EXTENSION));

        $lang = Server::get(IFactory::class)->get("")->getLanguageCode();

        $templatePath = self::getEmptyTemplatePath($lang, $ext);
        if (!file_exists($templatePath)) {
            return false;
        }
        return file_get_contents($templatePath);
    }

    /**
     * Get template path
     *
     * @param string $lang - language
     * @param string $ext - file extension
     */
    public static function getEmptyTemplatePath(string $lang, string $ext): string {
        if (!array_key_exists($lang, self::$localPath)) {
            $lang = "default";
        }

        return dirname(__DIR__) . DIRECTORY_SEPARATOR . "assets" . DIRECTORY_SEPARATOR . "document-templates" . DIRECTORY_SEPARATOR . self::$localPath[$lang] . DIRECTORY_SEPARATOR . "new" . $ext;
    }

    /**
     * Mapping local path to templates
     */
    private static array $localPath = [
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
