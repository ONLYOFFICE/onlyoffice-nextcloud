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

use OCA\Onlyoffice\FileCreator;
use OCP\Files\File;
use OCP\Files\NotPermittedException;
use OCP\IL10N;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use Psr\Log\LoggerInterface;
use Test\TestCase;

#[CoversClass(FileCreator::class)]
#[AllowMockObjectsWithoutExpectations]
class FileCreatorTest extends TestCase {

    private IL10N&Stub $trans;

    public function setUp(): void {
        parent::setUp();

        $this->trans = $this->createStub(IL10N::class);
        $this->trans->method("t")->willReturnArgument(0);
    }

    private function make(string $format): FileCreator {
        return new FileCreator(
            "onlyoffice",
            $this->trans,
            $this->createStub(LoggerInterface::class),
            $format,
        );
    }

    /**
     * Combines the app name and format with an underscore to produce a unique creator identifier per format.
     */
    public function testGetIdCombinesAppNameAndFormat(): void {
        $this->assertSame("onlyoffice_docx", $this->make("docx")->getId());
        $this->assertSame("onlyoffice_xlsx", $this->make("xlsx")->getId());
    }

    /**
     * Maps xlsx to the spreadsheet creation label.
     */
    public function testGetNameReturnsSpreadsheetForXlsx(): void {
        $this->assertSame("New spreadsheet", $this->make("xlsx")->getName());
    }

    /**
     * Maps pptx to the presentation creation label.
     */
    public function testGetNameReturnsPresentationForPptx(): void {
        $this->assertSame("New presentation", $this->make("pptx")->getName());
    }

    /**
     * Falls back to the generic document creation label for any format other than xlsx or pptx.
     */
    public function testGetNameReturnsDocumentForDocxAndUnknownFormats(): void {
        $this->assertSame("New document", $this->make("docx")->getName());
        $this->assertSame("New document", $this->make("pdf")->getName());
    }

    /**
     * Maps xlsx to the spreadsheet mime type.
     */
    public function testGetMimetypeReturnsSpreadsheetMimeForXlsx(): void {
        $this->assertSame(
            "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
            $this->make("xlsx")->getMimetype()
        );
    }

    /**
     * Maps pptx to the presentation mime type.
     */
    public function testGetMimetypeReturnsPresentationMimeForPptx(): void {
        $this->assertSame(
            "application/vnd.openxmlformats-officedocument.presentationml.presentation",
            $this->make("pptx")->getMimetype()
        );
    }

    /**
     * Falls back to the word processing document mime type for any format other than xlsx or pptx.
     */
    public function testGetMimetypeReturnsDocumentMimeForDocxAndUnknownFormats(): void {
        $this->assertSame(
            "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
            $this->make("docx")->getMimetype()
        );
        $this->assertSame(
            "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
            $this->make("pdf")->getMimetype()
        );
    }

    /**
     * Returns the format string passed at construction so the file system receives the correct extension.
     */
    public function testGetExtensionReturnsFormat(): void {
        $this->assertSame("docx", $this->make("docx")->getExtension());
        $this->assertSame("xlsx", $this->make("xlsx")->getExtension());
        $this->assertSame("pptx", $this->make("pptx")->getExtension());
    }

    /**
     * create() writes the bundled template content into the file when a known extension is given.
     */
    public function testCreateWritesTemplateContentToFile(): void {
        $file = $this->createMock(File::class);
        $file->method("getName")->willReturn("new.docx");
        $file->method("getId")->willReturn(1);
        $file->expects($this->once())->method("putContent")->with($this->isString());

        $this->make("docx")->create($file);
    }

    /**
     * create() does not write anything when no bundled template exists for the extension.
     */
    public function testCreateDoesNotWriteWhenTemplateNotFound(): void {
        $file = $this->createMock(File::class);
        $file->method("getName")->willReturn("new.unknown");
        $file->method("getId")->willReturn(1);
        $file->expects($this->never())->method("putContent");

        $this->make("unknown")->create($file);
    }

    /**
     * create() catches NotPermittedException and does not propagate it.
     */
    public function testCreateCatchesNotPermittedException(): void {
        $file = $this->createMock(File::class);
        $file->method("getName")->willReturn("new.docx");
        $file->method("getId")->willReturn(1);
        $file->method("putContent")->willThrowException(new NotPermittedException());

        $this->make("docx")->create($file);
        $this->addToAssertionCount(1);
    }
}
