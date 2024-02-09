<?php
/**
 *
 * (c) Copyright Ascensio System SIA 2024
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

use OCA\Onlyoffice\AppConfig;
use OCP\Dashboard\RegisterWidgetEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Util;

/**
 * Widget listener
 */
class WidgetListener implements IEventListener {

    /**
     * Application configuration
     *
     * @var AppConfig
     */
    private $appConfig;

    /**
     * @param AppConfig $config - application configuration
     */
    public function __construct(AppConfig $appConfig) {
        $this->appConfig = $appConfig;
    }

    public function handle(Event $event): void {
        if (!$event instanceof RegisterWidgetEvent) {
            return;
        }

        if (!empty($this->appConfig->getDocumentServerUrl())
            && $this->appConfig->settingsAreSuccessful()
            && $this->appConfig->isUserAllowedToUse()) {
            Util::addScript("onlyoffice", "desktop");
        }
    }
}
