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
use OCA\Onlyoffice\ExtraPermissions;
use OCA\Onlyoffice\FileVersions;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IUser;
use OCP\Share\IManager;
use OCP\Share\IShare;
use OCP\User\Events\BeforeUserDeletedEvent;
use OCP\User\Events\UserDeletedEvent;
use Psr\Log\LoggerInterface;

/**
 * OCP\User events listener
 */
class UserListener implements IEventListener {

    /**
     * Share ids for removal
     *
     * @var array<string, string[]>
     */
    private array $pendingShareIds = [];

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly IManager $shareManager,
        private readonly ExtraPermissions $extraPermissions,
    ) {}

    public function handle(Event $event): void {
        if ($event instanceof BeforeUserDeletedEvent) {
            $this->beforeUserDeleted($event->getUser());
        }

        if ($event instanceof UserDeletedEvent) {
            $this->userDeleted($event->getUser());
        }
    }

    public function beforeUserDeleted(IUser $user): void {
        try {
            $this->pendingShareIds[$user->getUID()] = $this->getShareIdsForUser($user->getUID());
        } catch (Exception $e) {
            $this->logger->error(
                "BeforeUserDeletedEvent: collecting shares for userId {$user->getUID()}",
                ["exception" => $e]
            );
        }
    }

    public function userDeleted(IUser $user): void {
        try {
            FileVersions::deleteAllVersions($user->getUID());
        } catch (Exception $e) {
            $this->logger->error(
                "UserDeletedEvent: userId {$user->getUID()}",
                ["exception" => $e]
            );
        }

        try {
            $this->deleteExtraPermissionsForUser($user->getUID());
        } catch (Exception $e) {
            $this->logger->error(
                "UserDeletedEvent: deleting extra permissions for userId {$user->getUID()}",
                ["exception" => $e]
            );
        }
    }

    /**
     * Get ids of shares owned by the user, plus direct user shares received by them.
     */
    private function getShareIdsForUser(string $userId): array {
        $ownedShareTypes = [
            IShare::TYPE_USER,
            IShare::TYPE_GROUP,
            IShare::TYPE_LINK,
            IShare::TYPE_ROOM,
            IShare::TYPE_CIRCLE,
        ];

        $shareIds = [];
        foreach ($ownedShareTypes as $shareType) {
            foreach ($this->shareManager->getSharesBy($userId, $shareType, null, false, -1) as $share) {
                $shareIds[] = $share->getId();
            }
        }
        foreach ($this->shareManager->getSharedWith($userId, IShare::TYPE_USER, null, -1) as $share) {
            $shareIds[] = $share->getId();
        }

        return array_unique($shareIds);
    }

    /**
     * Delete extra permissions for shares
     */
    private function deleteExtraPermissionsForUser(string $userId): void {
        $shareIds = $this->pendingShareIds[$userId] ?? [];
        unset($this->pendingShareIds[$userId]);

        if ($shareIds !== []) {
            $this->extraPermissions->deleteList($shareIds);
        }
    }
}
