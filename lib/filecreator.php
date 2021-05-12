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

use OCP\DirectEditing\ACreateEmpty;
use OCP\Files\File;
use OCP\IL10N;
use OCP\ILogger;

use OCA\Onlyoffice\TemplateManager;

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
     * Format for creation
     *
     * @var string
     */
    private $format;

    /**
     * @param string $AppName - application name
     * @param IL10N $trans - l10n service
     * @param ILogger $logger - logger
     * @param string $format - format for creation 
     */
    public function __construct($AppName,
                                IL10N $trans,
                                ILogger $logger,
                                $format) {
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
                return $this->trans->t("Spreadsheet");
            case "pptx":
                return $this->trans->t("Presentation");
        }
        return $this->trans->t("Document");
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
        $this->logger->debug("FileCreator: " . $file->getId() . " " . $file->getName() . " $creatorId $templateId", ["app" => $this->appName]);

        $fileName = $file->getName();
        $template = TemplateManager::GetEmptyTemplate($fileName);

        if (!$template) {
            $this->logger->error("FileCreator: Template for file creation not found: $templateId", ["app" => $this->appName]);
            return;
        }

        try {
            $file->putContent($template);
        } catch (NotPermittedException $e) {
            $this->logger->logException($e, ["message" => "FileCreator: Can't create file: $fileName", "app" => $this->appName]);
        }
    }
}
