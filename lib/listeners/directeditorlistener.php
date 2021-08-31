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

namespace OCA\Onlyoffice\Listeners;

use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\DirectEditing\RegisterDirectEditorEvent;

use OCA\Onlyoffice\AppConfig;
use OCA\Onlyoffice\DirectEditor;

/**
 * DirectEditor listener
 */
class DirectEditorListener implements IEventListener {

    /**
     * Application configuration
     *
     * @var AppConfig
     */
    private $appConfig;

    /**
     * Direct editor
     *
     * @var DirectEditor
     */
    private $editor;

    /**
     * @param AppConfig $config - application configuration
     * @param DirectEditor $editor - direct editor
     */
    public function __construct(AppConfig $appConfig,
                                    DirectEditor $editor) {
        $this->appConfig = $appConfig;
        $this->editor = $editor;
    }

    public function handle(Event $event): void {
        if (!$event instanceof RegisterDirectEditorEvent) {
            return;
        }

        if (!empty($this->appConfig->GetDocumentServerUrl())
            && $this->appConfig->SettingsAreSuccessful()) {

            $event->register($this->editor);
        }
    }
}