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
     * Maps each Office mime type to its corresponding template type string, returning empty for unknown types.
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
     * Maps each template type string back to its full Office mime type, returning empty for unknown types.
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
     * Recognises docx, xlsx, and pptx as template types (case-insensitively) and rejects all other extensions.
     */
    public function testIsTemplateTypeReturnsExpectedResult(string $name, bool $expected): void {
        $this->assertSame($expected, TemplateManager::isTemplateType($name));
    }

    /**
     * Maps a known language code to its BCP 47 locale folder and ends the path with the expected filename.
     */
    public function testGetEmptyTemplatePathContainsLocaleFolderForKnownLanguage(): void {
        $path = TemplateManager::getEmptyTemplatePath("en", ".docx");
        $this->assertStringContainsString("en-US", $path);
        $this->assertStringEndsWith("new.docx", $path);
    }

    /**
     * Falls back to the "default" locale folder for an unrecognised language code without throwing.
     */
    public function testGetEmptyTemplatePathFallsBackToDefaultForUnknownLanguage(): void {
        $path = TemplateManager::getEmptyTemplatePath("xx", ".xlsx");
        $this->assertStringContainsString("default", $path);
        $this->assertStringEndsWith("new.xlsx", $path);
    }

    /**
     * Resolves underscore-separated locale variants (e.g. de_DE, pt_BR, zh_CN) to their hyphenated BCP 47 counterparts.
     */
    public function testGetEmptyTemplatePathMapsLocaleVariantsCorrectly(): void {
        $this->assertStringContainsString("de-DE", TemplateManager::getEmptyTemplatePath("de_DE", ".pptx"));
        $this->assertStringContainsString("pt-BR", TemplateManager::getEmptyTemplatePath("pt_BR", ".docx"));
        $this->assertStringContainsString("zh-CN", TemplateManager::getEmptyTemplatePath("zh_CN", ".docx"));
    }
}
