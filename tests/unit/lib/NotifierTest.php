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

namespace OCA\Onlyoffice\Tests\PHP;

use OCA\Onlyoffice\Notifier;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Notification\IAction;
use OCP\Notification\INotification;
use OCP\Notification\UnknownNotificationException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use Psr\Log\LoggerInterface;
use Test\TestCase;

#[CoversClass(Notifier::class)]
#[AllowMockObjectsWithoutExpectations]
class NotifierTest extends TestCase {

    private string $appName = "onlyoffice";
    private IURLGenerator&Stub $urlGenerator;
    private IUserManager&Stub $userManager;
    private IFactory&Stub $l10nFactory;
    private Notifier $notifier;

    protected function setUp(): void {
        parent::setUp();

        $trans = $this->createStub(IL10N::class);
        $trans->method("t")->willReturnCallback(vsprintf(...));

        $this->l10nFactory = $this->createStub(IFactory::class);
        $this->l10nFactory->method("get")->willReturn($trans);

        $this->urlGenerator = $this->createStub(IURLGenerator::class);
        $this->urlGenerator->method("getAbsoluteURL")->willReturnArgument(0);
        $this->urlGenerator->method("imagePath")->willReturnCallback(
            fn($app, $img) => "/apps/$app/img/$img"
        );
        $this->urlGenerator->method("linkToRouteAbsolute")->willReturn("https://example.com/editor");

        $this->userManager = $this->createStub(IUserManager::class);

        $this->notifier = new Notifier(
            $this->appName,
            $this->l10nFactory,
            $this->urlGenerator,
            $this->createStub(LoggerInterface::class),
            $this->userManager,
        );
    }

    private function makeNotification(string $objectType, string $objectId = "subject", array $parameters = []): INotification&MockObject {
        $action = $this->createMock(IAction::class);
        $action->method("setLabel")->willReturnSelf();
        $action->method("setParsedLabel")->willReturnSelf();
        $action->method("setLink")->willReturnSelf();
        $action->method("setPrimary")->willReturnSelf();

        $notification = $this->createMock(INotification::class);
        $notification->method("getApp")->willReturn($this->appName);
        $notification->method("getObjectType")->willReturn($objectType);
        $notification->method("getObjectId")->willReturn($objectId);
        $notification->method("getSubjectParameters")->willReturn($parameters);
        $notification->method("createAction")->willReturn($action);
        $notification->method("setParsedSubject")->willReturnSelf();
        $notification->method("setParsedMessage")->willReturnSelf();
        $notification->method("setIcon")->willReturnSelf();
        $notification->method("setRichSubject")->willReturnSelf();
        $notification->method("addParsedAction")->willReturnSelf();

        return $notification;
    }

    /**
     * getID returns the app name.
     */
    public function testGetIdReturnsAppName(): void {
        $this->assertSame($this->appName, $this->notifier->getID());
    }

    /**
     * getName returns the app name.
     */
    public function testGetNameReturnsAppName(): void {
        $this->assertSame($this->appName, $this->notifier->getName());
    }

    /**
     * prepare throws UnknownNotificationException when the notification is from a different app.
     */
    public function testPrepareThrowsForUnknownApp(): void {
        $notification = $this->createStub(INotification::class);
        $notification->method("getApp")->willReturn("other_app");

        $this->expectException(UnknownNotificationException::class);

        $this->notifier->prepare($notification, "en");
    }

    /**
     * Sets the notification subject to the object ID and a descriptive message for editorsCheck notifications.
     */
    public function testPrepareEditorsCheckSetsParsedSubjectAndAction(): void {
        $notification = $this->makeNotification("editorsCheck", "Document server is not available");

        $parsedSubject = "";
        $parsedMessage = "";
        $actionAdded = false;

        $notification->method("setParsedSubject")->willReturnCallback(function ($s) use ($notification, &$parsedSubject) { $parsedSubject = $s; return $notification; });
        $notification->method("setParsedMessage")->willReturnCallback(function ($m) use ($notification, &$parsedMessage) { $parsedMessage = $m; return $notification; });
        $notification->method("addParsedAction")->willReturnCallback(function () use ($notification, &$actionAdded) { $actionAdded = true; return $notification; });

        $this->notifier->prepare($notification, "en");

        $this->assertSame("Document server is not available", $parsedSubject);
        $this->assertNotEmpty($parsedMessage);
        $this->assertTrue($actionAdded);
    }

    /**
     * Sets a subject containing the notifier's display name and the file name for mention notifications.
     */
    public function testPrepareMentionSetsSubjectWithNotifierAndFileName(): void {
        $user = $this->createStub(IUser::class);
        $user->method("getDisplayName")->willReturn("Jane Doe");
        $this->userManager->method("get")->willReturn($user);

        $notification = $this->makeNotification("mention", "Comment text", [
            "notifierId" => "janedoe",
            "fileId" => 42,
            "fileName" => "report.docx",
            "anchor" => "anchor1",
        ]);

        $parsedSubject = "";
        $notification->method("setParsedSubject")->willReturnCallback(function ($s) use ($notification, &$parsedSubject) {
            $parsedSubject = $s;
            return $notification;
        });

        $this->notifier->prepare($notification, "en");

        $this->assertStringContainsString("Jane Doe", $parsedSubject);
        $this->assertStringContainsString("report.docx", $parsedSubject);
    }

    /**
     * Sets a subject containing the file name and adds an action for document_unsaved notifications.
     */
    public function testPrepareDocumentUnsavedSetsParsedSubjectAndAction(): void {
        $notification = $this->makeNotification("document_unsaved", "doc-unsaved", [
            "fileId" => 99,
            "fileName" => "draft.docx",
        ]);

        $parsedSubject = "";
        $actionAdded = false;

        $notification->method("setParsedSubject")->willReturnCallback(function ($s) use ($notification, &$parsedSubject) {
            $parsedSubject = $s;
            return $notification;
        });
        $notification->method("addParsedAction")->willReturnCallback(function () use ($notification, &$actionAdded) {
            $actionAdded = true;
            return $notification;
        });

        $this->notifier->prepare($notification, "en");

        $this->assertStringContainsString("draft.docx", $parsedSubject);
        $this->assertTrue($actionAdded);
    }

    /**
     * Returns the notification unchanged for an unknown object type.
     */
    public function testPrepareReturnsNotificationForUnknownObjectType(): void {
        $notification = $this->makeNotification("unknown_type");

        $result = $this->notifier->prepare($notification, "en");

        $this->assertSame($notification, $result);
    }
}
