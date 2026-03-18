<?php

declare(strict_types=1);

/**
 *
 * (c) Copyright Ascensio System SIA 2026
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

namespace OCA\Onlyoffice\Tests\PHP;

use OCA\Onlyoffice\EmailManager;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Mail\IEMailTemplate;
use OCP\Mail\IMailer;
use OCP\Mail\IMessage;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

#[CoversClass(EmailManager::class)]
#[AllowMockObjectsWithoutExpectations]
class EmailManagerTest extends TestCase {

    private IMailer&MockObject $mailer;
    private IUserManager&MockObject $userManager;
    private IURLGenerator&MockObject $urlGenerator;
    private EmailManager $emailManager;

    public function setUp(): void {
        parent::setUp();

        $trans = $this->createStub(IL10N::class);
        $trans->method("t")->willReturnArgument(0);

        $this->mailer       = $this->createMock(IMailer::class);
        $this->userManager  = $this->createMock(IUserManager::class);
        $this->urlGenerator = $this->createMock(IURLGenerator::class);

        $this->emailManager = new EmailManager(
            "onlyoffice",
            $trans,
            $this->createStub(LoggerInterface::class),
            $this->mailer,
            $this->userManager,
            $this->urlGenerator,
        );
    }

    private function makeUser(string $email = "user@example.com", string $name = "User"): IUser&MockObject {
        $user = $this->createMock(IUser::class);
        $user->method("getEMailAddress")->willReturn($email);
        $user->method("getDisplayName")->willReturn($name);
        return $user;
    }

    private function stubSuccessfulSend(): void {
        $template = $this->createStub(IEMailTemplate::class);
        $this->mailer->method("createEMailTemplate")->willReturn($template);

        $message = $this->createStub(IMessage::class);
        $this->mailer->method("createMessage")->willReturn($message);
        $this->mailer->method("send")->willReturn([]);
    }

    /**
     * Returns false immediately when the recipient user does not exist in the user manager.
     */
    public function testNotifyMentionEmailReturnsFalseWhenRecipientNotFound(): void {
        $this->userManager->method("get")->willReturn(null);

        $result = $this->emailManager->notifyMentionEmail("notifier", "recipient", "1", "file.docx", "#anchor", "comment text");

        $this->assertFalse($result);
    }

    /**
     * Returns false when the recipient exists but has no email address configured.
     */
    public function testNotifyMentionEmailReturnsFalseWhenRecipientHasNoEmail(): void {
        $recipient = $this->makeUser("");
        $this->userManager->method("get")->willReturn($recipient);

        $result = $this->emailManager->notifyMentionEmail("notifier", "recipient", "1", "file.docx", "#anchor", "comment text");

        $this->assertFalse($result);
    }

    /**
     * Returns false when the notifier user does not exist, as their display name is needed to build the email.
     */
    public function testNotifyMentionEmailReturnsFalseWhenNotifierNotFound(): void {
        $recipient = $this->makeUser("recipient@example.com");

        $this->userManager->method("get")->willReturnCallback(
            fn($id) => $id === "recipient" ? $recipient : null
        );

        $result = $this->emailManager->notifyMentionEmail("notifier", "recipient", "1", "file.docx", "#anchor", "comment text");

        $this->assertFalse($result);
    }

    /**
     * Returns true when both users exist, have email addresses, and the mailer sends successfully.
     */
    public function testNotifyMentionEmailReturnsTrueOnSuccess(): void {
        $recipient = $this->makeUser("recipient@example.com", "Recipient");
        $notifier  = $this->makeUser("notifier@example.com", "Notifier");

        $this->userManager->method("get")->willReturnCallback(
            fn($id) => match($id) {
                "recipient" => $recipient,
                "notifier"  => $notifier,
                default     => null,
            }
        );

        $this->urlGenerator->method("linkToRouteAbsolute")->willReturn("https://example.com/file");
        $this->stubSuccessfulSend();

        $result = $this->emailManager->notifyMentionEmail("notifier", "recipient", "1", "file.docx", "#anchor", "comment text");

        $this->assertTrue($result);
    }

    /**
     * Catches a mailer exception gracefully and returns false instead of propagating it.
     */
    public function testNotifyMentionEmailReturnsFalseWhenMailerThrows(): void {
        $recipient = $this->makeUser("recipient@example.com", "Recipient");
        $notifier  = $this->makeUser("notifier@example.com", "Notifier");

        $this->userManager->method("get")->willReturnCallback(
            fn($id) => match($id) {
                "recipient" => $recipient,
                "notifier"  => $notifier,
                default     => null,
            }
        );

        $this->urlGenerator->method("linkToRouteAbsolute")->willReturn("https://example.com/file");

        $template = $this->createStub(IEMailTemplate::class);
        $this->mailer->method("createEMailTemplate")->willReturn($template);

        $message = $this->createStub(IMessage::class);
        $this->mailer->method("createMessage")->willReturn($message);
        $this->mailer->method("send")->willThrowException(new \Exception("SMTP error"));

        $result = $this->emailManager->notifyMentionEmail("notifier", "recipient", "1", "file.docx", "#anchor", "comment text");

        $this->assertFalse($result);
    }

    /**
     * Returns false immediately when the target user does not exist in the user manager.
     */
    public function testNotifyEditorsCheckEmailReturnsFalseWhenUserNotFound(): void {
        $this->userManager->method("get")->willReturn(null);

        $result = $this->emailManager->notifyEditorsCheckEmail("admin");

        $this->assertFalse($result);
    }

    /**
     * Returns false when the user has no email address, as the downtime alert cannot be delivered.
     */
    public function testNotifyEditorsCheckEmailReturnsFalseWhenUserHasNoEmail(): void {
        $user = $this->makeUser("");
        $this->userManager->method("get")->willReturn($user);

        $result = $this->emailManager->notifyEditorsCheckEmail("admin");

        $this->assertFalse($result);
    }

    /**
     * Returns true when the user exists, has an email address, and the mailer delivers without errors.
     */
    public function testNotifyEditorsCheckEmailReturnsTrueOnSuccess(): void {
        $user = $this->makeUser("admin@example.com", "Admin");
        $this->userManager->method("get")->willReturn($user);

        $this->urlGenerator->method("getAbsoluteURL")->willReturn("https://example.com/settings");
        $this->stubSuccessfulSend();

        $result = $this->emailManager->notifyEditorsCheckEmail("admin");

        $this->assertTrue($result);
    }

    /**
     * Returns false when the mailer reports delivery failures via a non-empty errors array.
     */
    public function testNotifyEditorsCheckEmailReturnsFalseWhenMailerReturnsErrors(): void {
        $user = $this->makeUser("admin@example.com", "Admin");
        $this->userManager->method("get")->willReturn($user);

        $this->urlGenerator->method("getAbsoluteURL")->willReturn("https://example.com/settings");

        $template = $this->createStub(IEMailTemplate::class);
        $this->mailer->method("createEMailTemplate")->willReturn($template);

        $message = $this->createStub(IMessage::class);
        $this->mailer->method("createMessage")->willReturn($message);
        $this->mailer->method("send")->willReturn(["admin@example.com"]);

        $result = $this->emailManager->notifyEditorsCheckEmail("admin");

        $this->assertFalse($result);
    }
}
