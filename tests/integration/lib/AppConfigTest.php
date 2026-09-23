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

use OCA\Onlyoffice\AppConfig;
use OCP\Config\IUserConfig;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\ICacheFactory;
use OCP\Server;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\LoggerInterface;
use Test\TestCase;

#[CoversClass(AppConfig::class)]
#[Group('DB')]
class AppConfigTest extends TestCase {

    private AppConfig $appConfig;
    private IAppConfig $iAppConfig;
    private string $appName = "onlyoffice";

    protected function setUp(): void {
        parent::setUp();

        $this->iAppConfig = Server::get(IAppConfig::class);

        $this->appConfig = new AppConfig(
            $this->appName,
            $this->iAppConfig,
            Server::get(IConfig::class),
            Server::get(IUserConfig::class),
            $this->createStub(LoggerInterface::class),
            Server::get(ICacheFactory::class),
        );

        $this->cleanUp();
    }

    protected function tearDown(): void {
        $this->cleanUp();
        parent::tearDown();
    }

    private function cleanUp(): void {
        $this->iAppConfig->deleteKey($this->appName, "DocumentServerUrl");
        $this->iAppConfig->deleteKey($this->appName, "DocumentServerInternalUrl");
        $this->iAppConfig->deleteKey($this->appName, "StorageUrl");
        $this->iAppConfig->deleteKey($this->appName, "jwt_secret");
        $this->iAppConfig->deleteKey($this->appName, "demo");
    }

    /**
     * Persists and retrieves the document server URL with trailing slash normalisation.
     */
    public function testSetAndGetDocumentServerUrl(): void {
        $this->appConfig->setDocumentServerUrl("https://example.com");

        $this->assertSame("https://example.com/", $this->appConfig->getDocumentServerUrl());
    }

    /**
     * Strips surrounding whitespace from the document server URL.
     */
    public function testSetDocumentServerUrlTrimsWhitespace(): void {
        $this->appConfig->setDocumentServerUrl("  https://example.com  ");

        $this->assertSame("https://example.com/", $this->appConfig->getDocumentServerUrl());
    }

    /**
     * Prepends http:// when no scheme is provided.
     */
    public function testSetDocumentServerUrlPrependsHttpWhenNoScheme(): void {
        $this->appConfig->setDocumentServerUrl("example.com");

        $this->assertSame("http://example.com/", $this->appConfig->getDocumentServerUrl());
    }

    /**
     * Returns an empty string when no document server URL has been set.
     */
    public function testGetDocumentServerUrlReturnsEmptyByDefault(): void {
        $this->assertSame("", $this->appConfig->getDocumentServerUrl());
    }

    /**
     * Clears the document server URL when an empty string is set.
     */
    public function testSetDocumentServerUrlWithEmptyStringClearsValue(): void {
        $this->appConfig->setDocumentServerUrl("https://example.com");
        $this->appConfig->setDocumentServerUrl("");

        $this->assertSame("", $this->appConfig->getDocumentServerUrl());
    }

    /**
     * Persists and retrieves the internal document server URL.
     */
    public function testSetAndGetDocumentServerInternalUrl(): void {
        $this->appConfig->setDocumentServerInternalUrl("https://internal.example.com");

        $this->assertSame("https://internal.example.com/", $this->appConfig->getDocumentServerInternalUrl());
    }

    /**
     * Falls back to the public document server URL when no internal URL is set.
     */
    public function testGetDocumentServerInternalUrlFallsBackToPublicUrl(): void {
        $this->appConfig->setDocumentServerUrl("https://example.com");

        $this->assertSame("https://example.com/", $this->appConfig->getDocumentServerInternalUrl());
    }

    /**
     * Returns the internal URL unchanged when $origin is true, even if it would fall back otherwise.
     */
    public function testGetDocumentServerInternalUrlOriginReturnsRawValue(): void {
        $this->assertSame("", $this->appConfig->getDocumentServerInternalUrl(true));
    }

    /**
     * Persists and retrieves the storage URL with trailing slash normalisation.
     */
    public function testSetAndGetStorageUrl(): void {
        $this->appConfig->setStorageUrl("https://storage.example.com");

        $this->assertSame("https://storage.example.com/", $this->appConfig->getStorageUrl());
    }

    /**
     * Returns an empty string when no storage URL has been set.
     */
    public function testGetStorageUrlReturnsEmptyByDefault(): void {
        $this->assertSame("", $this->appConfig->getStorageUrl());
    }

    /**
     * Persists and retrieves the document server secret key.
     */
    public function testSetAndGetDocumentServerSecret(): void {
        $this->appConfig->setDocumentServerSecret("my-secret-key");

        $this->assertSame("my-secret-key", $this->appConfig->getDocumentServerSecret());
    }

    /**
     * Returns an empty string when no secret has been set.
     */
    public function testGetDocumentServerSecretReturnsEmptyByDefault(): void {
        $this->assertSame("", $this->appConfig->getDocumentServerSecret());
    }

    /**
     * Clears the secret when an empty string is set.
     */
    public function testSetDocumentServerSecretWithEmptyStringClearsValue(): void {
        $this->appConfig->setDocumentServerSecret("my-secret-key");
        $this->appConfig->setDocumentServerSecret("");

        $this->assertSame("", $this->appConfig->getDocumentServerSecret());
    }

    /**
     * Returns fresh demo data with available=true and enabled=false when no demo has been selected.
     */
    public function testGetDemoDataReturnsDefaultsWhenNotSet(): void {
        $data = $this->appConfig->getDemoData();

        $this->assertTrue($data["available"]);
        $this->assertFalse($data["enabled"]);
    }

    /**
     * selectDemo enables the demo and getDemoData reflects the change.
     */
    public function testSelectDemoEnablesDemo(): void {
        $result = $this->appConfig->selectDemo(true);

        $this->assertTrue($result);
        $this->assertTrue($this->appConfig->getDemoData()["enabled"]);
        $this->assertTrue($this->appConfig->useDemo());
    }

    /**
     * selectDemo can disable the demo after it has been enabled.
     */
    public function testSelectDemoDisablesDemo(): void {
        $this->appConfig->selectDemo(true);
        $this->appConfig->selectDemo(false);

        $this->assertFalse($this->appConfig->getDemoData()["enabled"]);
        $this->assertFalse($this->appConfig->useDemo());
    }

    /**
     * replaceDocumentServerUrlToInternal swaps the public host with the internal URL.
     */
    public function testReplaceDocumentServerUrlToInternal(): void {
        $this->appConfig->setDocumentServerUrl("https://public.example.com");
        $this->appConfig->setDocumentServerInternalUrl("https://internal.example.com");

        $result = $this->appConfig->replaceDocumentServerUrlToInternal("https://public.example.com/path");

        $this->assertSame("https://internal.example.com/path", $result);
    }

    /**
     * replaceDocumentServerUrlToInternal returns the URL unchanged when no internal URL is configured.
     */
    public function testReplaceDocumentServerUrlToInternalNoOpWhenNotConfigured(): void {
        $url = "https://public.example.com/path";

        $this->assertSame($url, $this->appConfig->replaceDocumentServerUrlToInternal($url));
    }
}
