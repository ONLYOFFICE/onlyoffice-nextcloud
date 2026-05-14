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

use OCA\Files_Sharing\External\Storage as SharingExternalStorage;
use OCA\Onlyoffice\AppConfig;
use OCA\Onlyoffice\Crypt;
use OCA\Onlyoffice\DocumentService;
use OCA\Onlyoffice\FileUtility;
use OCA\Onlyoffice\Preview;
use OCP\Files\FileInfo;
use OCP\Files\IRootFolder;
use OCP\Files\Storage\IStorage;
use OCP\IURLGenerator;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

#[CoversClass(Preview::class)]
#[AllowMockObjectsWithoutExpectations]
class PreviewTest extends TestCase {

    private AppConfig&MockObject $appConfig;
    private Preview $preview;

    public function setUp(): void {
        parent::setUp();

        $this->appConfig = $this->createMock(AppConfig::class);

        $this->preview = new Preview(
            "onlyoffice",
            $this->createMock(IRootFolder::class),
            $this->createMock(LoggerInterface::class),
            $this->appConfig,
            $this->createMock(IURLGenerator::class),
            $this->createMock(Crypt::class),
            $this->createMock(FileUtility::class),
            $this->createMock(DocumentService::class),
            null,
        );
    }

    /**
     * Produces a syntactically valid PCRE regex that matches at least one known supported mime type.
     */
    public function testGetMimeTypeRegexReturnsValidRegex(): void {
        $regex = Preview::getMimeTypeRegex();
        $this->assertSame(1, preg_match($regex, "application/pdf"));
    }

    /**
     * Matches every mime type declared in the capabilities list.
     */
    public function testGetMimeTypeRegexMatchesAllCapabilities(): void {
        $regex = Preview::getMimeTypeRegex();
        foreach (Preview::$capabilities as $mime) {
            $this->assertSame(1, preg_match($regex, $mime), "Expected regex to match: $mime");
        }
    }

    /**
     * Does not match mime types outside the capabilities list, acting as an allowlist.
     */
    public function testGetMimeTypeRegexDoesNotMatchUnknownMime(): void {
        $regex = Preview::getMimeTypeRegex();
        $this->assertSame(0, preg_match($regex, "image/png"));
    }

    private function makeFile(
        int $size,
        string $mime,
        bool $isExternalStorage = false
    ): FileInfo&MockObject {
        $storage = $this->createStub(IStorage::class);
        $storage->method("instanceOfStorage")
            ->willReturnCallback(fn($class) => $isExternalStorage && $class === SharingExternalStorage::class);

        $file = $this->createMock(FileInfo::class);
        $file->method("getSize")->willReturn($size);
        $file->method("getMimetype")->willReturn($mime);
        $file->method("getStorage")->willReturn($storage);

        return $file;
    }

    /**
     * Returns false when the preview feature is disabled in the app configuration, regardless of the file.
     */
    public function testIsAvailableReturnsFalseWhenPreviewIsDisabled(): void {
        $this->appConfig->method("getPreview")->willReturn(false);

        $file = $this->makeFile(1024, "application/pdf");
        $this->assertFalse($this->preview->isAvailable($file));
    }

    /**
     * Rejects empty files as there is nothing to generate a thumbnail from.
     */
    public function testIsAvailableReturnsFalseWhenFileSizeIsZero(): void {
        $this->appConfig->method("getPreview")->willReturn(true);
        $this->appConfig->method("getLimitThumbSize")->willReturn(10 * 1024 * 1024);

        $file = $this->makeFile(0, "application/pdf");
        $this->assertFalse($this->preview->isAvailable($file));
    }

    /**
     * Rejects files larger than the configured thumbnail size limit to avoid excessive overhead.
     */
    public function testIsAvailableReturnsFalseWhenFileSizeExceedsLimit(): void {
        $this->appConfig->method("getPreview")->willReturn(true);
        $this->appConfig->method("getLimitThumbSize")->willReturn(1024);

        $file = $this->makeFile(2048, "application/pdf");
        $this->assertFalse($this->preview->isAvailable($file));
    }

    /**
     * Rejects files with a mime type not in the capabilities list, as they cannot be converted to a thumbnail.
     */
    public function testIsAvailableReturnsFalseWhenMimeTypeNotSupported(): void {
        $this->appConfig->method("getPreview")->willReturn(true);
        $this->appConfig->method("getLimitThumbSize")->willReturn(10 * 1024 * 1024);

        $file = $this->makeFile(1024, "image/png");
        $this->assertFalse($this->preview->isAvailable($file));
    }

    /**
     * Rejects files on external sharing storage, as they cannot be reached through the internal storage path.
     */
    public function testIsAvailableReturnsFalseForExternalSharingStorage(): void {
        $this->appConfig->method("getPreview")->willReturn(true);
        $this->appConfig->method("getLimitThumbSize")->willReturn(10 * 1024 * 1024);

        $file = $this->makeFile(1024, "application/pdf", true);
        $this->assertFalse($this->preview->isAvailable($file));
    }

    /**
     * Accepts a non-empty, supported, local file within the size limit when preview generation is enabled.
     */
    public function testIsAvailableReturnsTrueForSupportedLocalFile(): void {
        $this->appConfig->method("getPreview")->willReturn(true);
        $this->appConfig->method("getLimitThumbSize")->willReturn(10 * 1024 * 1024);

        $file = $this->makeFile(1024, "application/pdf");
        $this->assertTrue($this->preview->isAvailable($file));
    }
}
