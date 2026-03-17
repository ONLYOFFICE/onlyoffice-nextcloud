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
 * All the Product's GNU elements, including illustrations and icon sets, as well as technical
 * writing content are licensed under the terms of the Creative Commons Attribution-ShareAlike 4.0 International.
 * See the License terms at http://creativecommons.org/licenses/by-sa/4.0/legalcode
 *
 */

namespace OCA\Onlyoffice\Tests\PHP;

use OCA\Onlyoffice\TemplateManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Test\TestCase;

#[CoversClass(TemplateManager::class)]
class TemplateManagerTest extends TestCase {

    public static function mimeToTypeProvider(): array {
        return [
            "document"     => ["application/vnd.openxmlformats-officedocument.wordprocessingml.document", "document"],
            "spreadsheet"  => ["application/vnd.openxmlformats-officedocument.spreadsheetml.sheet", "spreadsheet"],
            "presentation" => ["application/vnd.openxmlformats-officedocument.presentationml.presentation", "presentation"],
            "unknown"      => ["application/pdf", ""],
        ];
    }

    #[DataProvider("mimeToTypeProvider")]
    /**
     * Verifies that each Office mime type maps to its corresponding template type
     * string, and that unrecognised mime types return an empty string.
     */
    public function testGetTypeTemplateReturnsExpectedType(string $mime, string $expected): void {
        $this->assertSame($expected, TemplateManager::getTypeTemplate($mime));
    }

    public static function typeToMimeProvider(): array {
        return [
            "document"     => ["document",     "application/vnd.openxmlformats-officedocument.wordprocessingml.document"],
            "spreadsheet"  => ["spreadsheet",  "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"],
            "presentation" => ["presentation", "application/vnd.openxmlformats-officedocument.presentationml.presentation"],
            "unknown"      => ["pdf",          ""],
        ];
    }

    #[DataProvider("typeToMimeProvider")]
    /**
     * Verifies that each template type string maps back to its full Office mime type,
     * and that unrecognised type strings return an empty string.
     */
    public function testGetMimeTemplateReturnsExpectedMime(string $type, string $expected): void {
        $this->assertSame($expected, TemplateManager::getMimeTemplate($type));
    }

    public static function templateTypeProvider(): array {
        return [
            "docx"             => ["file.docx", true],
            "xlsx"             => ["file.xlsx", true],
            "pptx"             => ["file.pptx", true],
            "uppercase DOCX"   => ["file.DOCX", true],
            "pdf"              => ["file.pdf",  false],
            "no extension"     => ["file",      false],
        ];
    }

    #[DataProvider("templateTypeProvider")]
    /**
     * Verifies that docx, xlsx, and pptx files are recognised as template types
     * (case-insensitively), while all other extensions and bare filenames are not.
     */
    public function testIsTemplateTypeReturnsExpectedResult(string $name, bool $expected): void {
        $this->assertSame($expected, TemplateManager::isTemplateType($name));
    }

    /**
     * Verifies that a known language code is mapped to its BCP 47 locale folder
     * and that the path ends with the expected filename.
     */
    public function testGetEmptyTemplatePathContainsLocaleFolderForKnownLanguage(): void {
        $path = TemplateManager::getEmptyTemplatePath("en", ".docx");
        $this->assertStringContainsString("en-US", $path);
        $this->assertStringEndsWith("new.docx", $path);
    }

    /**
     * Verifies that an unrecognised language code falls back to the "default"
     * locale folder rather than throwing or producing a broken path.
     */
    public function testGetEmptyTemplatePathFallsBackToDefaultForUnknownLanguage(): void {
        $path = TemplateManager::getEmptyTemplatePath("xx", ".xlsx");
        $this->assertStringContainsString("default", $path);
        $this->assertStringEndsWith("new.xlsx", $path);
    }

    /**
     * Verifies that underscore-separated locale variants (e.g. de_DE, pt_BR, zh_CN)
     * are correctly resolved to their hyphenated BCP 47 counterparts.
     */
    public function testGetEmptyTemplatePathMapsLocaleVariantsCorrectly(): void {
        $this->assertStringContainsString("de-DE", TemplateManager::getEmptyTemplatePath("de_DE", ".pptx"));
        $this->assertStringContainsString("pt-BR", TemplateManager::getEmptyTemplatePath("pt_BR", ".docx"));
        $this->assertStringContainsString("zh-CN", TemplateManager::getEmptyTemplatePath("zh_CN", ".docx"));
    }
}
