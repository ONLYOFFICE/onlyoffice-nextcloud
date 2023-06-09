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

use OCA\Files\Event\LoadAdditionalScriptsEvent;

use OCA\Onlyoffice\AppConfig;
use OCA\Onlyoffice\SettingsData;

/**
 * File listener
 */
class FilesListener implements IEventListener {

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
        if (!$event instanceof LoadAdditionalScriptsEvent) {
            return;
        }

        if (!empty($this->appConfig->GetDocumentServerUrl())
            && $this->appConfig->SettingsAreSuccessful()
            && $this->appConfig->isUserAllowedToUse()) {

            Util::addScript("onlyoffice", "desktop");
            Util::addScript("onlyoffice", "main");
            Util::addScript("onlyoffice", "template");

            if ($this->appConfig->GetSameTab()) {
                Util::addScript("onlyoffice", "listener");
            }

            if ($this->appConfig->GetAdvanced()
                && \OC::$server->getAppManager()->isInstalled("files_sharing")) {
                Util::addScript("onlyoffice", "share");
                Util::addStyle("onlyoffice", "share");
            }

            $container = $this->serverContainer;
            $this->initialState->provideLazyInitialState("settings", function () use ($container) {
                return $container->query(SettingsData::class);
            });

            Util::addStyle("onlyoffice", "main");
            Util::addStyle("onlyoffice", "template");
        }
    }
}