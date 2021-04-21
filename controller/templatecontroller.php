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

namespace OCA\Onlyoffice\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\FileDisplayResponse;
use OCP\AppFramework\Http;
use OCP\Files\NotFoundException;
use OCP\ILogger;
use OCP\IRequest;
use OCP\IL10N;

use OCA\Onlyoffice\TemplateManager;

/**
 * OCS handler
 */
class TemplateController extends Controller {

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
     * @param ILogger $logger - logger
     * @param IL10N $trans - l10n service
     */
    public function __construct($AppName,
                                    IRequest $request,
                                    ILogger $logger,
                                    IL10N $trans
                                    ) {
        parent::__construct($AppName, $request);

        $this->logger = $logger;
        $this->trans = $trans;
    }

    /**
     * Add global template
     *
     * @return array
     */
    public function AddTemplate() {

        $file = $this->request->getUploadedFile("file");

        if (!is_null($file)) {
            if (is_uploaded_file($file["tmp_name"]) && $file["error"] === 0) {
                if (!TemplateManager::IsTemplateType($file["name"])) {
                    return [
                        "error" => $this->trans->t("Template must be OOXML format")
                    ];
                }

                $templateDir = TemplateManager::GetGlobalTemplateDir();
                if ($templateDir->nodeExists($file["name"])) {
                    return [
                        "error" => $this->trans->t("Template already exist")
                    ];
                }

                $templateContent = file_get_contents($file["tmp_name"]);

                $template = $templateDir->newFile($file["name"]);
                $template->putContent($templateContent);

                $fileInfo = $template->getFileInfo();
                $result = [
                    "id" => $fileInfo->getId(),
                    "name" => $fileInfo->getName(),
                    "type" => TemplateManager::GetTypeTemplate($fileInfo->getMimeType())
                ];

                return $result;
            }
        }

        return [
            "error" => $this->trans->t("Invalid file provided")
        ];
    }

    /**
     * Delete template
     *
     * @param string $templateId - file identifier
     *
     * @return array
     */
    public function DeleteTemplate($templateId) {
        $templateDir = TemplateManager::GetGlobalTemplateDir();

        try {
            $templates = $templateDir->getById($templateId);
        } catch(\Exception $e) {
            $this->logger->logException($e, ["message" => "DeleteTemplate: $templateId", "app" => $this->AppName]);
            return [
                "error" => $this->trans->t("Failed to delete template")
            ];
        }

        if (empty($templates)) {
            $this->logger->info("Template not found: $templateId", ["app" => $this->AppName]);
            return [
                "error" => $this->trans->t("Failed to delete template")
            ];
        }

        $templates[0]->delete();

        $this->logger->debug("Template: deleted " . $templates[0]->getName(), ["app" => $this->appName]);
        return [];
    }
}