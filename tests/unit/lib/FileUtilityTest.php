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

use OCA\Files_Versions\Versions\IVersion;
use OCA\Onlyoffice\AppConfig;
use OCA\Onlyoffice\FileUtility;
use OCA\Onlyoffice\KeyManager;
use OCP\Constants;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\NotFoundException;
use OCP\IL10N;
use OCP\ISession;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager;
use OCP\Share\IShare;
use OCP\Share\IAttributes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use Psr\Log\LoggerInterface;
use Test\TestCase;

#[CoversClass(FileUtility::class)]
class FileUtilityTest extends TestCase {

    private IL10N&Stub $trans;
    private IManager&Stub $shareManager;
    private ISession&Stub $session;
    private KeyManager&Stub $keyManager;
    private AppConfig&Stub $appConfig;
    private FileUtility $fileUtility;

    protected function setUp(): void {
        parent::setUp();

        $this->trans = $this->createStub(IL10N::class);
        $this->trans->method("t")->willReturnArgument(0);

        $this->shareManager = $this->createStub(IManager::class);
        $this->session = $this->createStub(ISession::class);
        $this->keyManager = $this->createStub(KeyManager::class);
        $this->appConfig = $this->createStub(AppConfig::class);

        $this->fileUtility = new FileUtility(
            $this->trans,
            $this->createStub(LoggerInterface::class),
            $this->appConfig,
            $this->shareManager,
            $this->session,
            $this->keyManager,
        );
    }

    private function makeShare(
        string $id = "share1",
        int $permissions = Constants::PERMISSION_READ,
        ?string $password = null,
        ?object $node = null
    ): IShare&Stub {
        $share = $this->createStub(IShare::class);
        $share->method("getId")->willReturn($id);
        $share->method("getPermissions")->willReturn($permissions);
        $share->method("getPassword")->willReturn($password);
        $share->method("getNode")->willReturn($node ?? $this->createStub(File::class));
        return $share;
    }

    /**
     * Returns an error when the token is empty.
     */
    public function testGetShareReturnsErrorForEmptyToken(): void {
        [$share, $error] = $this->fileUtility->getShare("");

        $this->assertNull($share);
        $this->assertNotNull($error);
    }

    /**
     * Returns an error when no share is found for the token.
     */
    public function testGetShareReturnsErrorWhenShareNotFound(): void {
        $this->shareManager->method("getShareByToken")->willThrowException(new ShareNotFound());

        [$share, $error] = $this->fileUtility->getShare("invalid-token");

        $this->assertNull($share);
        $this->assertNotNull($error);
    }

    /**
     * Returns the share when a valid unprotected token is given.
     */
    public function testGetShareReturnsShareForValidToken(): void {
        $expected = $this->makeShare();
        $this->shareManager->method("getShareByToken")->willReturn($expected);
        $this->session->method("get")->willReturn(null);

        [$share, $error] = $this->fileUtility->getShare("valid-token");

        $this->assertSame($expected, $share);
        $this->assertNull($error);
    }

    /**
     * Returns an error when the share is password-protected and the user is not authenticated.
     */
    public function testGetShareReturnsErrorForPasswordProtectedShareWhenNotAuthenticated(): void {
        $this->shareManager->method("getShareByToken")->willReturn($this->makeShare("share1", Constants::PERMISSION_READ, "secret"));
        $this->session->method("get")->willReturn(null);

        [$share, $error] = $this->fileUtility->getShare("token");

        $this->assertNull($share);
        $this->assertNotNull($error);
    }

    /**
     * Returns the share when the user is authenticated via the array session format.
     */
    public function testGetShareReturnsShareWhenAuthenticatedViaArray(): void {
        $this->shareManager->method("getShareByToken")->willReturn($this->makeShare("share1", Constants::PERMISSION_READ, "secret"));
        $this->session->method("get")->willReturn(["share1"]);

        [$share, $error] = $this->fileUtility->getShare("token");

        $this->assertNotNull($share);
        $this->assertNull($error);
    }

    /**
     * Returns the share when the user is authenticated via the legacy string session format.
     */
    public function testGetShareReturnsShareWhenAuthenticatedViaString(): void {
        $this->shareManager->method("getShareByToken")->willReturn($this->makeShare("share1", Constants::PERMISSION_READ, "secret"));
        $this->session->method("get")->willReturn("share1");

        [$share, $error] = $this->fileUtility->getShare("token");

        $this->assertNotNull($share);
        $this->assertNull($error);
    }

    /**
     * Returns an error when the share has no read permission.
     */
    public function testGetNodeByTokenReturnsErrorForNoReadPermission(): void {
        $this->shareManager->method("getShareByToken")->willReturn($this->makeShare("s1", 0));
        $this->session->method("get")->willReturn(null);

        [$node, $error] = $this->fileUtility->getNodeByToken("token");

        $this->assertNull($node);
        $this->assertNotNull($error);
    }

    /**
     * Returns an error when the shared node is not found.
     */
    public function testGetNodeByTokenReturnsErrorWhenNodeNotFound(): void {
        $share = $this->makeShare("s1", Constants::PERMISSION_READ);
        $share->method("getNode")->willThrowException(new NotFoundException());
        $this->shareManager->method("getShareByToken")->willReturn($share);
        $this->session->method("get")->willReturn(null);

        [$node, $error] = $this->fileUtility->getNodeByToken("token");

        $this->assertNull($node);
        $this->assertNotNull($error);
    }

