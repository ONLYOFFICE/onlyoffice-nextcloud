<?php
/*
 * Copyright (C) Ascensio System SIA, 2009-2026
 *
 * This program is a free software product. You can redistribute it and/or
 * modify it under the terms of the GNU Affero General Public License (AGPL)
 * version 3 as published by the Free Software Foundation, together with the
 * additional terms provided in the LICENSE file.
 *
 * This program is distributed WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. For
 * details, see the GNU AGPL at: https://www.gnu.org/licenses/agpl-3.0.html
 *
 * You can contact Ascensio System SIA by email at info@onlyoffice.com
 * or by postal mail at 20A-6 Ernesta Birznieka-Upisha Street, Riga,
 * LV-1050, Latvia, European Union.
 *
 * The interactive user interfaces in modified versions of the Program
 * are required to display Appropriate Legal Notices in accordance with
 * Section 5 of the GNU AGPL version 3.
 *
 * No trademark rights are granted under this License.
 *
 * All non-code elements of the Product, including illustrations,
 * icon sets, and technical writing content, are licensed under the
 * Creative Commons Attribution-ShareAlike 4.0 International License:
 * https://creativecommons.org/licenses/by-sa/4.0/legalcode
 *
 * This license applies only to such non-code elements and does not
 * modify or replace the licensing terms applicable to the Program's
 * source code, which remains licensed under the GNU Affero General
 * Public License v3.
 *
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace OCA\Onlyoffice;

use OCP\DirectEditing\ACreateEmpty;
use OCP\Files\File;
use OCP\Files\NotPermittedException;
use OCP\IL10N;
use Psr\Log\LoggerInterface;

/**
 * File creator
 *
 * @package OCA\Onlyoffice
 */
class FileCreator extends ACreateEmpty {

    public function __construct(
        private readonly string $appName,
        private readonly IL10N $trans,
        private readonly LoggerInterface $logger,
        private readonly string $format
    ) {}

    /**
     * Unique id for the creator to filter templates
     */
    public function getId(): string {
        return $this->appName . "_" . $this->format;
    }

    /**
     * Descriptive name for the create action
     */
    public function getName(): string
    {
        return match ($this->format) {
            "xlsx" => $this->trans->t("New spreadsheet"),
            "pptx" => $this->trans->t("New presentation"),
            default => $this->trans->t("New document"),
        };
    }

    /**
     * Default file extension for the new file
     */
    public function getExtension(): string {
        return $this->format;
    }

    /**
     * Mimetype of the resulting created file
     */
    public function getMimetype(): string
    {
        return match ($this->format) {
            "xlsx" => "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
            "pptx" => "application/vnd.openxmlformats-officedocument.presentationml.presentation",
            default => "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
        };
    }

    /**
     * Add content when creating empty files
     */
    public function create(File $file, ?string $creatorId = null, ?string $templateId = null): void {
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
