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

use OCP\DirectEditing\ATemplate;

/**
 * Template for direct editor
 *
 * @package OCA\Onlyoffice
 */
class DirectTemplate extends ATemplate {


    /**
     * l10n service
     *
     * @var string
     */
    private $id;

    /**
     * Logger
     *
     * @var string
     */
    private $title;

    /**
     * Format for creation
     *
     * @var string
     */
    private $preview;

    /**
     * @param string $id - application name
     * @param string $title - logger
     * @param string $preview - format for creation 
     */
    public function __construct(string $id,
                                string $title,
                                string $preview) {
        $this->id = $id;
        $this->title = $title;
        $this->preview = $preview;
    }

    /**
     * Return a unique id so the app can identify the template
     *
     * @return string
     */
    public function getId(): string {
        return $this->id;
    }

    /**
     * Return a title that is displayed to the user
     *
     * @return string
     */
    public function getTitle(): string {
        return $this->title;
    }

    /**
     * Return a link to the template preview image
     *
     * @return string
     */
    public function getPreview(): string {
        return $this->preview;
    }
}