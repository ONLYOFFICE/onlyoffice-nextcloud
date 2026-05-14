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
     * Requests the correct app icon path from the URL generator and returns a non-empty string.
     */
    public function testGetIcon(): void {
        $this->url->expects($this->once())
            ->method("imagePath")
            ->with("onlyoffice", "app-dark.svg")
            ->willReturn("apps/onlyoffice/img/app-dark.svg");
        $this->assertNotEmpty($this->adminSection->getIcon());
    }

    /**
     * Returns the onlyoffice section identifier.
     */
    public function testGetID(): void {
        $this->assertSame("onlyoffice", $this->adminSection->getID());
    }

    /**
     * Returns the human-readable section name.
     */
    public function testGetName(): void {
        $this->assertSame("ONLYOFFICE", $this->adminSection->getName());
    }

    /**
     * Returns a positive integer as the section priority.
     */
    public function testGetPriority(): void {
        $this->assertGreaterThan(0, $this->adminSection->getPriority());
    }
}
