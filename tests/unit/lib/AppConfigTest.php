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
use OCP\IAppConfig;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IConfig;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

#[CoversClass(AppConfig::class)]
#[AllowMockObjectsWithoutExpectations]
class AppConfigTest extends TestCase {

    private IAppConfig&MockObject $appConfig;
    private IConfig&MockObject $config;
    private LoggerInterface&MockObject $logger;
    private AppConfig $subject;

    private string $appName = "onlyoffice";

    public function setUp(): void {
        parent::setUp();

        $this->appConfig = $this->createMock(IAppConfig::class);
        $this->config = $this->createMock(IConfig::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $cache = $this->createMock(ICache::class);
        $cacheFactory = $this->createMock(ICacheFactory::class);
        $cacheFactory->method("createLocal")->willReturn($cache);

        $this->subject = new AppConfig(
            $this->appName,
            $this->appConfig,
            $this->config,
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

    public static function documentServerUrlProvider(): array {
        return [
            "adds https scheme"             => ["example.com", "https://example.com/"],
            "keeps https scheme"            => ["https://example.com", "https://example.com/"],
            "adds trailing slash"           => ["https://example.com/", "https://example.com/"],
            "trims whitespace"              => ["  https://example.com  ", "https://example.com/"],
            "clears the value"              => ["", ""],
            "drops query and fragment"      => ["https://example.com/sub/?token=abc#part", "https://example.com/sub/"],
            "drops credentials"             => ["https://alice@example:s3cr3t@host/", "https://host/"],
            "lowercases scheme and host"    => ["HTTPS://Example.COM/Sub/", "https://example.com/Sub/"],
            "drops default port"            => ["https://example.com:443/", "https://example.com/"],
            "keeps other port"              => ["example.com:8080", "https://example.com:8080/"],
            "drops trailing dot on host"    => ["https://example.com./", "https://example.com/"],
            "removes inner whitespace"      => ["http://example.com\r\n/sub", "http://example.com/sub/"],
            "backslash separates the path"  => ["http://example.com\\@evil.com/", "http://example.com/@evil.com/"],
            "keeps foreign scheme"          => ["ftp://example.com/", "ftp://example.com/"],
            "keeps address without host"    => ["http:///sub", "http:///sub"],
            "keeps path only address"       => ["/onlyoffice", "/onlyoffice/"],
            "keeps ipv6 brackets"           => ["http://[::1]:8080/", "http://[::1]:8080/"],
            "keeps full ipv6 brackets"      => ["https://[2001:db8::1]/sub/", "https://[2001:db8::1]/sub/"],
            "drops default port on ipv6"    => ["https://[::1]:443/", "https://[::1]/"],
        ];
    }

    /**
     * Sanitizes the document server address before storing it.
     */
    #[DataProvider("documentServerUrlProvider")]
    public function testSetDocumentServerUrlStoresSanitizedAddress(string $address, string $stored): void {
        $this->appConfig->expects($this->once())
            ->method("setValueString")
            ->with($this->appName, "DocumentServerUrl", $stored);

        $this->subject->setDocumentServerUrl($address);
    }

    public static function documentServerInternalUrlProvider(): array {
        return [
            "keeps credentials"        => ["https://alice@example:s3cr3t@host/", "https://alice@example:s3cr3t@host/"],
            "drops query and fragment" => ["http://example.com/?token=abc#part", "http://example.com/"],
            "adds https scheme"        => ["example.com", "https://example.com/"],
            "keeps ipv6 brackets"      => ["http://[::1]:8080/", "http://[::1]:8080/"],
            "clears the value"         => ["", ""],
        ];
    }

    /**
     * Sanitizes the internal document server address before storing it, keeping any credentials.
     */
    #[DataProvider("documentServerInternalUrlProvider")]
    public function testSetDocumentServerInternalUrlStoresSanitizedAddress(string $address, string $stored): void {
        $this->appConfig->expects($this->once())
            ->method("setValueString")
            ->with($this->appName, "DocumentServerInternalUrl", $stored);

        $this->subject->setDocumentServerInternalUrl($address);
    }

    public static function storageUrlProvider(): array {
        return [
            "drops credentials"        => ["https://alice@example:s3cr3t@cloud.example.com/", "https://cloud.example.com/"],
            "drops query and fragment" => ["https://cloud.example.com/?x=1#y", "https://cloud.example.com/"],
            "adds https scheme"        => ["cloud.example.com", "https://cloud.example.com/"],
            "clears the value"         => ["", ""],
        ];
    }

    /**
     * Sanitizes the storage address before storing it.
     */
    #[DataProvider("storageUrlProvider")]
    public function testSetStorageUrlStoresSanitizedAddress(string $address, string $stored): void {
        $this->appConfig->expects($this->once())
            ->method("setValueString")
            ->with($this->appName, "StorageUrl", $stored);

        $this->subject->setStorageUrl($address);
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

    /**
     * Marks the stored secret key as sensitive so it is encrypted at rest and hidden from config listings.
     */
    public function testSetDocumentServerSecretStoresValueAsSensitive(): void {
        $this->appConfig->expects($this->once())
            ->method("setValueString")
            ->with($this->appName, "jwt_secret", "supersecret", false, true);

        $this->subject->setDocumentServerSecret("supersecret");
    }

    /**
     * Keeps the sensitive marking when the secret key is cleared.
     */
    public function testSetDocumentServerSecretStoresEmptyValueAsSensitive(): void {
        $this->appConfig->expects($this->once())
            ->method("setValueString")
            ->with($this->appName, "jwt_secret", "", false, true);

        $this->subject->setDocumentServerSecret("");
    }

    public static function allowLocalAddressProvider(): array {
        return [
            "allowed for every app" => [true, [], true],
            "allowed for this app"  => [false, ["allow_local_address" => true], true],
            "written as a string"   => [false, ["allow_local_address" => "true"], true],
            "not allowed"           => [false, [], false],
        ];
    }

    /**
     * Allows local addresses when either the setting for every app or the one for this app is enabled.
     */
    #[DataProvider("allowLocalAddressProvider")]
    public function testGetAllowLocalAddress(bool $everyApp, array $appSection, bool $expected): void {
        $this->config->method("getSystemValueBool")
            ->with("allow_local_remote_servers", false)
            ->willReturn($everyApp);
        $this->config->method("getSystemValue")
            ->with($this->appName)
            ->willReturn($appSection);

        $this->assertSame($expected, $this->subject->getAllowLocalAddress());
    }
}
