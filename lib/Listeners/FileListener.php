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
use OC\Files\Node\Node;
use OCA\Onlyoffice\ExtraPermissions;
use OCA\Onlyoffice\FileVersions;
use OCA\Onlyoffice\KeyManager;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Events\Node\NodeDeletedEvent;
use OCP\Files\Events\Node\NodeWrittenEvent;
use OCP\Share\IShare;
use Psr\Log\LoggerInterface;
use OCP\Files\File;
use OCP\Share\IManager;

/**
 * OCP\Files events listener
 */
class FileListener implements IEventListener {

    public function __construct(
        private readonly IManager $shareManager,
        private readonly LoggerInterface $logger,
        private readonly ExtraPermissions $extraPermissions,
        private readonly KeyManager $keyManager
    ) {}

    public function handle(Event $event): void {
        if ($event instanceof NodeDeletedEvent) {
            $this->nodeDeleted($event->getNode());
        }

        if ($event instanceof NodeWrittenEvent) {
            $this->nodeWritten($event->getNode());
        }
    }

    public function nodeDeleted(Node $node): void {
        if (!$node instanceof File) {
            return;
        }

        if ($node->getOwner() === null) {
            return;
        }

        $this->deleteKeyForFile($node);
        $this->deleteVersionsForFile($node);
        $this->deleteExtraPermissionsForFile($node);
    }

    public function nodeWritten(Node $node): void {
        if (!$node instanceof File) {
            return;
        }

        try {
            $this->keyManager->delete($node->getId());
        } catch (Exception $e) {
            $this->logger->error(
                "NodeWrittenEvent: deleting key for file {$node->getId()}",
                ["exception" => $e]
            );
        }
    }

    private function deleteExtraPermissionsForFile(File $file): void {
        $shareTypes = [
            IShare::TYPE_USER,
            IShare::TYPE_GROUP,
            IShare::TYPE_LINK,
            IShare::TYPE_ROOM,
        ];
        $shares = [];

        foreach ($shareTypes as $shareType) {
            $shares = array_merge(
                $shares,
                $this->shareManager->getSharesBy($file->getOwner()->getUID(), $shareType, $file)
            );
        }

        $shareIds = array_map(fn(IShare $share): string => $share->getId(), $shares);

        try {
            if ($shareIds !== []) {
                $this->extraPermissions->deleteList($shareIds);
            }
        } catch (Exception $e) {
            $this->logger->error(
                "NodeDeletedEvent: extra permissions for file {$file->getId()}",
                ["exception" => $e]
            );
        }
    }

    private function deleteKeyForFile(File $file): void {
        try {
            $this->keyManager->delete($file->getId(), true);
        } catch (Exception $e) {
            $this->logger->error(
                "NodeDeletedEvent: deleting key for file {$file->getId()}",
                ["exception" => $e]
            );
        }
    }

    private function deleteVersionsForFile(File $file): void {
        try {
            FileVersions::deleteAllVersions($file->getOwner()->getUID(), $file);
        } catch (Exception $e) {
            $this->logger->error(
                "NodeDeletedEvent: deleting all versions for file {$file->getId()}",
                ["exception" => $e]
            );
        }
    }
}
