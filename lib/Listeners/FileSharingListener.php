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

namespace OCA\Onlyoffice\Listeners;

use OCA\Files_Sharing\Event\BeforeTemplateRenderedEvent;
use OCA\Onlyoffice\AppConfig;
use OCA\Onlyoffice\SettingsData;
use OCP\AppFramework\Services\IInitialState;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Server;
use OCP\Util;

/**
 * File Sharing listener
 */
class FileSharingListener implements IEventListener {

    public function __construct(
        private readonly AppConfig $appConfig,
        private readonly IInitialState $initialState
    ) {}

    public function handle(Event $event): void {
        if (!$event instanceof BeforeTemplateRenderedEvent) {
            return;
        }

        if (!empty($this->appConfig->getDocumentServerUrl())
            && $this->appConfig->settingsAreSuccessful()) {
            $shareType = "";
            if (method_exists($event, "getShare")) {
                $share = $event->getShare();
                $shareType = $share->getNodeType();

                $sharedBy = $share->getSharedBy();
                if (!$this->appConfig->isUserAllowedToUse($sharedBy)) {
                    return;
                }
            }

            if ($this->appConfig->getSameTab() || $shareType === "file") {
                Util::addScript("onlyoffice", "onlyoffice-listener");
                Util::addStyle("onlyoffice", "onlyoffice-listener");
            }

            $this->initialState->provideLazyInitialState("settings", fn() => Server::get(SettingsData::class));

            Util::addScript("onlyoffice", "onlyoffice-main");
            Util::addStyle("onlyoffice", "onlyoffice-main");
            Util::addStyle("onlyoffice", "main");
            Util::addStyle("onlyoffice", "format");
        }
    }
}
