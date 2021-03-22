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


/**
 * Template manager
 *
 * @package OCA\Onlyoffice
 */
class TemplateManager {

    /**
     * Mapping local path to templates
     *
     * @var Array
     */
    private static $localPath = [
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
        "nl" => "nl-NL",
        "pl" => "pl-PL",
        "pt_BR" => "pt-BR",
        "pt_PT" => "pt-PT",
        "ru" => "ru-RU",
        "sv" => "sv-SE",
        "zh_CN" => "zh-CN"
    ];

    /**
     * Get template
     *
     * @param string $name - file name
     *
     * @return string
     */
    public static function GetTemplate(string $name) {
        $ext = strtolower("." . pathinfo($name, PATHINFO_EXTENSION));

        $lang = \OC::$server->getL10NFactory("")->get("")->getLanguageCode();

        $templatePath = self::getTemplatePath($lang, $ext);
        if (!file_exists($templatePath)) {
            $lang = "en";
            $templatePath = self::getTemplatePath($lang, $ext);
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
    private static function GetTemplatePath(string $lang, string $ext) {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . "assets" . DIRECTORY_SEPARATOR . self::$localPath[$lang] . DIRECTORY_SEPARATOR . "new" . $ext;
    }
}
