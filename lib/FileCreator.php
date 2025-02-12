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

use OCP\DirectEditing\ACreateEmpty;
use OCP\Files\File;
use OCP\IL10N;
use Psr\Log\LoggerInterface;

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
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Format for creation
     *
     * @var string
     */
    private $format;

    /**
     * @param string $AppName - application name
     * @param IL10N $trans - l10n service
     * @param LoggerInterface $logger - logger
     * @param string $format - format for creation
     */
    public function __construct(
        $AppName,
        IL10N $trans,
        LoggerInterface $logger,
        $format
    ) {
        $this->appName = $AppName;
        $this->trans = $trans;
        $this->logger = $logger;
        $this->format = $format;
    }

    /**
     * Unique id for the creator to filter templates
     *
     * @return string
     */
    public function getId(): string {
        return $this->appName . "_" . $this->format;
    }

    /**
     * Descriptive name for the create action
     *
     * @return string
     */
    public function getName(): string {
        switch ($this->format) {
            case "xlsx":
                return $this->trans->t("New spreadsheet");
            case "pptx":
                return $this->trans->t("New presentation");
        }
        return $this->trans->t("New document");
    }

    /**
     * Default file extension for the new file
     *
     * @return string
     */
    public function getExtension(): string {
        return $this->format;
    }

    /**
     * Mimetype of the resulting created file
     *
     * @return array
     */
    public function getMimetype(): string {
        switch ($this->format) {
            case "xlsx":
                return "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet";
            case "pptx":
                return "application/vnd.openxmlformats-officedocument.presentationml.presentation";
        }
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
        $this->logger->debug("FileCreator: " . $file->getId() . " " . $file->getName() . " $creatorId $templateId");

        $fileName = $file->getName();
        $template = TemplateManager::getEmptyTemplate($fileName);

        if (!$template) {
            $this->logger->error("FileCreator: Template for file creation not found: $templateId");
            return;
        }

        try {
            $file->putContent($template);
        } catch (NotPermittedException $e) {
            $this->logger->error("FileCreator: Can't create file: $fileName", ["exception" => $e]);
        }
    }
}
