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
use OCA\Onlyoffice\ExtraPermissions;
use OCP\Constants;
use OCP\DB\IPreparedStatement;
use OCP\DB\IResult;
use OCP\Files\Node;
use OCP\IAppConfig;
use OCP\IDBConnection;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager;
use OCP\Share\IShare;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

#[CoversClass(ExtraPermissions::class)]
#[AllowMockObjectsWithoutExpectations]
class ExtraPermissionsTest extends TestCase {

    private LoggerInterface&MockObject $logger;
    private IManager&MockObject $shareManager;
    private IAppConfig&MockObject $config;
    private AppConfig&MockObject $appConfig;
    private IDBConnection&MockObject $connection;
    private ExtraPermissions $extraPermissions;

    public function setUp(): void {
        parent::setUp();

        $this->logger       = $this->createMock(LoggerInterface::class);
        $this->shareManager = $this->createMock(IManager::class);
        $this->config       = $this->createMock(IAppConfig::class);
        $this->appConfig    = $this->createMock(AppConfig::class);
        $this->connection   = $this->createMock(IDBConnection::class);

        $this->extraPermissions = new ExtraPermissions(
            $this->logger,
            $this->shareManager,
            $this->config,
            $this->appConfig,
            $this->connection,
            null,
        );
    }

    private function stubDbEmpty(): void {
        $result = $this->createStub(IResult::class);
        $result->method("fetch")->willReturn(false);

        $statement = $this->createStub(IPreparedStatement::class);
        $statement->method("execute")->willReturn($result);

        $this->connection->method("prepare")->willReturn($statement);
    }

    private function makeShare(
        string $id,
        int $shareType,
        int $permissions,
        string $nodeName = "file.docx"
    ): IShare&MockObject {
        $node = $this->createStub(Node::class);
        $node->method("getName")->willReturn($nodeName);

        $share = $this->createMock(IShare::class);
        $share->method("getId")->willReturn($id);
        $share->method("getShareType")->willReturn($shareType);
        $share->method("getPermissions")->willReturn($permissions);
        $share->method("getNode")->willReturn($node);
        $share->method("getSharedWith")->willReturn("user1");
        $share->method("getSharedWithDisplayName")->willReturn("User One");

        return $share;
    }

    /**
     * Returns an empty array immediately when no shares are provided, without making any database calls.
     */
    public function testGetExtrasReturnsEmptyArrayWhenNoSharesGiven(): void {
        $result = $this->extraPermissions->getExtras([]);
        $this->assertSame([], $result);
    }

    /**
     * Returns null when the share cannot be resolved by any provider, indicating the share does not exist.
     */
    public function testGetExtraReturnsNullWhenShareNotFound(): void {
        $this->shareManager->method("getShareById")->willThrowException(new ShareNotFound());

        $result = $this->extraPermissions->getExtra("nonexistent");

        $this->assertNull($result);
    }

    /**
     * Returns null for non-link shares that carry PERMISSION_SHARE when resharing is enabled.
     */
    public function testGetExtraReturnsNullWhenShareHasPermissionShareAndIsNotLink(): void {
        $share = $this->makeShare("1", IShare::TYPE_USER, Constants::PERMISSION_SHARE);
        $this->shareManager->method("getShareById")->willReturn($share);
        $this->config->method("getValueString")->willReturn('yes');
        $this->stubDbEmpty();

        $result = $this->extraPermissions->getExtra("1");

        $this->assertNull($result);
    }

    /**
     * Returns extra permissions for a non-link share with PERMISSION_SHARE when the admin has
     * disabled resharing.
     */
    public function testGetExtraReturnsExtraPermissionsWhenShareHasPermissionShareButResharingDisabled(): void {
        $share = $this->makeShare("9", IShare::TYPE_USER, Constants::PERMISSION_SHARE | Constants::PERMISSION_UPDATE, "file.docx");
        $this->shareManager->method("getShareById")->willReturn($share);
        $this->config->method("getValueString")->willReturn('no');
        $this->appConfig->method("formatsSetting")->willReturn([
            "docx" => ["review" => true]
        ]);
        $this->stubDbEmpty();

        $result = $this->extraPermissions->getExtra("9");

        $this->assertNotNull($result);
        $this->assertSame(ExtraPermissions::REVIEW, $result["available"] & ExtraPermissions::REVIEW);
    }

    /**
     * Returns null when the shared file's extension is not registered in the format settings.
     */
    public function testGetExtraReturnsNullWhenFileFormatIsUnknown(): void {
        $share = $this->makeShare("2", IShare::TYPE_LINK, Constants::PERMISSION_UPDATE, "file.xyz");
        $this->shareManager->method("getShareById")->willReturn($share);
        $this->appConfig->method("formatsSetting")->willReturn([]);
        $this->stubDbEmpty();

        $result = $this->extraPermissions->getExtra("2");

        $this->assertNull($result);
    }

