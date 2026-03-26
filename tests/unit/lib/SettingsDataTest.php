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
