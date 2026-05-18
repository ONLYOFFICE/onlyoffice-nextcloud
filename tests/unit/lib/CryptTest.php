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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use Test\TestCase;

#[CoversClass(Crypt::class)]
class CryptTest extends TestCase {

    private AppConfig&Stub $appConfig;
    private Crypt $crypt;
    
    public function setUp(): void
    {
        parent::setUp();
        
        $this->appConfig = $this->createStub(AppConfig::class);
        $this->appConfig->method("getSKey")->willReturn("secret");
        $this->crypt = new Crypt($this->appConfig);
    }

    /**
     * Encodes a payload with getHash() and decodes it back to the original via readHash(), with no error.
     */
    public function testGetHashAndReadHash(): void {
        $data = [
            "key" => "1234567890",
            "name" => "John Doe",
        ];

        $token = $this->crypt->getHash($data);
        $this->assertNotEmpty($token);

        $decoded = $this->crypt->readHash($token);
        $this->assertCount(2, $decoded);
        $this->assertEquals((object)$data, $decoded[0]);
        $this->assertNull($decoded[1]);
    }

    /**
     * Returns a null payload and a descriptive error message for an empty token, rather than throwing.
     */
    public function testReadHashWithEmptyToken(): void {
        $decoded = $this->crypt->readHash("");

        $this->assertNull($decoded[0]);
        $this->assertSame("token is empty", $decoded[1]);
    }

    /**
     * Returns a null payload and a non-null error message when given a malformed token that cannot be decoded.
     */
    public function testReadHashWithInvalidToken(): void {
        $decoded = $this->crypt->readHash("invalid_token");

        $this->assertNull($decoded[0]);
        $this->assertNotNull($decoded[1]);
    }
}