    /**
     * Sets the REVIEW bit in the available permissions bitmask when the file format declares review support.
     */
    public function testGetExtraReturnsAvailableReviewForReviewCapableFormat(): void {
        $share = $this->makeShare("3", IShare::TYPE_LINK, Constants::PERMISSION_UPDATE, "file.docx");
        $this->shareManager->method("getShareById")->willReturn($share);
        $this->appConfig->method("formatsSetting")->willReturn([
            "docx" => ["review" => true]
        ]);
        $this->stubDbEmpty();

        $result = $this->extraPermissions->getExtra("3");

        $this->assertNotNull($result);
        $this->assertSame(ExtraPermissions::REVIEW, $result["available"] & ExtraPermissions::REVIEW);
    }

    /**
     * Makes the COMMENT bit available when the format supports comments and REVIEW is not set on the share.
     */
    public function testGetExtraReturnsAvailableCommentWhenReviewNotChecked(): void {
        $share = $this->makeShare("4", IShare::TYPE_LINK, Constants::PERMISSION_UPDATE, "file.docx");
        $this->shareManager->method("getShareById")->willReturn($share);
        $this->appConfig->method("formatsSetting")->willReturn([
            "docx" => ["comment" => true]
        ]);
        $this->stubDbEmpty();

        $result = $this->extraPermissions->getExtra("4");

        $this->assertNotNull($result);
        $this->assertSame(ExtraPermissions::COMMENT, $result["available"] & ExtraPermissions::COMMENT);
    }

    /**
     * Makes the FILLFORMS bit available when the format supports form filling and REVIEW is not set on the share.
     */
    public function testGetExtraReturnsAvailableFillFormsWhenReviewNotChecked(): void {
        $share = $this->makeShare("5", IShare::TYPE_LINK, Constants::PERMISSION_UPDATE, "file.pdf");
        $this->shareManager->method("getShareById")->willReturn($share);
        $this->appConfig->method("formatsSetting")->willReturn([
            "pdf" => ["fillForms" => true]
        ]);
        $this->stubDbEmpty();

        $result = $this->extraPermissions->getExtra("5");

        $this->assertNotNull($result);
        $this->assertSame(ExtraPermissions::FILLFORMS, $result["available"] & ExtraPermissions::FILLFORMS);
    }

    /**
     * Makes the MODIFYFILTER bit available when the format supports it and COMMENT is not set on the share.
     */
    public function testGetExtraReturnsAvailableModifyFilterWhenCommentNotSet(): void {
        $share = $this->makeShare("6", IShare::TYPE_LINK, Constants::PERMISSION_UPDATE, "file.xlsx");
        $this->shareManager->method("getShareById")->willReturn($share);
        $this->appConfig->method("formatsSetting")->willReturn([
            "xlsx" => ["modifyFilter" => true]
        ]);
        $this->stubDbEmpty();

        $result = $this->extraPermissions->getExtra("6");

        $this->assertNotNull($result);
        $this->assertSame(ExtraPermissions::MODIFYFILTER, $result["available"] & ExtraPermissions::MODIFYFILTER);
    }

    /**
     * Seeds the result with id=-1 and the share id when no extra permissions row exists in the database.
     */
    public function testGetExtraInitialisesDefaultsWhenNoExtraStored(): void {
        $share = $this->makeShare("7", IShare::TYPE_LINK, Constants::PERMISSION_UPDATE, "file.docx");
        $this->shareManager->method("getShareById")->willReturn($share);
        $this->appConfig->method("formatsSetting")->willReturn([
            "docx" => ["review" => true]
        ]);
        $this->stubDbEmpty();

        $result = $this->extraPermissions->getExtra("7");

        $this->assertNotNull($result);
        $this->assertSame(-1, $result["id"]);
        $this->assertSame("7", $result["share_id"]);
    }

    /**
     * Enriches the result with share metadata (type, shareWith, shareWithName) to avoid re-fetching the share.
     */
    public function testGetExtraIncludesShareMetadataInResult(): void {
        $share = $this->makeShare("8", IShare::TYPE_LINK, Constants::PERMISSION_UPDATE, "file.docx");
        $this->shareManager->method("getShareById")->willReturn($share);
        $this->appConfig->method("formatsSetting")->willReturn([
            "docx" => ["review" => true]
        ]);
        $this->stubDbEmpty();

        $result = $this->extraPermissions->getExtra("8");

        $this->assertSame(IShare::TYPE_LINK, $result["type"]);
        $this->assertSame("user1", $result["shareWith"]);
        $this->assertSame("User One", $result["shareWithName"]);
    }
}
