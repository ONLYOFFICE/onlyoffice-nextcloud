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
use PHPUnit\Framework\Attributes\CoversClass;
use Test\TestCase;

#[CoversClass(MailMergeAttachment::class)]
class MailMergeAttachmentTest extends TestCase {

    /**
     * Each setter returns the same instance so calls can be chained.
     */
    public function testSettersReturnSelf(): void {
        $attachment = new MailMergeAttachment();

        $this->assertSame($attachment, $attachment->setName('report.pdf'));
        $this->assertSame($attachment, $attachment->setExtension('pdf'));
        $this->assertSame($attachment, $attachment->setContent('%PDF-content'));
    }

    /**
     * All getters return an empty string before any value has been set.
     */
    public function testFieldsDefaultToEmptyString(): void {
        $attachment = new MailMergeAttachment();

        $this->assertSame('', $attachment->getName());
        $this->assertSame('', $attachment->getExtension());
        $this->assertSame('', $attachment->getContent());
    }

    /**
     * Each field stores and returns exactly the value that was set.
     */
    public function testFieldsRoundTrip(): void {
        $attachment = (new MailMergeAttachment())
            ->setName('report.pdf')
            ->setExtension('pdf')
            ->setContent('%PDF-content');

        $this->assertSame('report.pdf', $attachment->getName());
        $this->assertSame('pdf', $attachment->getExtension());
        $this->assertSame('%PDF-content', $attachment->getContent());
    }
}
