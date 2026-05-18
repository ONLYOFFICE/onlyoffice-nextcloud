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

use OCA\Onlyoffice\AppConfig;
use OCA\Onlyoffice\Crypt;
use OCA\Onlyoffice\DirectEditor;
use OCP\IL10N;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

#[CoversClass(DirectEditor::class)]
#[AllowMockObjectsWithoutExpectations]
class DirectEditorTest extends TestCase {

    private AppConfig&MockObject $appConfig;
    private DirectEditor $directEditor;

    public function setUp(): void {
        parent::setUp();

        $this->appConfig = $this->createMock(AppConfig::class);

        $this->directEditor = new DirectEditor(
            "onlyoffice",
            $this->createStub(IL10N::class),
            $this->createStub(LoggerInterface::class),
            $this->appConfig,
            $this->createStub(Crypt::class),
        );
    }

    /**
     * Returns an empty array when the user is not allowed to use the app, without inspecting format settings.
     */
    public function testGetMimetypesReturnsEmptyWhenUserNotAllowed(): void {
        $this->appConfig->method("isUserAllowedToUse")->willReturn(false);

        $this->assertSame([], $this->directEditor->getMimetypes());
    }

    /**
     * Returns only the mime types of formats marked as default (def=true), filtering out non-default formats.
     */
    public function testGetMimetypesReturnsOnlyDefaultFormats(): void {
        $this->appConfig->method("isUserAllowedToUse")->willReturn(true);
        $this->appConfig->method("formatsSetting")->willReturn([
            "docx" => ["mime" => ["application/vnd.openxmlformats-officedocument.wordprocessingml.document"], "def" => true],
            "xlsx" => ["mime" => ["application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"], "def" => true],
            "pdf"  => ["mime" => ["application/pdf"]],
        ]);

        $result = $this->directEditor->getMimetypes();

        $this->assertCount(2, $result);
        $this->assertContains("application/vnd.openxmlformats-officedocument.wordprocessingml.document", $result);
        $this->assertContains("application/vnd.openxmlformats-officedocument.spreadsheetml.sheet", $result);
        $this->assertNotContains("application/pdf", $result);
    }

    /**
     * Returns an empty array when no formats are marked as default.
     */
    public function testGetMimetypesReturnsEmptyWhenNoDefaultFormats(): void {
        $this->appConfig->method("isUserAllowedToUse")->willReturn(true);
        $this->appConfig->method("formatsSetting")->willReturn([
            "pdf" => ["mime" => ["application/pdf"]],
        ]);

        $this->assertSame([], $this->directEditor->getMimetypes());
    }

    /**
     * Returns an empty array when the user is not allowed to use the app, without inspecting format settings.
     */
    public function testGetMimetypesOptionalReturnsEmptyWhenUserNotAllowed(): void {
        $this->appConfig->method("isUserAllowedToUse")->willReturn(false);

        $this->assertSame([], $this->directEditor->getMimetypesOptional());
    }

    /**
     * Returns only the mime types of formats not marked as default, complementing getMimetypes().
     */
    public function testGetMimetypesOptionalReturnsNonDefaultFormats(): void {
        $this->appConfig->method("isUserAllowedToUse")->willReturn(true);
        $this->appConfig->method("formatsSetting")->willReturn([
            "docx" => ["mime" => ["application/vnd.openxmlformats-officedocument.wordprocessingml.document"], "def" => true],
            "pdf"  => ["mime" => ["application/pdf"]],
            "odt"  => ["mime" => ["application/vnd.oasis.opendocument.text"], "def" => false],
        ]);

        $result = $this->directEditor->getMimetypesOptional();

        $this->assertCount(2, $result);
        $this->assertContains("application/pdf", $result);
        $this->assertContains("application/vnd.oasis.opendocument.text", $result);
        $this->assertNotContains("application/vnd.openxmlformats-officedocument.wordprocessingml.document", $result);
    }

    /**
     * Partitions all formats between getMimetypes() and getMimetypesOptional() with no overlap.
     */
    public function testGetMimetypesAndOptionalAreDisjointAndComplete(): void {
        $this->appConfig->method("isUserAllowedToUse")->willReturn(true);
        $this->appConfig->method("formatsSetting")->willReturn([
            "docx" => ["mime" => ["application/vnd.openxmlformats-officedocument.wordprocessingml.document"], "def" => true],
            "xlsx" => ["mime" => ["application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"], "def" => true],
            "pdf"  => ["mime" => ["application/pdf"]],
            "odt"  => ["mime" => ["application/vnd.oasis.opendocument.text"], "def" => false],
        ]);

        $default  = $this->directEditor->getMimetypes();
        $optional = $this->directEditor->getMimetypesOptional();

        $this->assertEmpty(array_intersect($default, $optional));
        $this->assertCount(4, array_merge($default, $optional));
    }

    /**
     * Returns an empty array when the user is not allowed to use the app, presenting no file creation options.
     */
    public function testGetCreatorsReturnsEmptyWhenUserNotAllowed(): void {
        $this->appConfig->method("isUserAllowedToUse")->willReturn(false);

        $this->assertSame([], $this->directEditor->getCreators());
    }

    /**
     * Returns exactly three creators (docx, xlsx, pptx) when the user is allowed, one per supported format.
     */
    public function testGetCreatorsReturnsThreeCreatorsWhenAllowed(): void {
        $this->appConfig->method("isUserAllowedToUse")->willReturn(true);

        $creators = $this->directEditor->getCreators();

        $this->assertCount(3, $creators);
    }
}
