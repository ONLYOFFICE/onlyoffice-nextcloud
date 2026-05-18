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

use DateTime;
use DateInterval;
use OCA\Onlyoffice\AppConfig;
use OCP\Config\IUserConfig;
use OCP\IAppConfig;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IConfig;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

#[CoversClass(AppConfig::class)]
#[AllowMockObjectsWithoutExpectations]
class AppConfigTest extends TestCase {

    private IAppConfig&MockObject $appConfig;
    private IConfig&MockObject $config;
    private IUserConfig&MockObject $userConfig;
    private LoggerInterface&MockObject $logger;
    private AppConfig $subject;

    private string $appName = "onlyoffice";

    public function setUp(): void {
        parent::setUp();

        $this->appConfig = $this->createMock(IAppConfig::class);
        $this->config = $this->createMock(IConfig::class);
        $this->userConfig = $this->createMock(IUserConfig::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $cache = $this->createMock(ICache::class);
        $cacheFactory = $this->createMock(ICacheFactory::class);
        $cacheFactory->method("createLocal")->willReturn($cache);

        $this->subject = new AppConfig(
            $this->appName,
            $this->appConfig,
            $this->config,
            $this->userConfig,
            $this->logger,
            $cacheFactory,
        );
    }

    /**
     * Reads directly from the root system config when $system=true, bypassing the app-specific section.
     */
    public function testGetSystemValueFromRootConfig(): void {
        $this->config->method("getSystemValue")
            ->with("somekey")
            ->willReturn("rootvalue");

        $result = $this->subject->getSystemValue("somekey", true);

        $this->assertSame("rootvalue", $result);
    }

    /**
     * Reads from the app-specific section of the system config when $system is not set and the key exists there.
     */
    public function testGetSystemValueFromAppSection(): void {
        $this->config->method("getSystemValue")
            ->with($this->appName)
            ->willReturn(["somekey" => "appvalue"]);

        $result = $this->subject->getSystemValue("somekey");

        $this->assertSame("appvalue", $result);
    }

    /**
     * Returns null when the requested key is absent from the app-specific config section.
     */
    public function testGetSystemValueReturnsNullWhenKeyMissing(): void {
        $this->config->method("getSystemValue")
            ->with($this->appName)
            ->willReturn(["otherkey" => "value"]);

        $result = $this->subject->getSystemValue("somekey");

        $this->assertNull($result);
    }

    /**
     * Returns available=true and enabled=false when no demo data has been stored yet.
     */
    public function testGetDemoDataReturnsDefaultsWhenNoneStored(): void {
        $this->appConfig->method("getValueString")->willReturn("");

        $data = $this->subject->getDemoData();

        $this->assertTrue($data["available"]);
        $this->assertFalse($data["enabled"]);
    }

    /**
     * Reports the demo as available and enabled when the trial start date is within the 30-day window.
     */
    public function testGetDemoDataReturnsAvailableWhenWithinTrialPeriod(): void {
        $start = new DateTime();
        $start->sub(new DateInterval("P5D"));

        $stored = json_encode(["start" => $start, "enabled" => true]);
        $this->appConfig->method("getValueString")->willReturn($stored);

        $data = $this->subject->getDemoData();

        $this->assertTrue($data["available"]);
        $this->assertTrue($data["enabled"]);
    }

    /**
     * Marks the demo as unavailable and forcibly disabled once the 30-day trial period has elapsed.
     */
    public function testGetDemoDataReturnsUnavailableWhenTrialExpired(): void {
        $start = new DateTime();
        $start->sub(new DateInterval("P31D"));

        $stored = json_encode(["start" => $start, "enabled" => true]);
        $this->appConfig->method("getValueString")->willReturn($stored);

        $data = $this->subject->getDemoData();

        $this->assertFalse($data["available"]);
        $this->assertFalse($data["enabled"]);
    }

    /**
     * Prefixes a URL that has no scheme with http:// before storing it.
     */
    public function testSetDocumentServerUrlAddsHttpSchemeWhenMissing(): void {
        $this->appConfig->expects($this->once())
            ->method("setValueString")
            ->with($this->appName, "DocumentServerUrl", "http://example.com/");

        $this->subject->setDocumentServerUrl("example.com");
    }

    /**
     * Preserves an existing https:// scheme unchanged when storing the URL.
     */
    public function testSetDocumentServerUrlKeepsHttpsScheme(): void {
        $this->appConfig->expects($this->once())
            ->method("setValueString")
            ->with($this->appName, "DocumentServerUrl", "https://example.com/");

        $this->subject->setDocumentServerUrl("https://example.com");
    }

    /**
     * Ensures a trailing slash is always present on the stored URL, normalising URLs that already include one.
     */
    public function testSetDocumentServerUrlAddsTrailingSlash(): void {
        $this->appConfig->expects($this->once())
            ->method("setValueString")
            ->with($this->appName, "DocumentServerUrl", "https://example.com/");

        $this->subject->setDocumentServerUrl("https://example.com/");
    }

    /**
     * Strips leading and trailing whitespace from the URL before normalisation and storage.
     */
    public function testSetDocumentServerUrlTrimsWhitespace(): void {
        $this->appConfig->expects($this->once())
            ->method("setValueString")
            ->with($this->appName, "DocumentServerUrl", "https://example.com/");

        $this->subject->setDocumentServerUrl("  https://example.com  ");
    }

    /**
     * Stores an empty string as-is, allowing the server URL to be cleared without triggering normalisation.
     */
    public function testSetDocumentServerUrlStoresEmptyStringAsIs(): void {
        $this->appConfig->expects($this->once())
            ->method("setValueString")
            ->with($this->appName, "DocumentServerUrl", "");

        $this->subject->setDocumentServerUrl("");
    }

    /**
     * Returns the hardcoded demo server address instead of the configured URL when demo mode is active.
     */
    public function testGetDocumentServerUrlReturnsDemoUrlWhenDemoEnabled(): void {
        $start = new DateTime();
        $start->sub(new DateInterval("P5D"));
        $stored = json_encode(["start" => $start, "enabled" => true]);

        $this->appConfig->method("getValueString")
            ->willReturnCallback(fn($app, $key, $default) =>
                $key === "demo" ? $stored : "");

        $url = $this->subject->getDocumentServerUrl();

        $this->assertSame("https://onlinedocs.docs.onlyoffice.com/", $url);
    }

    /**
     * Returns the administrator-configured server URL when demo mode is not active.
     */
    public function testGetDocumentServerUrlReturnsConfiguredUrlWhenDemoDisabled(): void {
        $this->appConfig->method("getValueString")
            ->willReturnCallback(fn($app, $key, $default) =>
                $key === "DocumentServerUrl" ? "https://myserver.com/" : "");

        $url = $this->subject->getDocumentServerUrl();

        $this->assertSame("https://myserver.com/", $url);
    }
}
