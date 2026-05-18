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

use Exception;
use OCA\Files_Versions\Events\VersionRestoredEvent;
use OCA\Files_Versions\Versions\IVersion;
use OCA\Onlyoffice\FileVersions;
use OCA\Onlyoffice\KeyManager;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use Psr\Log\LoggerInterface;
use OCP\Files\File;

/**
 * OCA\Files_Versions events listener
 */
class FileVersionsListener implements IEventListener {

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly KeyManager $keyManager
    ) {}

    public function handle(Event $event): void {
        if ($event instanceof VersionRestoredEvent) {
            $this->versionRestored($event->getVersion());
        }
    }

    public function versionRestored(IVersion $version): void {
        $file = $version->getSourceFile();

        if (!$file instanceof File) {
            return;
        }

        if (empty($file->getOwner())) {
            return;
        }

        $this->deleteKeyForFile($file);
        $this->deleteVersion($version);
    }

    private function deleteKeyForFile(File $file, bool $unlock = false): void {
        try {
            $this->keyManager->delete($file->getId(), $unlock);
        } catch (Exception $e) {
            $this->logger->error(
                "VersionRestoredEvent: deleting key for file {$file->getId()}",
                ["exception" => $e]
            );
        }
    }

    private function deleteVersion(IVersion $version): void {
        $file = $version->getSourceFile();
        try {
            FileVersions::deleteVersion($file->getOwner()->getUID(), $file, $version->getRevisionId());
        } catch (Exception $e) {
            $this->logger->error(
                "VersionRestoredEvent: deleting version for file {$file->getId()}",
                ["exception" => $e]
            );
        }
    }
}
