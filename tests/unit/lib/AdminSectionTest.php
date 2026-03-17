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

use OCA\Onlyoffice\AdminSection;
use OCP\IURLGenerator;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

#[CoversClass(AdminSection::class)]
#[AllowMockObjectsWithoutExpectations]
class AdminSectionTest extends TestCase {

    private IURLGenerator&MockObject $url;
    private AdminSection $adminSection;
    
    public function setUp(): void
    {
        parent::setUp();
        
        $this->url = $this->createMock(IURLGenerator::class);
        $this->adminSection = new AdminSection($this->url);
    }

    /**
     * Verifies that getIcon() requests the correct app icon path from the URL generator
     * and returns a non-empty string.
     */
    public function testGetIcon(): void {
        $this->url->expects($this->once())
            ->method("imagePath")
            ->with("onlyoffice", "app-dark.svg")
            ->willReturn("apps/onlyoffice/img/app-dark.svg");
        $this->assertNotEmpty($this->adminSection->getIcon());
    }

    /**
     * Verifies that getID() returns the onlyoffice section identifier.
     */
    public function testGetID(): void {
        $this->assertSame("onlyoffice", $this->adminSection->getID());
    }

    /**
     * Verifies that getName() returns the human-readable section name.
     */
    public function testGetName(): void {
        $this->assertSame("ONLYOFFICE", $this->adminSection->getName());
    }

    /**
     * Verifies that getPriority() returns a positive integer.
     */
    public function testGetPriority(): void {
        $this->assertGreaterThan(0, $this->adminSection->getPriority());
    }
}