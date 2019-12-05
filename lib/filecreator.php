<?php
/**
 *
 * (c) Copyright Ascensio System SIA 2019
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

use OCP\DirectEditing\ACreateEmpty;
use OCP\Files\File;
use OCP\IL10N;
use OCP\ILogger;

/**
 * File creator
 *
 * @package OCA\Onlyoffice
 */
class FileCreator extends ACreateEmpty {

    /**
     * Application name
     *
     * @var string
     */
    private $appName;

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
     * @param string $AppName - application name
     * @param IL10N $trans - l10n service
     * @param ILogger $logger - logger
     */
    public function __construct($AppName,
                                IL10N $trans,
                                ILogger $logger) {
        $this->appName = $AppName;
        $this->trans = $trans;
        $this->logger = $logger;
    }

    /**
     * Unique id for the creator to filter templates
     *
     * @return string
     */
    public function getId(): string {
        return $this->appName;
    }

    /**
     * Descriptive name for the create action
     *
     * @return string
     */
    public function getName(): string {
        return $this->trans->t("Document");
    }

    /**
     * Default file extension for the new file
     *
     * @return string
     */
    public function getExtension(): string {
        return "docx";
    }

    /**
     * Mimetype of the resulting created file
     *
     * @return array
     */
    public function getMimetype(): string {
        return "application/vnd.openxmlformats-officedocument.wordprocessingml.document";
    }

    /**
     * Add content when creating empty files
     *
     * @param File $file - empty file
     * @param string $creatorId - creator id
     * @param string $templateId - teamplate id
     */
    public function create(File $file, string $creatorId = null, string $templateId = null): void {
        $this->logger->debug("FileCreator: " . $file->getId() . " " . $file->getName() . " $creatorId $templateId", array("app" => $this->appName));

        $fileName = $file->getName();
        $template = self::GetTemplate($fileName);

        if (!$template) {
            $this->logger->error("FileCreator: Template for file creation not found: $templatePath", array("app" => $this->appName));
            return;
        }

        try {
            $file->putContent($template);
        } catch (NotPermittedException $e) {
            $this->logger->error("FileCreator: Can't create file: $name", array("app" => $this->appName));
        }
    }

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
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . "assets" . DIRECTORY_SEPARATOR . $lang . DIRECTORY_SEPARATOR . "new" . $ext;
    }
}
