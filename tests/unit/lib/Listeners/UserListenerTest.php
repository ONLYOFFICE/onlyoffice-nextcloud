<?php

declare(strict_types=1);

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

namespace OCA\Onlyoffice\Tests\PHP\Listeners;

use Exception;
use OCA\Onlyoffice\ExtraPermissions;
use OCA\Onlyoffice\Listeners\UserListener;
use OCP\IUser;
use OCP\Share\IManager;
use OCP\Share\IShare;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

#[CoversClass(UserListener::class)]
#[AllowMockObjectsWithoutExpectations]
class UserListenerTest extends TestCase {

    private LoggerInterface&MockObject $logger;
    private IManager&MockObject $shareManager;
    private ExtraPermissions&MockObject $extraPermissions;
    private UserListener $userListener;

    public function setUp(): void {
        parent::setUp();

        $this->logger           = $this->createMock(LoggerInterface::class);
        $this->shareManager     = $this->createMock(IManager::class);
        $this->extraPermissions = $this->createMock(ExtraPermissions::class);

        $this->userListener = new UserListener(
            $this->logger,
            $this->shareManager,
            $this->extraPermissions,
        );
    }

    private function makeUser(string $uid): IUser&MockObject {
        $user = $this->createMock(IUser::class);
        $user->method("getUID")->willReturn($uid);

        return $user;
    }

    private function makeShare(string $id): IShare&MockObject {
        $share = $this->createMock(IShare::class);
        $share->method("getId")->willReturn($id);

        return $share;
    }

    /**
     * Deletes extra permissions for shares owned by the user, across every share type, plus direct
     * shares where the user is the recipient - but does not look up shares received via group,
     * room or circle membership, since deleting a mere member never removes those shares.
     */
    public function testBeforeUserDeletedThenUserDeletedDeletesExtraPermissionsForOwnedAndDirectlyReceivedShares(): void {
        $userId = "extra";
        $ownedUserShare = $this->makeShare("10");
        $ownedLinkShare = $this->makeShare("11");
        $receivedUserShare = $this->makeShare("12");

        $this->shareManager->method("getSharesBy")->willReturnCallback(
            fn (string $uid, int $shareType) => match ($uid === $userId ? $shareType : null) {
                IShare::TYPE_USER => [$ownedUserShare],
                IShare::TYPE_LINK => [$ownedLinkShare],
                default => [],
            }
        );
        $this->shareManager->expects($this->once())
            ->method("getSharedWith")
            ->with($userId, IShare::TYPE_USER, null, -1)
            ->willReturn([$receivedUserShare]);

        $this->extraPermissions->expects($this->once())
            ->method("deleteList")
            ->with($this->callback(function (array $ids) {
                sort($ids);
                return $ids === ["10", "11", "12"];
            }));

        $user = $this->makeUser($userId);
        $this->userListener->beforeUserDeleted($user);
        $this->userListener->userDeleted($user);
    }

    /**
     * Leaves extra permissions untouched when no shares were collected for the user beforehand.
     */
    public function testUserDeletedDoesNothingWhenNoSharesWereCollected(): void {
        $this->extraPermissions->expects($this->never())->method("deleteList");

        $this->userListener->userDeleted($this->makeUser("extra"));
    }

    /**
     * Logs and swallows the error instead of letting a share lookup failure abort user deletion.
     */
    public function testBeforeUserDeletedLogsAndSwallowsExceptionWhenShareLookupFails(): void {
        $this->shareManager->method("getSharesBy")->willThrowException(new Exception("Invalid backend"));

        $this->logger->expects($this->once())
            ->method("error")
            ->with($this->stringContains("collecting shares"), $this->arrayHasKey("exception"));

        $this->userListener->beforeUserDeleted($this->makeUser("extra"));
    }

    /**
     * Leaves extra permissions untouched when the earlier share collection failed for this user.
     */
    public function testUserDeletedSkipsExtraPermissionsWhenCollectionPreviouslyFailed(): void {
        $this->shareManager->method("getSharesBy")->willThrowException(new Exception("Invalid backend"));
        $this->extraPermissions->expects($this->never())->method("deleteList");

        $user = $this->makeUser("extra");
        $this->userListener->beforeUserDeleted($user);
        $this->userListener->userDeleted($user);
    }

    /**
     * Logs and swallows the error instead of letting a failure while deleting extra permissions propagate.
     */
    public function testUserDeletedLogsAndSwallowsExceptionWhenDeletingExtraPermissionsFails(): void {
        $this->shareManager->method("getSharesBy")->willReturn([]);
        $this->shareManager->method("getSharedWith")->willReturn([$this->makeShare("13")]);
        $this->extraPermissions->method("deleteList")->willThrowException(new Exception("db gone away"));

        $this->logger->expects($this->atLeastOnce())->method("error");

        $user = $this->makeUser("extra");
        $this->userListener->beforeUserDeleted($user);
        $this->userListener->userDeleted($user);
    }
}
