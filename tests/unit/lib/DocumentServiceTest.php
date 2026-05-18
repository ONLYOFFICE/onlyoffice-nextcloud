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
     * Returns a key of 20 characters or fewer as-is, without hashing or truncation.
     */
    public function testGenerateRevisionIdReturnsShortKeyUnchanged(): void {
        $result = DocumentService::generateRevisionId("short_key");
        $this->assertSame("short_key", $result);
    }

    /**
     * Replaces a key longer than 20 characters with its CRC32 hash before using it as a revision identifier.
     */
    public function testGenerateRevisionIdCrc32sKeysLongerThanTwentyChars(): void {
        $longKey = str_repeat("a", 21);
        $result = DocumentService::generateRevisionId($longKey);
        $this->assertSame((string) crc32($longKey), $result);
    }

    /**
     * Produces a revision id that never exceeds 20 characters, regardless of input length.
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
     * Throws an exception with a human-readable description for each known conversion service error code.
     */
    public function testProcessConvServResponceErrorThrowsWithExpectedMessage(int $code, string $fragment): void {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches("/" . preg_quote($fragment, "/") . "/");

        $this->documentService->processConvServResponceError($code);
    }

    /**
     * Includes the unrecognised conversion error code verbatim in the exception message for caller diagnostics.
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
     * Throws an exception with a human-readable description for each known command service error code.
     */
    public function testProcessCommandServResponceErrorThrowsWithExpectedMessage(int $code, string $fragment): void {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches("/" . preg_quote($fragment, "/") . "/");

        $this->documentService->processCommandServResponceError($code);
    }

    /**
     * Treats error code 0 as success and does not throw, unlike the conversion service which always throws.
     */
    public function testProcessCommandServResponceErrorDoesNotThrowOnSuccess(): void {
        $this->documentService->processCommandServResponceError(0);
        $this->addToAssertionCount(1);
    }

    /**
     * Includes the unrecognised command error code verbatim in the exception message for caller diagnostics.
     */
    public function testProcessCommandServResponceErrorIncludesCodeForUnknownErrors(): void {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches("/ErrorCode = 42/");

        $this->documentService->processCommandServResponceError(42);
    }
}
