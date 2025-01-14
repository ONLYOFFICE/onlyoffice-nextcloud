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

namespace OCA\Onlyoffice\Listeners;

use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCA\Onlyoffice\AppConfig;
use OCA\Onlyoffice\SettingsData;
use OCP\AppFramework\Services\IInitialState;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IServerContainer;
use OCP\Util;

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
    public function __construct(
        AppConfig $appConfig,
        IInitialState $initialState,
        IServerContainer $serverContainer
    ) {
        $this->appConfig = $appConfig;
        $this->initialState = $initialState;
        $this->serverContainer = $serverContainer;
    }

    public function handle(Event $event): void {
        if (!$event instanceof LoadAdditionalScriptsEvent) {
            return;
        }

        if (!empty($this->appConfig->getDocumentServerUrl())
            && $this->appConfig->settingsAreSuccessful()
            && $this->appConfig->isUserAllowedToUse()) {
            Util::addScript("onlyoffice", "onlyoffice-desktop");
            Util::addScript("onlyoffice", "onlyoffice-main");
            Util::addScript("onlyoffice", "onlyoffice-template");

            if ($this->appConfig->getSameTab()) {
                Util::addScript("onlyoffice", "onlyoffice-listener");
            }

            if ($this->appConfig->getAdvanced()
                && \OC::$server->getAppManager()->isInstalled("files_sharing")) {
                Util::addScript("onlyoffice", "onlyoffice-share");
                Util::addStyle("onlyoffice", "share");
            }

            $container = $this->serverContainer;
            $this->initialState->provideLazyInitialState("settings", function () use ($container) {
                return $container->query(SettingsData::class);
            });

            Util::addStyle("onlyoffice", "main");
            Util::addStyle("onlyoffice", "template");
            Util::addStyle("onlyoffice", "format");
        }
    }
}
