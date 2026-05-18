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
use OCA\Onlyoffice\SettingsData;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use Test\TestCase;

#[CoversClass(SettingsData::class)]
class SettingsDataTest extends TestCase {

    private AppConfig&Stub $appConfig;
    private SettingsData $settingsData;

    protected function setUp(): void {
        parent::setUp();

        $this->appConfig = $this->createStub(AppConfig::class);
        $this->settingsData = new SettingsData($this->appConfig);
    }

    /**
     * jsonSerialize returns all four expected keys sourced from AppConfig.
     */
    public function testJsonSerializeReturnsAllExpectedKeys(): void {
        $formats = ["docx" => ["mime" => "application/vnd.openxmlformats-officedocument.wordprocessingml.document"]];

        $this->appConfig->method("formatsSetting")->willReturn($formats);
        $this->appConfig->method("getSameTab")->willReturn(true);
        $this->appConfig->method("getEnableSharing")->willReturn(false);
        $this->appConfig->method("getDisableDownload")->willReturn(true);

        $result = $this->settingsData->jsonSerialize();

        $this->assertArrayHasKey("formats", $result);
        $this->assertArrayHasKey("sameTab", $result);
        $this->assertArrayHasKey("enableSharing", $result);
        $this->assertArrayHasKey("disableDownload", $result);
    }

    /**
     * jsonSerialize passes AppConfig values through unchanged.
     */
    public function testJsonSerializeReflectsAppConfigValues(): void {
        $formats = ["xlsx" => ["mime" => "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"]];

        $this->appConfig->method("formatsSetting")->willReturn($formats);
        $this->appConfig->method("getSameTab")->willReturn(false);
        $this->appConfig->method("getEnableSharing")->willReturn(true);
        $this->appConfig->method("getDisableDownload")->willReturn(false);

        $result = $this->settingsData->jsonSerialize();

        $this->assertSame($formats, $result["formats"]);
        $this->assertFalse($result["sameTab"]);
        $this->assertTrue($result["enableSharing"]);
        $this->assertFalse($result["disableDownload"]);
    }

    /**
     * The object is JSON-encodable and produces a valid JSON string.
     */
    public function testObjectIsJsonEncodable(): void {
        $this->appConfig->method("formatsSetting")->willReturn([]);
        $this->appConfig->method("getSameTab")->willReturn(false);
        $this->appConfig->method("getEnableSharing")->willReturn(false);
        $this->appConfig->method("getDisableDownload")->willReturn(false);

        $json = json_encode($this->settingsData);

        $this->assertIsString($json);
        $this->assertNotFalse($json);
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
    }
}
