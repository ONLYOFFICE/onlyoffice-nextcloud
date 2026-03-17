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

use OCA\Onlyoffice\AppConfig;
use OCA\Onlyoffice\Crypt;
use OCA\Onlyoffice\DocumentService;
use OCP\IL10N;
use OCP\IURLGenerator;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

#[CoversClass(DocumentService::class)]
#[AllowMockObjectsWithoutExpectations]
class DocumentServiceTest extends TestCase {

    private IL10N&MockObject $trans;
    private DocumentService $documentService;

    public function setUp(): void {
        parent::setUp();

        $this->trans = $this->createMock(IL10N::class);
        $this->trans->method("t")->willReturnArgument(0);

        $this->documentService = new DocumentService(
            $this->trans,
            $this->createMock(AppConfig::class),
            $this->createMock(IURLGenerator::class),
            $this->createMock(Crypt::class),
            $this->createMock(LoggerInterface::class),
        );
    }

    /**
     * Verifies that a key of 20 characters or fewer is returned as-is
     * without hashing or truncation.
     */
    public function testGenerateRevisionIdReturnsShortKeyUnchanged(): void {
        $result = DocumentService::generateRevisionId("short_key");
        $this->assertSame("short_key", $result);
    }

    /**
     * Verifies that a key longer than 20 characters is replaced with its CRC32
     * hash before being used as a revision identifier.
     */
    public function testGenerateRevisionIdCrc32sKeysLongerThanTwentyChars(): void {
        $longKey = str_repeat("a", 21);
        $result = DocumentService::generateRevisionId($longKey);
        $this->assertSame((string) crc32($longKey), $result);
    }

    /**
     * Verifies that the resulting revision id never exceeds 20 characters,
     * regardless of input length.
     */
    public function testGenerateRevisionIdTruncatesResultToTwentyChars(): void {
        $key = str_repeat("b", 20);
        $result = DocumentService::generateRevisionId($key);
        $this->assertLessThanOrEqual(20, strlen($result));
    }

    public static function conversionErrorProvider(): array {
        return [
            "encrypt signature" => [-20, "Error encrypt signature"],
            "invalid token"     => [-8,  "Invalid token"],
            "document request"  => [-7,  "Error document request"],
            "result database"   => [-6,  "Error while accessing the conversion result database"],
            "incorrect password" => [-5, "Incorrect password"],
            "download error"    => [-4,  "Error while downloading the document file to be converted"],
            "conversion error"  => [-3,  "Conversion error"],
            "timeout"           => [-2,  "Timeout conversion error"],
            "unknown"           => [-1,  "Unknown error"],
        ];
    }

    #[DataProvider("conversionErrorProvider")]
    /**
     * Verifies that each known conversion service error code produces an exception
     * whose message contains the expected human-readable description.
     */
    public function testProcessConvServResponceErrorThrowsWithExpectedMessage(int $code, string $fragment): void {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches("/" . preg_quote($fragment, "/") . "/");

        $this->documentService->processConvServResponceError($code);
    }

    /**
     * Verifies that an unrecognised conversion error code is included verbatim
     * in the exception message so callers can diagnose unexpected responses.
     */
    public function testProcessConvServResponceErrorIncludesCodeForUnknownErrors(): void {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches("/ErrorCode = 99/");

        $this->documentService->processConvServResponceError(99);
    }

    public static function commandErrorProvider(): array {
        return [
            "invalid token"         => [6, "Invalid token"],
            "command not correct"   => [5, "Command not corr"],
            "internal server error" => [3, "Internal server error"],
        ];
    }

    #[DataProvider("commandErrorProvider")]
    /**
     * Verifies that each known command service error code produces an exception
     * whose message contains the expected human-readable description.
     */
    public function testProcessCommandServResponceErrorThrowsWithExpectedMessage(int $code, string $fragment): void {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches("/" . preg_quote($fragment, "/") . "/");

        $this->documentService->processCommandServResponceError($code);
    }

    /**
     * Verifies that error code 0 is treated as success and no exception is thrown,
     * unlike the conversion service which always throws.
     */
    public function testProcessCommandServResponceErrorDoesNotThrowOnSuccess(): void {
        $this->documentService->processCommandServResponceError(0);
        $this->addToAssertionCount(1);
    }

    /**
     * Verifies that an unrecognised command error code is included verbatim
     * in the exception message so callers can diagnose unexpected responses.
     */
    public function testProcessCommandServResponceErrorIncludesCodeForUnknownErrors(): void {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches("/ErrorCode = 42/");

        $this->documentService->processCommandServResponceError(42);
    }
}
