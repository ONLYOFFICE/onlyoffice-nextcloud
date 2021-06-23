<?php
/**
 *
 * (c) Copyright Ascensio System SIA 2020
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

use OCP\Files\File;
use OCP\Files\Template\ICustomTemplateProvider;
use OCP\Files\Template\Template;
use OCP\IURLGenerator;

use OCA\Onlyoffice\TemplateManager;

class TemplateProvider implements ICustomTemplateProvider {

    /**
     * Application name
     *
     * @var string
     */
    private $appName;

    /**
     * Url generator service
     *
     * @var IURLGenerator
     */
    private $urlGenerator;

    /**
     * @param string $AppName - application name
     * @param IURLGenerator $urlGenerator - url generator service
     */
    public function __construct($AppName, IURLGenerator $urlGenerator) {
        $this->appName = $AppName;
        $this->urlGenerator = $urlGenerator;
    }

    /**
     * Return a list of additional templates that the template provider is offering
     *
     * @param string $template - mimetype of the template
     *
     * @return array
     */
    public function getCustomTemplates($mimetype) : array {
        $templates = [];

        $templateFiles = TemplateManager::GetGlobalTemplates($mimetype);

        foreach ($templateFiles as $templateFile) {
            $template = new Template(
                TemplateProvider::class,
                $templateFile->getId(),
                $templateFile
            );

            $template->setCustomPreviewUrl($this->urlGenerator->linkToRouteAbsolute($this->appName . ".template.preview", ["fileId" => $templateFile->getId()]));

            array_push($templates, $template);
        }

        return $templates;
    }

    /**
     * Return the file for a given template id
     *
     * @param string $templateId - identifier of the template
     *
     * @return File
     */
    public function getCustomTemplate($templateId) : File {
        return TemplateManager::GetTemplate($templateId);
    }
}