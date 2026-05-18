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

use OCA\Onlyoffice\Exceptions\DesktopRedirectException;
use OCA\Onlyoffice\Middleware\DesktopMiddleware;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\INavigationManager;
use OCP\IRequest;
use OCP\IURLGenerator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use Test\TestCase;

#[CoversClass(DesktopMiddleware::class)]
class DesktopMiddlewareTest extends TestCase {

    private IRequest&Stub $request;
    private INavigationManager&Stub $navigationManager;
    private IURLGenerator&Stub $urlGenerator;
    private Controller&Stub $controller;
    private DesktopMiddleware $middleware;

    public function setUp(): void {
        parent::setUp();

        $this->request = $this->createStub(IRequest::class);
        $this->navigationManager = $this->createStub(INavigationManager::class);
        $this->urlGenerator = $this->createStub(IURLGenerator::class);
        $this->controller = $this->createStub(Controller::class);

        $this->middleware = new DesktopMiddleware(
            $this->request,
            $this->navigationManager,
            $this->urlGenerator,
        );
    }

    private function configureDesktopRequest(string $entryHref, string $requestUri): void {
        $this->request->method('getHeader')->willReturn('AscDesktopEditor/6.0');
        $this->navigationManager->method('getDefaultEntryIdForUser')->willReturn('dashboard');
        $this->navigationManager->method('get')->willReturn(['id' => 'dashboard', 'href' => $entryHref]);
        $this->request->method('getRequestUri')->willReturn($requestUri);
    }

    /**
     * Passes through without redirecting when the User-Agent header is empty.
     */
    public function testPassesThroughWhenUserAgentIsEmpty(): void {
        $this->request->method('getHeader')->willReturn('');

        $this->middleware->beforeController($this->controller, 'index');
        $this->addToAssertionCount(1);
    }

    /**
     * Passes through without redirecting when the User-Agent belongs to a regular browser, not the desktop client.
     */
    public function testPassesThroughForNonDesktopUserAgent(): void {
        $this->request->method('getHeader')->willReturn('Mozilla/5.0 (X11; Linux x86_64)');

        $this->middleware->beforeController($this->controller, 'index');
        $this->addToAssertionCount(1);
    }

    /**
     * Passes through without redirecting when the user's default app is already files.
     */
    public function testPassesThroughWhenDefaultAppIsFiles(): void {
        $this->request->method('getHeader')->willReturn('AscDesktopEditor/6.0');
        $this->navigationManager->method('getDefaultEntryIdForUser')->willReturn('files');

        $this->middleware->beforeController($this->controller, 'index');
        $this->addToAssertionCount(1);
    }

    /**
     * Passes through without redirecting when the default app has no registered navigation entry.
     */
    public function testPassesThroughWhenNavigationEntryNotFound(): void {
        $this->request->method('getHeader')->willReturn('AscDesktopEditor/6.0');
        $this->navigationManager->method('getDefaultEntryIdForUser')->willReturn('dashboard');
        $this->navigationManager->method('get')->willReturn(null);

        $this->middleware->beforeController($this->controller, 'index');
        $this->addToAssertionCount(1);
    }

    /**
     * Passes through without redirecting when the request is on a subpath of the default app, not its root.
     */
    public function testPassesThroughWhenOnSubpathOfDefaultApp(): void {
        $this->configureDesktopRequest(
            'https://example.com/apps/dashboard',
            '/apps/dashboard/settings'
        );

        $this->middleware->beforeController($this->controller, 'index');
        $this->addToAssertionCount(1);
    }

    /**
     * Redirects when the desktop client lands on the root of the default app with no trailing slash on either side.
     */
    public function testRedirectsWhenDesktopClientLandsOnDefaultApp(): void {
        $this->expectException(DesktopRedirectException::class);

        $this->configureDesktopRequest(
            'https://example.com/apps/dashboard',
            '/apps/dashboard'
        );

        $this->middleware->beforeController($this->controller, 'index');
    }

    /**
     * Redirects correctly when the entry href has a trailing slash but the request URI does not, and vice versa.
     */
    public function testRedirectNormalisesTrailingSlashes(): void {
        $this->expectException(DesktopRedirectException::class);

        $this->configureDesktopRequest(
            'https://example.com/apps/dashboard/',
            '/apps/dashboard'
        );

        $this->middleware->beforeController($this->controller, 'index');
    }

    /**
     * Redirects when pretty URLs are in use (no index.php in either the entry href or the request URI).
     */
    public function testRedirectWithPrettyUrls(): void {
        $this->expectException(DesktopRedirectException::class);

        $this->configureDesktopRequest(
            'https://example.com/apps/dashboard',
            '/apps/dashboard'
        );

        $this->middleware->beforeController($this->controller, 'index');
    }

    /**
     * Redirects when index.php is present consistently in both the entry href and the request URI (no pretty URLs).
     */
    public function testRedirectWithIndexPhpInBothPaths(): void {
        $this->expectException(DesktopRedirectException::class);

        $this->configureDesktopRequest(
            'https://example.com/index.php/apps/dashboard',
            '/index.php/apps/dashboard'
        );

        $this->middleware->beforeController($this->controller, 'index');
    }

    /**
     * Redirects when Nextcloud is installed in a subdirectory and both paths share the same prefix.
     */
    public function testRedirectWithSubdirectoryInstall(): void {
        $this->expectException(DesktopRedirectException::class);

        $this->configureDesktopRequest(
            'https://example.com/nextcloud/apps/dashboard',
            '/nextcloud/apps/dashboard'
        );

        $this->middleware->beforeController($this->controller, 'index');
    }

    /**
     * Returns a RedirectResponse pointing to the files app when given a DesktopRedirectException.
     */
    public function testAfterExceptionReturnsRedirectToFilesForDesktopRedirectException(): void {
        $this->urlGenerator->method('linkToRouteAbsolute')->willReturn('https://example.com/apps/files/');

        $response = $this->middleware->afterException(
            $this->controller,
            'index',
            new DesktopRedirectException()
        );

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('https://example.com/apps/files/', $response->getRedirectURL());
    }

    /**
     * Rethrows any exception that is not a DesktopRedirectException, leaving it for other middleware to handle.
     */
    public function testAfterExceptionRethrowsUnrelatedExceptions(): void {
        $this->expectException(\RuntimeException::class);

        $this->middleware->afterException(
            $this->controller,
            'index',
            new \RuntimeException('unexpected')
        );
    }
}
