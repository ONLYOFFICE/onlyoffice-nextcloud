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
