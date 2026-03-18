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
