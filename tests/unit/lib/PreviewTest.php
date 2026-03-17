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
     * Verifies that getMimeTypeRegex() produces a syntactically valid PCRE regex
     * that matches at least one known supported mime type.
     */
    public function testGetMimeTypeRegexReturnsValidRegex(): void {
        $regex = Preview::getMimeTypeRegex();
        $this->assertSame(1, preg_match($regex, "application/pdf"));
    }

    /**
     * Verifies that the regex matches every mime type declared in the capabilities
     * list.
     */
    public function testGetMimeTypeRegexMatchesAllCapabilities(): void {
        $regex = Preview::getMimeTypeRegex();
        foreach (Preview::$capabilities as $mime) {
            $this->assertSame(1, preg_match($regex, $mime), "Expected regex to match: $mime");
        }
    }

    /**
     * Verifies that the regex does not match mime types outside the capabilities
     * list, ensuring it acts as an allowlist rather than matching everything.
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
     * Verifies that isAvailable() returns false when the preview feature is
     * disabled in the app configuration, regardless of the file.
     */
    public function testIsAvailableReturnsFalseWhenPreviewIsDisabled(): void {
        $this->appConfig->method("getPreview")->willReturn(false);

        $file = $this->makeFile(1024, "application/pdf");
        $this->assertFalse($this->preview->isAvailable($file));
    }

    /**
     * Verifies that empty files are rejected, as there is nothing to generate
     * a thumbnail from.
     */
    public function testIsAvailableReturnsFalseWhenFileSizeIsZero(): void {
        $this->appConfig->method("getPreview")->willReturn(true);
        $this->appConfig->method("getLimitThumbSize")->willReturn(10 * 1024 * 1024);

        $file = $this->makeFile(0, "application/pdf");
        $this->assertFalse($this->preview->isAvailable($file));
    }

    /**
     * Verifies that files larger than the configured thumbnail size limit are
     * rejected to avoid excessive memory and processing overhead.
     */
    public function testIsAvailableReturnsFalseWhenFileSizeExceedsLimit(): void {
        $this->appConfig->method("getPreview")->willReturn(true);
        $this->appConfig->method("getLimitThumbSize")->willReturn(1024);

        $file = $this->makeFile(2048, "application/pdf");
        $this->assertFalse($this->preview->isAvailable($file));
    }

    /**
     * Verifies that files with a mime type not in the capabilities list are
     * rejected, as the document service cannot convert them to a thumbnail.
     */
    public function testIsAvailableReturnsFalseWhenMimeTypeNotSupported(): void {
        $this->appConfig->method("getPreview")->willReturn(true);
        $this->appConfig->method("getLimitThumbSize")->willReturn(10 * 1024 * 1024);

        $file = $this->makeFile(1024, "image/png");
        $this->assertFalse($this->preview->isAvailable($file));
    }

    /**
     * Verifies that files on external sharing storage are rejected, as the
     * document service cannot reach them through the internal storage path.
     */
    public function testIsAvailableReturnsFalseForExternalSharingStorage(): void {
        $this->appConfig->method("getPreview")->willReturn(true);
        $this->appConfig->method("getLimitThumbSize")->willReturn(10 * 1024 * 1024);

        $file = $this->makeFile(1024, "application/pdf", true);
        $this->assertFalse($this->preview->isAvailable($file));
    }

    /**
     * Verifies that a non-empty, supported, local file within the size limit
     * is accepted when preview generation is enabled.
     */
    public function testIsAvailableReturnsTrueForSupportedLocalFile(): void {
        $this->appConfig->method("getPreview")->willReturn(true);
        $this->appConfig->method("getLimitThumbSize")->willReturn(10 * 1024 * 1024);

        $file = $this->makeFile(1024, "application/pdf");
        $this->assertTrue($this->preview->isAvailable($file));
    }
}
