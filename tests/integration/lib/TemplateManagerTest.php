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

namespace OCA\Onlyoffice\Tests\Integration;

use OCA\Onlyoffice\TemplateManager;
use OCP\Files\File;
use OCP\Files\Folder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Test\TestCase;

#[CoversClass(TemplateManager::class)]
#[Group('DB')]
class TemplateManagerTest extends TestCase {

    /** Files added to the template directory during tests, cleaned up in tearDown */
    private array $createdTemplateIds = [];

    protected function tearDown(): void {
        foreach ($this->createdTemplateIds as $id) {
            $file = TemplateManager::getTemplate($id);
            if ($file !== null) {
                $file->delete();
            }
        }
        $this->createdTemplateIds = [];
        parent::tearDown();
    }

    private function addTemplateFile(string $name, string $content = "test"): File {
        $dir = TemplateManager::getGlobalTemplateDir();
        $file = $dir->newFile($name, $content);
        $this->createdTemplateIds[] = $file->getId();
        return $file;
    }

    /**
     * Returns a Folder instance pointing to the global template directory.
     */
    public function testGetGlobalTemplateDirReturnsFolder(): void {
        $dir = TemplateManager::getGlobalTemplateDir();

        $this->assertInstanceOf(Folder::class, $dir);
    }

    /**
     * Creates the template directory if it does not already exist.
     */
    public function testGetGlobalTemplateDirCreatesDirectoryIfMissing(): void {
        $dir = TemplateManager::getGlobalTemplateDir();

        $this->assertTrue($dir->isReadable());
    }

    /**
     * Returns an empty array when the template directory contains no files.
     */
    public function testGetGlobalTemplatesReturnsEmptyWhenNoTemplatesExist(): void {
        $dir = TemplateManager::getGlobalTemplateDir();
        foreach ($dir->getDirectoryListing() as $node) {
            $node->delete();
        }

        $templates = TemplateManager::getGlobalTemplates();

        $this->assertSame([], $templates);
    }

    /**
     * Returns all files in the template directory when no mime filter is applied.
     */
    public function testGetGlobalTemplatesReturnsAllFiles(): void {
        $this->addTemplateFile("test.docx");
        $this->addTemplateFile("test.xlsx");

        $templates = TemplateManager::getGlobalTemplates();

        $names = array_map(fn($f) => $f->getName(), $templates);
        $this->assertContains("test.docx", $names);
        $this->assertContains("test.xlsx", $names);
    }

    /**
     * Returns only files matching the given mime type when a filter is applied.
     */
    public function testGetGlobalTemplatesFiltersByMimeType(): void {
        $this->addTemplateFile("word.docx");
        $this->addTemplateFile("sheet.xlsx");

        $templates = TemplateManager::getGlobalTemplates(
            "application/vnd.openxmlformats-officedocument.wordprocessingml.document"
        );

        $names = array_map(fn($f) => $f->getName(), $templates);
        $this->assertContains("word.docx", $names);
        $this->assertNotContains("sheet.xlsx", $names);
    }

    /**
     * Returns null when the template ID is zero.
     */
    public function testGetTemplateReturnsNullForZeroId(): void {
        $this->assertNull(TemplateManager::getTemplate(0));
    }

    /**
     * Returns null when no template with the given ID exists.
     */
    public function testGetTemplateReturnsNullForNonExistentId(): void {
        $this->assertNull(TemplateManager::getTemplate(PHP_INT_MAX));
    }

    /**
     * Returns the File when a template with the given ID exists.
     */
    public function testGetTemplateReturnsFileWhenExists(): void {
        $created = $this->addTemplateFile("find-me.docx");

        $found = TemplateManager::getTemplate($created->getId());

        $this->assertInstanceOf(File::class, $found);
        $this->assertSame("find-me.docx", $found->getName());
    }

    /**
     * Returns false for a file ID that is not in the template directory.
     */
    public function testIsTemplateReturnsFalseForNonExistentId(): void {
        $this->assertFalse(TemplateManager::isTemplate(PHP_INT_MAX));
    }

    /**
     * Returns true when the given file ID belongs to a template.
     */
    public function testIsTemplateReturnsTrueForKnownTemplate(): void {
        $file = $this->addTemplateFile("check-me.docx");

        $this->assertTrue(TemplateManager::isTemplate($file->getId()));
    }

    /**
     * Returns the binary content of the bundled default docx template.
     */
    public function testGetEmptyTemplateReturnsContentForDocx(): void {
        $content = TemplateManager::getEmptyTemplate("new.docx");

        $this->assertNotFalse($content);
        $this->assertNotEmpty($content);
    }

    /**
     * Returns the binary content of the bundled default xlsx template.
     */
    public function testGetEmptyTemplateReturnsContentForXlsx(): void {
        $content = TemplateManager::getEmptyTemplate("new.xlsx");

        $this->assertNotFalse($content);
        $this->assertNotEmpty($content);
    }

    /**
     * Returns the binary content of the bundled default pptx template.
     */
    public function testGetEmptyTemplateReturnsContentForPptx(): void {
        $content = TemplateManager::getEmptyTemplate("new.pptx");

        $this->assertNotFalse($content);
        $this->assertNotEmpty($content);
    }

    /**
     * Returns false when the requested extension has no bundled template.
     */
    public function testGetEmptyTemplateReturnsFalseForUnknownExtension(): void {
        $this->assertFalse(TemplateManager::getEmptyTemplate("new.unknown"));
    }
}
