<?php
/**
 *
 * (c) Copyright Ascensio System SIA 2023
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
use OCP\Util;
use OCP\AppFramework\Services\IInitialState;
use OCP\IServerContainer;

use OCA\Files_Sharing\Event\BeforeTemplateRenderedEvent;

use OCA\Onlyoffice\AppConfig;
use OCA\Onlyoffice\SettingsData;

/**
 * File Sharing listener
 */
class FileSharingListener implements IEventListener {

    /**
     * Application configuration
     *
     * @var AppConfig
     */
    private $appConfig;

    /**
     * Initial state
     *
     * @var IInitialState
     */
    private $initialState;

    /**
     * Server container
     *
     * @var IServerContainer
     */
    private $serverContainer;

    /**
     * @param AppConfig $config - application configuration
     * @param IInitialState $initialState - initial state
     * @param IServerContainer $serverContainer - server container
     */
    public function __construct(AppConfig $appConfig,
                                IInitialState $initialState,
                                IServerContainer $serverContainer) {
        $this->appConfig = $appConfig;
        $this->initialState = $initialState;
        $this->serverContainer = $serverContainer;
    }

    public function handle(Event $event): void {
        if (!$event instanceof BeforeTemplateRenderedEvent) {
            return;
        }

        if (!empty($this->appConfig->GetDocumentServerUrl())
            && $this->appConfig->SettingsAreSuccessful()) {
            Util::addScript("onlyoffice", "main");

            if ($this->appConfig->GetSameTab()) {
                Util::addScript("onlyoffice", "listener");
            }

            $container = $this->serverContainer;
            $this->initialState->provideLazyInitialState("settings", function () use ($container) {
                return $container->query(SettingsData::class);
            });

            Util::addStyle("onlyoffice", "main");
        }
    }
}