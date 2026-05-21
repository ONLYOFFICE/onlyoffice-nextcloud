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

use Exception;
use OCA\Onlyoffice\MailMergeAttachment;
use OCA\Onlyoffice\MailMergeMessage;
use OCA\Onlyoffice\MailMergeService;
use OCP\Mail\Provider\IManager;
use OCP\Mail\Provider\IMessage as MailProviderIMessage;
use OCP\Mail\Provider\IMessageSend;
use OCP\Mail\Provider\IService;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

interface IServiceWithSend extends IService, IMessageSend {}

#[CoversClass(MailMergeService::class)]
#[AllowMockObjectsWithoutExpectations]
class MailMergeServiceTest extends TestCase {

    private IManager&MockObject $mailManager;
    private MailMergeService $mailMergeService;

    public function setUp(): void {
        parent::setUp();
        $this->mailManager = $this->createMock(IManager::class);
        $this->mailMergeService = new MailMergeService($this->mailManager);
    }

    private function makeMessage(): MailMergeMessage {
        return (new MailMergeMessage())
            ->setFrom('sender@example.com')
            ->setTo('recipient@example.com')
            ->setSubject('Hello')
            ->setBodyPlain('Plain text')
            ->setBodyHtml('<p>HTML</p>');
    }

    private function stubProviderMessage(): MailProviderIMessage&MockObject {
        $message = $this->createMock(MailProviderIMessage::class);
        $message->method('setFrom')->willReturnSelf();
        $message->method('setTo')->willReturnSelf();
        $message->method('setSubject')->willReturnSelf();
        $message->method('setBodyHtml')->willReturnSelf();
        $message->method('setBodyPlain')->willReturnSelf();
        return $message;
    }

    /**
     * Throws when the mail manager cannot find a service for the given user and address.
     */
    public function testSendThrowsWhenServiceNotFound(): void {
        $this->mailManager->method('findServiceByAddress')->willReturn(null);

        $this->expectException(Exception::class);

        $this->mailMergeService->send('user1', $this->makeMessage());
    }

    /**
     * Throws when the found service does not have the MessageSend capability.
     */
    public function testSendThrowsWhenServiceNotCapable(): void {
        $service = $this->createStub(IService::class);
        $service->method('capable')->willReturn(false);
        $this->mailManager->method('findServiceByAddress')->willReturn($service);

        $this->expectException(Exception::class);

        $this->mailMergeService->send('user1', $this->makeMessage());
    }

    /**
     * Does not call setAttachments when the message carries no attachment.
     */
    public function testSendHtmlEmailDoesNotSetAttachments(): void {
        $providerMessage = $this->stubProviderMessage();
        $providerMessage->expects($this->never())->method('setAttachments');

        $service = $this->createStub(IServiceWithSend::class);
        $service->method('capable')->willReturn(true);
        $service->method('initiateMessage')->willReturn($providerMessage);
        $this->mailManager->method('findServiceByAddress')->willReturn($service);

        $this->mailMergeService->send('user1', $this->makeMessage());
    }

    /**
     * Calls setAttachments exactly once when the message carries an attachment.
     */
    public function testSendWithAttachmentCallsSetAttachments(): void {
        $providerMessage = $this->stubProviderMessage();
        $providerMessage->expects($this->once())->method('setAttachments');

        $service = $this->createStub(IServiceWithSend::class);
        $service->method('capable')->willReturn(true);
        $service->method('initiateMessage')->willReturn($providerMessage);
        $this->mailManager->method('findServiceByAddress')->willReturn($service);

        $attachment = (new MailMergeAttachment())
            ->setName('report.pdf')
            ->setExtension('pdf')
            ->setContent('%PDF-content');

        $this->mailMergeService->send('user1', $this->makeMessage()->setAttachment($attachment));
    }

    /**
     * Calls sendMessage exactly once on the mail service.
     */
    public function testSendCallsSendMessage(): void {
        $providerMessage = $this->stubProviderMessage();

        $service = $this->createMock(IServiceWithSend::class);
        $service->method('capable')->willReturn(true);
        $service->method('initiateMessage')->willReturn($providerMessage);
        $service->expects($this->once())->method('sendMessage');
        $this->mailManager->method('findServiceByAddress')->willReturn($service);

        $this->mailMergeService->send('user1', $this->makeMessage());
    }

    /**
     * Propagates exceptions thrown by sendMessage without wrapping them.
     */
    public function testSendPropagatesExceptionFromSendMessage(): void {
        $providerMessage = $this->stubProviderMessage();

        $service = $this->createStub(IServiceWithSend::class);
        $service->method('capable')->willReturn(true);
        $service->method('initiateMessage')->willReturn($providerMessage);
        $service->method('sendMessage')->willThrowException(new Exception('SMTP error'));
        $this->mailManager->method('findServiceByAddress')->willReturn($service);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('SMTP error');

        $this->mailMergeService->send('user1', $this->makeMessage());
    }
}