    /**
     * Returns the node and share when the token is valid and has read permission.
     */
    public function testGetNodeByTokenReturnsNodeForValidShare(): void {
        $file = $this->createStub(File::class);
        $share = $this->makeShare("s1", Constants::PERMISSION_READ, null, $file);
        $this->shareManager->method("getShareByToken")->willReturn($share);
        $this->session->method("get")->willReturn(null);

        [$node, $error, $returnedShare] = $this->fileUtility->getNodeByToken("token");

        $this->assertSame($file, $node);
        $this->assertNull($error);
        $this->assertSame($share, $returnedShare);
    }

    /**
     * Returns the file directly when the share node is a file.
     */
    public function testGetFileByTokenReturnsFileNodeDirectly(): void {
        $file = $this->createStub(File::class);
        $share = $this->makeShare("s1", Constants::PERMISSION_READ, null, $file);
        $this->shareManager->method("getShareByToken")->willReturn($share);
        $this->session->method("get")->willReturn(null);

        [$result, $error] = $this->fileUtility->getFileByToken(null, "token");

        $this->assertSame($file, $result);
        $this->assertNull($error);
    }

    /**
     * Returns the file from a folder by file ID when the share node is a folder.
     */
    public function testGetFileByTokenReturnsFolderChildById(): void {
        $file = $this->createStub(File::class);
        $folder = $this->createStub(Folder::class);
        $folder->method("getById")->willReturn([$file]);

        $share = $this->makeShare("s1", Constants::PERMISSION_READ, null, $folder);
        $this->shareManager->method("getShareByToken")->willReturn($share);
        $this->session->method("get")->willReturn(null);

        [$result, $error] = $this->fileUtility->getFileByToken(42, "token");

        $this->assertSame($file, $result);
        $this->assertNull($error);
    }

    /**
     * Returns the file from a folder by path when no file ID is given.
     */
    public function testGetFileByTokenReturnsFolderChildByPath(): void {
        $file = $this->createStub(File::class);
        $folder = $this->createStub(Folder::class);
        $folder->method("get")->willReturn($file);

        $share = $this->makeShare("s1", Constants::PERMISSION_READ, null, $folder);
        $this->shareManager->method("getShareByToken")->willReturn($share);
        $this->session->method("get")->willReturn(null);

        [$result, $error] = $this->fileUtility->getFileByToken(null, "token", "subdir/file.docx");

        $this->assertSame($file, $result);
        $this->assertNull($error);
    }

    /**
     * Generates a new key, stores it and returns it when none exists.
     */
    public function testGetKeyGeneratesAndStoresKeyWhenNoneExists(): void {
        $file = $this->createStub(File::class);
        $file->method("getId")->willReturn(1);

        $keyManager = $this->createMock(KeyManager::class);
        $keyManager->method("get")->willReturn("");
        $keyManager->expects($this->once())->method("set");

        $this->appConfig->method("getSystemValue")->willReturn("instance-id");

        $fileUtility = new FileUtility(
            $this->trans,
            $this->createStub(LoggerInterface::class),
            $this->appConfig,
            $this->shareManager,
            $this->session,
            $keyManager,
        );

        $key = $fileUtility->getKey($file);

        $this->assertStringStartsWith("instance-id_", $key);
    }

    /**
     * Returns the existing stored key without generating a new one.
     */
    public function testGetKeyReturnsExistingKeyWithoutGenerating(): void {
        $file = $this->createStub(File::class);
        $file->method("getId")->willReturn(1);

        $keyManager = $this->createMock(KeyManager::class);
        $keyManager->method("get")->willReturn("existing-key");
        $keyManager->expects($this->never())->method("set");

        $fileUtility = new FileUtility(
            $this->trans,
            $this->createStub(LoggerInterface::class),
            $this->appConfig,
            $this->shareManager,
            $this->session,
            $keyManager,
        );

        $this->assertSame("existing-key", $fileUtility->getKey($file));
    }

    /**
     * Combines instance ID, file etag and revision ID into the version key.
     */
    public function testGetVersionKeyCombinesInstanceIdEtagAndRevisionId(): void {
        $this->appConfig->method("getSystemValue")->willReturn("inst123");

        $sourceFile = $this->createStub(File::class);
        $sourceFile->method("getEtag")->willReturn("etag-abc");

        $version = $this->createStub(IVersion::class);
        $version->method("getSourceFile")->willReturn($sourceFile);
        $version->method("getRevisionId")->willReturn("1234567890");

        $key = $this->fileUtility->getVersionKey($version);

        $this->assertSame("inst123_etag-abc_1234567890", $key);
    }

    /**
     * Returns true when no download attribute is set on the share.
     */
    public function testCanShareDownloadReturnsTrueWhenNoAttributeSet(): void {
        $share = $this->createStub(IShare::class);
        $share->method("getAttributes")->willReturn(null);

        $this->assertTrue(FileUtility::canShareDownload($share));
    }

    /**
     * Returns false when the download attribute is explicitly set to false.
     */
    public function testCanShareDownloadReturnsFalseWhenAttributeIsFalse(): void {
        $attributes = $this->createStub(IAttributes::class);
        $attributes->method("getAttribute")->willReturn(false);

        $share = $this->createStub(IShare::class);
        $share->method("getAttributes")->willReturn($attributes);

        $this->assertFalse(FileUtility::canShareDownload($share));
    }

    /**
     * Returns true when the download attribute is explicitly set to true.
     */
    public function testCanShareDownloadReturnsTrueWhenAttributeIsTrue(): void {
        $attributes = $this->createStub(IAttributes::class);
        $attributes->method("getAttribute")->willReturn(true);

        $share = $this->createStub(IShare::class);
        $share->method("getAttributes")->willReturn($attributes);

        $this->assertTrue(FileUtility::canShareDownload($share));
    }
}
