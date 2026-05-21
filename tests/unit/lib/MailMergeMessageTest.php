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

use OCA\Onlyoffice\MailMergeAttachment;
use OCA\Onlyoffice\MailMergeMessage;
use PHPUnit\Framework\Attributes\CoversClass;
use Test\TestCase;

#[CoversClass(MailMergeMessage::class)]
class MailMergeMessageTest extends TestCase {

    /**
     * Each setter returns the same instance so calls can be chained.
     */
    public function testSettersReturnSelf(): void {
        $message = new MailMergeMessage();

        $this->assertSame($message, $message->setFrom('sender@example.com'));
        $this->assertSame($message, $message->setTo('recipient@example.com'));
        $this->assertSame($message, $message->setSubject('Subject'));
        $this->assertSame($message, $message->setBodyPlain('Plain text'));
        $this->assertSame($message, $message->setBodyHtml('<p>HTML</p>'));
        $this->assertSame($message, $message->setAttachment($this->createStub(MailMergeAttachment::class)));
    }

    /**
     * All string getters return an empty string before any value has been set.
     */
    public function testStringFieldsDefaultToEmptyString(): void {
        $message = new MailMergeMessage();

        $this->assertSame('', $message->getFrom());
        $this->assertSame('', $message->getTo());
        $this->assertSame('', $message->getSubject());
        $this->assertSame('', $message->getBodyPlain());
        $this->assertSame('', $message->getBodyHtml());
    }

    /**
     * getAttachment returns null and hasAttachment returns false before an attachment is set.
     */
    public function testAttachmentDefaultsToNull(): void {
        $message = new MailMergeMessage();

        $this->assertNull($message->getAttachment());
        $this->assertFalse($message->hasAttachment());
    }

    /**
     * Each string field stores and returns exactly the value that was set.
     */
    public function testStringFieldsRoundTrip(): void {
        $message = (new MailMergeMessage())
            ->setFrom('sender@example.com')
            ->setTo('recipient@example.com')
            ->setSubject('Hello')
            ->setBodyPlain('Plain text body')
            ->setBodyHtml('<p>HTML body</p>');

        $this->assertSame('sender@example.com', $message->getFrom());
        $this->assertSame('recipient@example.com', $message->getTo());
        $this->assertSame('Hello', $message->getSubject());
        $this->assertSame('Plain text body', $message->getBodyPlain());
        $this->assertSame('<p>HTML body</p>', $message->getBodyHtml());
    }

    /**
     * After setting an attachment, getAttachment returns the same instance
     * and hasAttachment returns true.
     */
    public function testAttachmentRoundTrip(): void {
        $attachment = $this->createStub(MailMergeAttachment::class);
        $message = (new MailMergeMessage())->setAttachment($attachment);

        $this->assertSame($attachment, $message->getAttachment());
        $this->assertTrue($message->hasAttachment());
    }
}
