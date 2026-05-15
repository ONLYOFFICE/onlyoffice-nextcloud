<?php

declare(strict_types=1);

require __DIR__ . '/../../../../../vendor-bin/behat/vendor/autoload.php';

use Behat\Behat\Context\Context;
use Behat\Hook\AfterScenario;
use Behat\Hook\BeforeSuite;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use PHPUnit\Framework\Assert;
use Psr\Http\Message\ResponseInterface;

class CoreContext implements Context
{
    const TEST_PASSWORD = "password";
    const REGULAR_USER = "editortest";

    private ResponseInterface $response;
    private ?string $currentUser = null;
    private array $createdUsers = [];
    private array $createdFiles = [];
    private ?int $lastTemplateId = null;
    private ?int $lastFileId = null;
    private ?array $lastCreatedFile = null;
    private ?string $lastCreatedUser = null;
    private array $lastCreatedUsers = [];
    private array $lastUserList = [];
    private ?array $lastMentionResponse = null;
    private ?array $lastReferenceResponse = null;
    private ?array $lastUrlResponse = null;
    private ?ResponseInterface $lastDownloadResponse = null;
    private ?array $lastHistoryResponse = null;
    private ?array $lastVersionResponse = null;
    private ?array $lastConfigResponse = null;
    private ?string $lastFilePath = null;
    private ?string $lastShareId = null;
    private ?string $lastShareToken = null;
    private ?array $lastExtraPermissionsResponse = null;
    private ?array $lastKeyOperationResponse = null;
    private ?array $lastFederationKeyResponse = null;
    private ?array $lastHealthcheckResponse = null;
    private bool $advancedEnabled = false;
    private array $lastAddressSettings = [];
    private ?int $otherFileId = null;

    public function __construct(
        private string $baseUrl,
        private string $adminUser,
        private string $adminPassword
    ) {}

    #[BeforeSuite]
    public static function createPHPUnitConfiguration(): void {
        (new \PHPUnit\TextUI\Configuration\Builder())->build([]);
    }

    #[AfterScenario]
    public function cleanUp(): void
    {
        foreach ($this->createdFiles as [$user, $path]) {
            $this->deleteFileViaWebDav($user, $path);
        }
        $this->createdFiles = [];

        $this->setCurrentUser($this->adminUser);
        foreach ($this->createdUsers as $username) {
            $this->sendOcsRequest('DELETE', "/cloud/users/$username");
        }
        $this->createdUsers = [];

        if ($this->lastTemplateId !== null) {
            $this->sendFrontpageRequest('DELETE', 'apps/onlyoffice/ajax/template', ['templateId' => $this->lastTemplateId]);
            $this->lastTemplateId = null;
        }

        $this->lastFileId = null;
        $this->lastCreatedFile = null;
        $this->lastCreatedUser = null;
        $this->lastCreatedUsers = [];
        $this->lastUserList = [];
        $this->lastMentionResponse = null;
        $this->lastReferenceResponse = null;
        $this->lastUrlResponse = null;
        $this->lastDownloadResponse = null;
        $this->lastHistoryResponse = null;
        $this->lastVersionResponse = null;
        $this->lastConfigResponse = null;
        $this->lastFilePath = null;
        $this->lastShareId = null;
        $this->lastShareToken = null;
        $this->lastExtraPermissionsResponse = null;
        $this->lastKeyOperationResponse = null;
        $this->lastFederationKeyResponse = null;
        $this->lastHealthcheckResponse = null;

        if ($this->advancedEnabled) {
            $this->disableAdvancedMode();
        }
    }

    #[Given('I am logged in as a regular user')]
    public function iAmLoggedInAsARegularUser(): void
    {
        if (!isset($this->createdUsers[self::REGULAR_USER])) {
            $this->setCurrentUser($this->adminUser);
            $this->sendOcsRequest('POST', '/cloud/users', [
                'userid' => self::REGULAR_USER,
                'password' => self::TEST_PASSWORD,
            ]);
            $this->createdUsers[self::REGULAR_USER] = self::REGULAR_USER;
        }
        $this->setCurrentUser(self::REGULAR_USER);
    }

    #[Given('a file named :name already exists in my home folder')]
    public function aFileNamedAlreadyExistsInMyHomeFolder(string $name): void
    {
        $this->sendFrontpageRequest('POST', 'apps/onlyoffice/ajax/new', [
            'name' => $name,
            'dir'  => '/',
        ]);
        $this->response->getBody()->rewind();
        $body = json_decode($this->response->getBody()->getContents(), true);
        Assert::assertArrayNotHasKey('error', $body, 'Failed to create prerequisite file: ' . ($body['error'] ?? ''));
        $this->createdFiles[] = [self::REGULAR_USER, '/' . $body['name']];
    }

    #[Given('a global template :name exists')]
    public function aGlobalTemplateExists(string $name): void
    {
        $previousUser = $this->setCurrentUser($this->adminUser);
        $tmp = tempnam(sys_get_temp_dir(), 'behat_') . '.' . pathinfo($name, PATHINFO_EXTENSION);
        file_put_contents($tmp, 'placeholder');

        $this->sendFrontpageRequest('POST', 'apps/onlyoffice/ajax/template', [
            'multipart' => [
                'name'     => 'file',
                'contents' => fopen($tmp, 'r'),
                'filename' => $name,
            ],
        ]);

        unlink($tmp);
        $this->response->getBody()->rewind();
        $body = json_decode($this->response->getBody()->getContents(), true);
        Assert::assertArrayHasKey('id', $body, 'Failed to upload template');
        $this->lastTemplateId = $body['id'];
        $this->setCurrentUser($previousUser);
    }

    #[When('I create a blank file named :name in my home folder')]
    public function iCreateABlankFileNamedInMyHomeFolder(string $name): void
    {
        $this->sendFrontpageRequest('POST', 'apps/onlyoffice/ajax/new', [
            'name' => $name,
            'dir'  => '/',
        ]);
        $this->response->getBody()->rewind();
        $body = json_decode($this->response->getBody()->getContents(), true);
        if (isset($body['id'])) {
            $this->lastCreatedFile = $body;
            $this->createdFiles[] = [self::REGULAR_USER, '/' . $body['name']];
        }
    }

    #[When('I create a file from that template in my home folder')]
    public function iCreateAFileFromThatTemplateInMyHomeFolder(): void
    {
        Assert::assertNotNull($this->lastTemplateId, 'No template was created in this scenario');
        $this->sendFrontpageRequest('POST', 'apps/onlyoffice/ajax/new', [
            'name'       => 'fromtemplate.docx',
            'dir'        => '/',
            'templateId' => $this->lastTemplateId,
        ]);
        $this->response->getBody()->rewind();
        $body = json_decode($this->response->getBody()->getContents(), true);
        Assert::assertArrayNotHasKey('error', $body, 'Failed to create file from template: ' . ($body['error'] ?? ''));
        $this->lastCreatedFile = $body;
        $this->createdFiles[] = [self::REGULAR_USER, '/' . $body['name']];
    }

    #[Given('I have the :filename asset file in my home folder')]
    public function iHaveTheAssetFileInMyHomeFolder(string $filename): void
    {
        $assetPath = __DIR__ . '/../../../../assets/' . $filename;
        Assert::assertFileExists($assetPath, "Asset file not found: $assetPath");

        $client = new Client();
        $response = $client->put("{$this->baseUrl}/remote.php/webdav/$filename", [
            'auth'    => [self::REGULAR_USER, self::TEST_PASSWORD],
            'body'    => fopen($assetPath, 'r'),
            'headers' => ['OCS-ApiRequest' => 'true'],
        ]);

        $fileId = $response->getHeaderLine('OC-FileId');
        Assert::assertNotEmpty($fileId, "WebDAV upload did not return OC-FileId for $filename");

        $this->lastFileId = (int) $fileId;
        $this->lastFilePath = "/$filename";
        $this->createdFiles[] = [self::REGULAR_USER, "/$filename"];
    }

    #[Given('I have a :ext file in my home folder')]
    public function iHaveAFileInMyHomeFolder(string $ext): void
    {
        $name = "testfile.$ext";
        $client = new Client();
        $response = $client->put("{$this->baseUrl}/remote.php/webdav/$name", [
            'auth'    => [self::REGULAR_USER, self::TEST_PASSWORD],
            'body'    => 'placeholder',
            'headers' => ['OCS-ApiRequest' => 'true'],
        ]);

        $fileId = $response->getHeaderLine('OC-FileId');
        Assert::assertNotEmpty($fileId, "WebDAV upload did not return OC-FileId for $name");

        $this->lastFileId = (int) $fileId;
        $this->lastFilePath = "/$name";
        $this->createdFiles[] = [self::REGULAR_USER, "/$name"];
    }

    #[When('I convert the file')]
    public function iConvertTheFile(): void
    {
        Assert::assertNotNull($this->lastFileId, 'No file was uploaded in this scenario');
        $this->sendFrontpageRequest('POST', 'apps/onlyoffice/ajax/convert', [
            'fileId' => $this->lastFileId,
        ]);
        $this->response->getBody()->rewind();
        $this->lastCreatedFile = json_decode($this->response->getBody()->getContents(), true);
        if (isset($this->lastCreatedFile['name'])) {
            $this->createdFiles[] = [self::REGULAR_USER, '/' . $this->lastCreatedFile['name']];
        }
    }

    #[Then('the conversion should succeed')]
    public function theConversionShouldSucceed(): void
    {
        Assert::assertSame(200, $this->response->getStatusCode());
        Assert::assertArrayNotHasKey('error', $this->lastCreatedFile ?? [], 'Conversion returned an error: ' . ($this->lastCreatedFile['error'] ?? ''));
        Assert::assertArrayHasKey('id', $this->lastCreatedFile ?? []);
    }

    #[Then('the converted file should be a :ext file')]
    public function theConvertedFileShouldBeA(string $ext): void
    {
        $name = $this->lastCreatedFile['name'] ?? '';
        Assert::assertSame($ext, strtolower(pathinfo($name, PATHINFO_EXTENSION)));
    }

    #[Then('the conversion should not be required')]
    public function theConversionShouldNotBeRequired(): void
    {
        Assert::assertSame(200, $this->response->getStatusCode());
        Assert::assertSame('Conversion is not required', $this->lastCreatedFile['error'] ?? null);
    }

    #[Then('the conversion should fail with an unsupported format error')]
    public function theConversionShouldFailWithAnUnsupportedFormatError(): void
    {
        Assert::assertSame(200, $this->response->getStatusCode());
        Assert::assertSame('Format is not supported', $this->lastCreatedFile['error'] ?? null);
    }

    #[When('I create a file with no name in my home folder')]
    public function iCreateAFileWithNoNameInMyHomeFolder(): void
    {
        $this->sendFrontpageRequest('POST', 'apps/onlyoffice/ajax/new', [
            'name' => '',
            'dir'  => '/',
        ]);
        $this->response->getBody()->rewind();
        $this->lastCreatedFile = json_decode($this->response->getBody()->getContents(), true);
    }

    #[Then('the file should be created successfully')]
    public function theFileShouldBeCreatedSuccessfully(): void
    {
        Assert::assertSame(200, $this->response->getStatusCode());
        Assert::assertArrayNotHasKey('error', $this->lastCreatedFile ?? [], 'File creation returned an error: ' . ($this->lastCreatedFile['error'] ?? ''));
        Assert::assertArrayHasKey('id', $this->lastCreatedFile ?? []);
    }

    #[Then('the created file should be named :name')]
    public function theCreatedFileShouldBeNamed(string $name): void
    {
        Assert::assertSame($name, $this->lastCreatedFile['name'] ?? null);
    }

    #[Then('the created file should have a different name than :name')]
    public function theCreatedFileShouldHaveADifferentNameThan(string $name): void
    {
        Assert::assertNotSame($name, $this->lastCreatedFile['name'] ?? null);
    }

    #[Then('the creation should fail')]
    public function theCreationShouldFail(): void
    {
        Assert::assertArrayHasKey('error', $this->lastCreatedFile ?? [], 'Expected creation to fail but no error was returned');
        Assert::assertNotEmpty($this->lastCreatedFile['error']);
    }

    #[Given('another user with an email exists')]
    public function anotherUserWithAnEmailExists(): void
    {
        $this->createTestUser('userwithmail', 'user@example.com');
    }

    #[Given('another user without an email exists')]
    public function anotherUserWithoutAnEmailExists(): void
    {
        $this->createTestUser('userwithoutmail', null);
    }

    #[Given('a user with display name :displayName and an email exists')]
    public function aUserWithDisplayNameAndAnEmailExists(string $displayName): void
    {
        $uid = strtolower(str_replace(' ', '_', $displayName));
        $this->createTestUser($uid, "$uid@example.com", $displayName);
        $this->lastCreatedUser = $uid;
    }

    #[When('I request the user list for the file')]
    public function iRequestTheUserListForTheFile(): void
    {
        Assert::assertNotNull($this->lastFileId);
        $this->sendFrontpageRequest('GET', "apps/onlyoffice/ajax/users?fileId={$this->lastFileId}");
        $this->response->getBody()->rewind();
        $this->lastUserList = json_decode($this->response->getBody()->getContents(), true) ?? [];
    }

    #[When('I search for :query in the user list for the file')]
    public function iSearchForInTheUserListForTheFile(string $query): void
    {
        Assert::assertNotNull($this->lastFileId);
        $this->sendFrontpageRequest('GET', "apps/onlyoffice/ajax/users?fileId={$this->lastFileId}&search=" . urlencode($query));
        $this->response->getBody()->rewind();
        $this->lastUserList = json_decode($this->response->getBody()->getContents(), true) ?? [];
    }

    #[Then('the response should contain that user')]
    public function theResponseShouldContainThatUser(): void
    {
        Assert::assertNotNull($this->lastCreatedUser);
        $ids = array_column($this->lastUserList, 'id');
        Assert::assertContains($this->lastCreatedUser, $ids);
    }

    #[Then('the response should not contain that user')]
    public function theResponseShouldNotContainThatUser(): void
    {
        Assert::assertNotNull($this->lastCreatedUser);
        $ids = array_column($this->lastUserList, 'id');
        Assert::assertNotContains($this->lastCreatedUser, $ids);
    }

    #[Then('the response should not contain the current user')]
    public function theResponseShouldNotContainTheCurrentUser(): void
    {
        $ids = array_column($this->lastUserList, 'id');
        Assert::assertNotContains(self::REGULAR_USER, $ids);
    }

    #[Then('the response should contain a user named :name')]
    public function theResponseShouldContainAUserNamed(string $name): void
    {
        $names = array_column($this->lastUserList, 'name');
        Assert::assertContains($name, $names);
    }

    #[Then('the response should not contain a user named :name')]
    public function theResponseShouldNotContainAUserNamed(string $name): void
    {
        $names = array_column($this->lastUserList, 'name');
        Assert::assertNotContains($name, $names);
    }

    #[When('I request user info for that user')]
    public function iRequestUserInfoForThatUser(): void
    {
        Assert::assertNotNull($this->lastCreatedUser);
        $this->requestUserInfo([$this->lastCreatedUser]);
    }

    #[When('I request user info for all created users')]
    public function iRequestUserInfoForAllCreatedUsers(): void
    {
        $this->requestUserInfo($this->lastCreatedUsers);
    }

    #[When('I request user info for :userId')]
    public function iRequestUserInfoFor(string $userId): void
    {
        $this->requestUserInfo([$userId]);
    }

    #[Then('the response should contain one user')]
    public function theResponseShouldContainOneUser(): void
    {
        Assert::assertCount(1, $this->lastUserList);
    }

    #[Then('the response should contain :count users')]
    public function theResponseShouldContainUsers(int $count): void
    {
        Assert::assertCount($count, $this->lastUserList);
    }

    #[Then("that user's name should be :name")]
    public function thatUserSNameShouldBe(string $name): void
    {
        Assert::assertSame($name, $this->lastUserList[0]['name'] ?? null);
    }

    #[Then('that user should not have an image field')]
    public function thatUserShouldNotHaveAnImageField(): void
    {
        Assert::assertArrayNotHasKey('image', $this->lastUserList[0] ?? []);
    }

    #[Then('the response should be an empty list')]
    public function theResponseShouldBeAnEmptyList(): void
    {
        Assert::assertSame([], $this->lastUserList);
    }

    #[When('I mention that user in the document with comment :comment')]
    public function iMentionThatUserInTheDocumentWithComment(string $comment): void
    {
        Assert::assertNotNull($this->lastFileId);
        Assert::assertNotNull($this->lastCreatedUser);
        $email = "{$this->lastCreatedUser}@example.com";
        $this->sendFrontpageRequest('POST', 'apps/onlyoffice/ajax/mention', [
            'fileId'  => $this->lastFileId,
            'anchor'  => 'anchor1',
            'comment' => $comment,
            'emails'  => [$email],
        ]);
        $this->response->getBody()->rewind();
        $this->lastMentionResponse = json_decode($this->response->getBody()->getContents(), true);
    }

    #[Then('the mention should be sent successfully')]
    public function theMentionShouldBeSentSuccessfully(): void
    {
        Assert::assertSame(200, $this->response->getStatusCode());
        Assert::assertArrayNotHasKey('error', $this->lastMentionResponse ?? []);
        Assert::assertArrayHasKey('message', $this->lastMentionResponse ?? []);
    }


    #[Then('the file should be shared with that user')]
    public function theFileShouldBeSharedWithThatUser(): void
    {
        Assert::assertNotNull($this->lastCreatedUser);
        $previousUser = $this->setCurrentUser($this->lastCreatedUser);
        $this->sendOcsRequest('GET', '/apps/files_sharing/api/v1/shares?shared_with_me=true');
        $this->response->getBody()->rewind();
        $body = json_decode($this->response->getBody()->getContents(), true);
        $sharedFileIds = array_column($body['ocs']['data'] ?? [], 'item_source');
        Assert::assertContains($this->lastFileId, $sharedFileIds);
        $this->setCurrentUser($previousUser);
    }

    #[When('I resolve the file by path')]
    #[Given('I have already resolved the file by path')]
    public function iResolveTheFileByPath(): void
    {
        $this->sendFrontpageRequest('POST', 'apps/onlyoffice/ajax/reference', [
            'json' => ['referenceData' => [], 'path' => '/testfile.docx'],
        ]);
        $this->response->getBody()->rewind();
        $this->lastReferenceResponse = json_decode($this->response->getBody()->getContents(), true);
    }

    #[When('I resolve the file by its reference data')]
    public function iResolveTheFileByItsReferenceData(): void
    {
        Assert::assertNotNull($this->lastReferenceResponse);
        Assert::assertArrayHasKey('referenceData', $this->lastReferenceResponse, 'No referenceData in previous response');
        $this->sendFrontpageRequest('POST', 'apps/onlyoffice/ajax/reference', [
            'json' => ['referenceData' => $this->lastReferenceResponse['referenceData']],
        ]);
        $this->response->getBody()->rewind();
        $this->lastReferenceResponse = json_decode($this->response->getBody()->getContents(), true);
    }

    #[When('I resolve a file by a path that does not exist')]
    public function iResolveAFileByAPathThatDoesNotExist(): void
    {
        $this->sendFrontpageRequest('POST', 'apps/onlyoffice/ajax/reference', [
            'json' => ['referenceData' => [], 'path' => '/nonexistent.docx'],
        ]);
        $this->response->getBody()->rewind();
        $this->lastReferenceResponse = json_decode($this->response->getBody()->getContents(), true);
    }

    #[Then('the reference should be resolved successfully')]
    public function theReferenceShouldBeResolvedSuccessfully(): void
    {
        Assert::assertSame(200, $this->response->getStatusCode());
        Assert::assertArrayNotHasKey('error', $this->lastReferenceResponse ?? []);
        Assert::assertArrayHasKey('fileType', $this->lastReferenceResponse ?? []);
        Assert::assertArrayHasKey('url', $this->lastReferenceResponse ?? []);
    }

    #[Then('the response should contain reference data with a file key and instance id')]
    public function theResponseShouldContainReferenceDataWithAFileKeyAndInstanceId(): void
    {
        $referenceData = $this->lastReferenceResponse['referenceData'] ?? [];
        Assert::assertArrayHasKey('fileKey', $referenceData);
        Assert::assertArrayHasKey('instanceId', $referenceData);
        Assert::assertNotEmpty($referenceData['fileKey']);
        Assert::assertNotEmpty($referenceData['instanceId']);
    }

    #[Then('the reference should not be found')]
    public function theReferenceShouldNotBeFound(): void
    {
        Assert::assertSame(200, $this->response->getStatusCode());
        Assert::assertArrayHasKey('error', $this->lastReferenceResponse ?? []);
    }

    private function requestUserInfo(array $userIds): void
    {
        $encoded = urlencode(json_encode($userIds));
        $this->sendFrontpageRequest('GET', "apps/onlyoffice/ajax/userInfo?userIds=$encoded");
        $this->response->getBody()->rewind();
        $this->lastUserList = json_decode($this->response->getBody()->getContents(), true) ?? [];
    }

    private function createTestUser(string $uid, ?string $email, ?string $displayName = null): void
    {
        $previousUser = $this->setCurrentUser($this->adminUser);
        $this->sendOcsRequest('POST', '/cloud/users', array_filter([
            'userid'      => $uid,
            'password'    => self::TEST_PASSWORD,
            'email'       => $email,
            'displayName' => $displayName,
        ]));
        $this->createdUsers[$uid] = $uid;
        $this->lastCreatedUser = $uid;
        $this->lastCreatedUsers[] = $uid;
        $this->setCurrentUser($previousUser);
    }

    #[When('I request the URL for the file')]
    public function iRequestTheUrlForTheFile(): void
    {
        $this->sendFrontpageRequest('GET', 'apps/onlyoffice/ajax/url?filePath=/testfile.docx');
        $this->response->getBody()->rewind();
        $this->lastUrlResponse = json_decode($this->response->getBody()->getContents(), true);
    }

    #[When('I request the URL for a file that does not exist')]
    public function iRequestTheUrlForAFileThatDoesNotExist(): void
    {
        $this->sendFrontpageRequest('GET', 'apps/onlyoffice/ajax/url?filePath=/nonexistent.docx');
        $this->response->getBody()->rewind();
        $this->lastUrlResponse = json_decode($this->response->getBody()->getContents(), true);
    }

    #[Then('the response should contain a download URL')]
    public function theResponseShouldContainADownloadUrl(): void
    {
        Assert::assertSame(200, $this->response->getStatusCode());
        Assert::assertArrayHasKey('url', $this->lastUrlResponse ?? []);
        Assert::assertNotEmpty($this->lastUrlResponse['url']);
    }

    #[Then('the file type in the response should be :ext')]
    public function theFileTypeInTheResponseShouldBe(string $ext): void
    {
        Assert::assertSame($ext, $this->lastUrlResponse['fileType'] ?? null);
    }

    #[Then('the URL request should fail')]
    public function theUrlRequestShouldFail(): void
    {
        Assert::assertArrayHasKey('error', $this->lastUrlResponse ?? []);
    }

    #[When('I download the file converting it to :ext')]
    public function iDownloadTheFileConvertingItTo(string $ext): void
    {
        Assert::assertNotNull($this->lastFileId);
        $this->sendFrontpageRequest(
            'GET',
            "apps/onlyoffice/downloadas?fileId={$this->lastFileId}&toExtension=$ext"
        );
        $this->lastDownloadResponse = $this->response;
    }

    #[When('I download a file that does not exist')]
    public function iDownloadAFileThatDoesNotExist(): void
    {
        $this->sendFrontpageRequest('GET', 'apps/onlyoffice/downloadas?fileId=999999');
        $this->lastDownloadResponse = $this->response;
    }

    #[Then('the download should succeed')]
    public function theDownloadShouldSucceed(): void
    {
        Assert::assertSame(200, $this->lastDownloadResponse->getStatusCode());
        Assert::assertNotEmpty($this->lastDownloadResponse->getHeaderLine('Content-Disposition'));
    }

    #[Then('the downloaded file should have the extension :ext')]
    public function theDownloadedFileShouldHaveTheExtension(string $ext): void
    {
        $disposition = $this->lastDownloadResponse->getHeaderLine('Content-Disposition');
        Assert::assertSame(1, preg_match('/filename=(?:"(?<quoted>[^"]+)"|(?<unquoted>[^";\s]+))/', $disposition, $matches));
        $filename = $matches['quoted'] !== '' ? $matches['quoted'] : $matches['unquoted'];
        Assert::assertSame($ext, pathinfo($filename, PATHINFO_EXTENSION));
    }

    #[Then('no file should be served for download')]
    public function noFileShouldBeServedForDownload(): void
    {
        Assert::assertEmpty($this->lastDownloadResponse->getHeaderLine('Content-Disposition'));
    }

    #[When('I request the editor config for the file')]
    public function iRequestTheEditorConfigForTheFile(): void
    {
        Assert::assertNotNull($this->lastFileId);
        $this->sendOcsRequest('GET', "/apps/onlyoffice/api/v1/config/{$this->lastFileId}");
        $this->response->getBody()->rewind();
        $body = json_decode($this->response->getBody()->getContents(), true);
        $this->lastConfigResponse = $body ?? null;
    }

    #[When('I request the editor config for a file that does not exist')]
    public function iRequestTheEditorConfigForAFileThatDoesNotExist(): void
    {
        $this->sendOcsRequest('GET', '/apps/onlyoffice/api/v1/config/999999');
        $this->response->getBody()->rewind();
        $body = json_decode($this->response->getBody()->getContents(), true);
        $this->lastConfigResponse = $body ?? null;
    }

    #[Then('the config should be returned successfully')]
    public function theConfigShouldBeReturnedSuccessfully(): void
    {
        Assert::assertSame(200, $this->response->getStatusCode());
        Assert::assertIsArray($this->lastConfigResponse);
        Assert::assertArrayNotHasKey('error', $this->lastConfigResponse);
    }

    #[Then('the config should contain document and editor settings')]
    public function theConfigShouldContainDocumentAndEditorSettings(): void
    {
        Assert::assertArrayHasKey('document', $this->lastConfigResponse ?? []);
        Assert::assertArrayHasKey('documentType', $this->lastConfigResponse ?? []);
        Assert::assertArrayHasKey('editorConfig', $this->lastConfigResponse ?? []);
        Assert::assertArrayHasKey('fileType', $this->lastConfigResponse['document'] ?? []);
        Assert::assertArrayHasKey('key', $this->lastConfigResponse['document'] ?? []);
        Assert::assertArrayHasKey('title', $this->lastConfigResponse['document'] ?? []);
    }

    #[Then('the document type should be :type')]
    public function theDocumentTypeShouldBe(string $type): void
    {
        Assert::assertSame($type, $this->lastConfigResponse['documentType'] ?? null);
    }

    #[Then('the config request should fail with an unsupported format error')]
    public function theConfigRequestShouldFailWithAnUnsupportedFormatError(): void
    {
        Assert::assertSame(200, $this->response->getStatusCode());
        Assert::assertSame('Format is not supported', $this->lastConfigResponse['error'] ?? null);
    }

    #[Then('the config request should fail')]
    public function theConfigRequestShouldFail(): void
    {
        Assert::assertSame(200, $this->response->getStatusCode());
        Assert::assertArrayHasKey('error', $this->lastConfigResponse ?? []);
    }

    #[Given('the file is shared via a link')]
    public function theFileIsSharedViaALink(): void
    {
        $this->createLinkShare(1); // READ only
    }

    #[Given('the file is shared via a public link with edit permission')]
    public function theFileIsSharedViaAPublicLinkWithEditPermission(): void
    {
        $this->createLinkShare(15); // READ | UPDATE | CREATE | DELETE
    }

    private function createLinkShare(int $permissions): void
    {
        Assert::assertNotNull($this->lastFilePath, 'No file has been created in this scenario');

        $previousUser = $this->setCurrentUser(self::REGULAR_USER);
        $this->sendOcsRequest('POST', '/apps/files_sharing/api/v1/shares', [
            'path'        => $this->lastFilePath,
            'shareType'   => 3,
            'permissions' => $permissions,
        ]);
        $this->response->getBody()->rewind();
        $body = json_decode($this->response->getBody()->getContents(), true);
        $this->lastShareToken = $body['ocs']['data']['token'] ?? null;
        $this->lastShareId = (string) ($body['ocs']['data']['id'] ?? '');
        Assert::assertNotEmpty($this->lastShareToken, 'Failed to create link share: ' . json_encode($body['ocs']['meta'] ?? []));
        $this->setCurrentUser($previousUser);
    }

    #[When('I lock the document via the share token')]
    public function iLockTheDocumentViaTheShareToken(): void
    {
        Assert::assertNotNull($this->lastShareToken);
        $this->sendOcsRequest('POST', '/apps/onlyoffice/api/v1/keylock', [
            'shareToken' => $this->lastShareToken,
            'path'       => '',
            'lock'       => 1,
            'fs'         => 0,
        ]);
        $this->response->getBody()->rewind();
        $body = json_decode($this->response->getBody()->getContents(), true);
        $this->lastKeyOperationResponse = $body['ocs']['data'] ?? null;
    }

    #[When('I unlock the document via the share token')]
    public function iUnlockTheDocumentViaTheShareToken(): void
    {
        Assert::assertNotNull($this->lastShareToken);
        $this->sendOcsRequest('POST', '/apps/onlyoffice/api/v1/keylock', [
            'shareToken' => $this->lastShareToken,
            'path'       => '',
            'lock'       => 0,
            'fs'         => 0,
        ]);
        $this->response->getBody()->rewind();
        $body = json_decode($this->response->getBody()->getContents(), true);
        $this->lastKeyOperationResponse = $body['ocs']['data'] ?? null;
    }

    #[When('I request a preview for the template')]
    public function iRequestAPreviewForTheTemplate(): void
    {
        Assert::assertNotNull($this->lastTemplateId, 'No template was created in this scenario');
        $this->sendFrontpageRequest('GET', "apps/onlyoffice/preview?fileId={$this->lastTemplateId}");
    }

    #[When('I request a preview for a non-existent template')]
    public function iRequestAPreviewForANonExistentTemplate(): void
    {
        $this->sendFrontpageRequest('GET', 'apps/onlyoffice/preview?fileId=999999');
    }

    #[When('I request a preview with a zero file id')]
    public function iRequestAPreviewWithAZeroFileId(): void
    {
        $this->sendFrontpageRequest('GET', 'apps/onlyoffice/preview?fileId=0');
    }

    #[When('I request a preview with zero dimensions')]
    public function iRequestAPreviewWithZeroDimensions(): void
    {
        $this->sendFrontpageRequest('GET', 'apps/onlyoffice/preview?fileId=1&x=0&y=0');
    }

    #[Then('the preview request should be rejected')]
    public function thePreviewRequestShouldBeRejected(): void
    {
        Assert::assertSame(400, $this->response->getStatusCode());
    }

    #[Then('the preview should not be found')]
    public function thePreviewShouldNotBeFound(): void
    {
        Assert::assertSame(404, $this->response->getStatusCode());
    }

    #[Then('the preview request should not be rejected')]
    public function thePreviewRequestShouldNotBeRejected(): void
    {
        Assert::assertNotSame(400, $this->response->getStatusCode());
    }

    #[When('I lock the document via an invalid share token')]
    public function iLockTheDocumentViaAnInvalidShareToken(): void
    {
        $this->sendOcsRequest('POST', '/apps/onlyoffice/api/v1/keylock', [
            'shareToken' => 'invalidtoken',
            'path'       => '',
            'lock'       => 1,
            'fs'         => 0,
        ]);
        $this->response->getBody()->rewind();
        $body = json_decode($this->response->getBody()->getContents(), true);
        $this->lastKeyOperationResponse = $body['ocs']['data'] ?? null;
    }

    #[Then('the key operation should succeed')]
    public function theKeyOperationShouldSucceed(): void
    {
        Assert::assertSame(200, $this->response->getStatusCode());
        Assert::assertArrayNotHasKey('error', $this->lastKeyOperationResponse ?? []);
    }

    #[Then('the key operation should fail')]
    public function theKeyOperationShouldFail(): void
    {
        Assert::assertSame(200, $this->response->getStatusCode());
        Assert::assertArrayHasKey('error', $this->lastKeyOperationResponse ?? []);
    }

    #[Given('the advanced feature is enabled')]
    public function theAdvancedFeatureIsEnabled(): void
    {
        $this->enableAdvancedMode();
    }

    #[Given('another user exists')]
    public function anotherUserExists(): void
    {
        $this->createTestUser('sharetarget', null);
    }

    #[Given('the file is shared with that user with update permission and without resharing')]
    public function theFileIsSharedWithThatUser(): void
    {
        Assert::assertNotNull($this->lastFilePath, 'No file has been created in this scenario');
        Assert::assertNotNull($this->lastCreatedUser, 'No other user has been created in this scenario');

        $previousUser = $this->setCurrentUser(self::REGULAR_USER);
        $this->sendOcsRequest('POST', '/apps/files_sharing/api/v1/shares', [
            'path'        => $this->lastFilePath,
            'shareType'   => 0,
            'shareWith'   => $this->lastCreatedUser,
            'permissions' => 3, // READ | UPDATE, no SHARE
        ]);
        $this->response->getBody()->rewind();
        $body = json_decode($this->response->getBody()->getContents(), true);
        $this->lastShareId = (string) ($body['ocs']['data']['id'] ?? '');
        Assert::assertNotEmpty($this->lastShareId, 'Failed to create share: ' . json_encode($body['ocs']['meta'] ?? []));
        $this->setCurrentUser($previousUser);
    }

    #[When('I request the extra permissions for the file')]
    public function iRequestTheExtraPermissionsForTheFile(): void
    {
        Assert::assertNotNull($this->lastFileId);
        $this->sendOcsRequest('GET', "/apps/onlyoffice/api/v1/shares/{$this->lastFileId}");
        $this->response->getBody()->rewind();
        $body = json_decode($this->response->getBody()->getContents(), true);
        $this->lastExtraPermissionsResponse = $body['ocs']['data'] ?? null;
    }

    #[When('I set the review permission on a non-existent share')]
    public function iSetTheReviewPermissionOnANonExistentShare(): void
    {
        $this->sendOcsRequest('PUT', '/apps/onlyoffice/api/v1/shares', [
            'extraId'     => 0,
            'shareId'     => 'nonexistent',
            'fileId'      => 999999,
            'permissions' => 1,
        ]);
        $this->response->getBody()->rewind();
        $body = json_decode($this->response->getBody()->getContents(), true);
        $this->lastExtraPermissionsResponse = $body['ocs']['data'] ?? null;
    }

    #[When('I set the review permission on that share')]
    public function iSetTheReviewPermissionOnThatShare(): void
    {
        Assert::assertNotNull($this->lastShareId, 'No share has been created in this scenario');
        Assert::assertNotNull($this->lastFileId);
        $this->sendOcsRequest('PUT', '/apps/onlyoffice/api/v1/shares', [
            'extraId'     => 0,
            'shareId'     => $this->lastShareId,
            'fileId'      => $this->lastFileId,
            'permissions' => 1,
        ]);
        $this->response->getBody()->rewind();
        $body = json_decode($this->response->getBody()->getContents(), true);
        $this->lastExtraPermissionsResponse = $body['ocs']['data'] ?? null;
    }

    #[Then('the extra permissions request should fail')]
    public function theExtraPermissionsRequestShouldFail(): void
    {
        Assert::assertSame(400, $this->response->getStatusCode());
    }

    #[Then('the extra permissions should be an empty list')]
    public function theExtraPermissionsShouldBeAnEmptyList(): void
    {
        Assert::assertSame(200, $this->response->getStatusCode());
        Assert::assertSame([], $this->lastExtraPermissionsResponse);
    }

    #[Then('the extra permissions request should succeed')]
    public function theExtraPermissionsRequestShouldSucceed(): void
    {
        Assert::assertSame(200, $this->response->getStatusCode());
        Assert::assertIsArray($this->lastExtraPermissionsResponse);
    }

    #[Then('the share should have the review permission set')]
    public function theShareShouldHaveTheReviewPermissionSet(): void
    {
        Assert::assertSame(1, $this->lastExtraPermissionsResponse['permissions'] ?? null);
    }

    #[Given('a third user exists with a :ext file')]
    public function aThirdUserExistsWithAFile(string $ext): void
    {
        $this->createTestUser('attacker', null);

        $name = "attackerfile.$ext";
        $client = new Client();
        $response = $client->put("{$this->baseUrl}/remote.php/webdav/$name", [
            'auth'    => ['attacker', self::TEST_PASSWORD],
            'body'    => 'placeholder',
            'headers' => ['OCS-ApiRequest' => 'true'],
        ]);

        $fileId = $response->getHeaderLine('OC-FileId');
        Assert::assertNotEmpty($fileId, "WebDAV upload did not return OC-FileId for $name");

        $this->otherFileId = (int) $fileId;
        $this->createdFiles[] = ['attacker', "/$name"];
    }

    #[When('the third user sets the review permission using the first user\'s share on their own file')]
    public function theThirdUserSetsTheReviewPermissionUsingTheFirstUsersShare(): void
    {
        Assert::assertNotNull($this->lastShareId, 'No share has been created in this scenario');
        Assert::assertNotNull($this->otherFileId, 'No attacker file has been created in this scenario');

        $previousUser = $this->setCurrentUser('attacker');
        $this->sendOcsRequest('PUT', '/apps/onlyoffice/api/v1/shares', [
            'extraId'     => 0,
            'shareId'     => $this->lastShareId,
            'fileId'      => $this->otherFileId,
            'permissions' => 1,
        ]);
        $this->response->getBody()->rewind();
        $body = json_decode($this->response->getBody()->getContents(), true);
        $this->lastExtraPermissionsResponse = $body['ocs']['data'] ?? null;
        $this->setCurrentUser($previousUser);
    }

    #[Given('I am logged in as that share recipient')]
    public function iAmLoggedInAsThatShareRecipient(): void
    {
        Assert::assertNotNull($this->lastCreatedUser, 'No other user has been created in this scenario');
        $this->setCurrentUser($this->lastCreatedUser);
    }

    #[Given('I have updated the file content')]
    public function iHaveUpdatedTheFileContent(): void
    {
        $lastFile = end($this->createdFiles);
        Assert::assertNotEmpty($lastFile, 'No file has been created yet');
        [$user, $path] = $lastFile;

        $auth = $user === $this->adminUser
            ? [$this->adminUser, $this->adminPassword]
            : [$user, self::TEST_PASSWORD];

        $client = new Client();
        $response = $client->put("{$this->baseUrl}/remote.php/webdav$path", [
            'auth'    => $auth,
            'body'    => 'updated content',
            'headers' => ['OCS-ApiRequest' => 'true'],
        ]);
        Assert::assertLessThan(400, $response->getStatusCode());
    }

    #[When('I request the history for the file')]
    public function iRequestTheHistoryForTheFile(): void
    {
        Assert::assertNotNull($this->lastFileId);
        $this->sendFrontpageRequest('GET', "apps/onlyoffice/ajax/history?fileId={$this->lastFileId}");
        $this->response->getBody()->rewind();
        $this->lastHistoryResponse = json_decode($this->response->getBody()->getContents(), true);
    }

    #[When('I request the history for a file that does not exist')]
    public function iRequestTheHistoryForAFileThatDoesNotExist(): void
    {
        $this->sendFrontpageRequest('GET', 'apps/onlyoffice/ajax/history?fileId=999999');
        $this->response->getBody()->rewind();
        $this->lastHistoryResponse = json_decode($this->response->getBody()->getContents(), true);
    }

    #[When('I request version :version of the file')]
    public function iRequestVersionOfTheFile(int $version): void
    {
        Assert::assertNotNull($this->lastFileId);
        $this->sendFrontpageRequest('GET', "apps/onlyoffice/ajax/version?fileId={$this->lastFileId}&version=$version");
        $this->response->getBody()->rewind();
        $this->lastVersionResponse = json_decode($this->response->getBody()->getContents(), true);
    }

    #[When('I request version :version of a file that does not exist')]
    public function iRequestVersionOfAFileThatDoesNotExist(int $version): void
    {
        $this->sendFrontpageRequest('GET', "apps/onlyoffice/ajax/version?fileId=999999&version=$version");
        $this->response->getBody()->rewind();
        $this->lastVersionResponse = json_decode($this->response->getBody()->getContents(), true);
    }

    #[When('I restore version :version of the file')]
    public function iRestoreVersionOfTheFile(int $version): void
    {
        Assert::assertNotNull($this->lastFileId);
        $this->sendFrontpageRequest('PUT', 'apps/onlyoffice/ajax/restore', [
            'fileId'  => $this->lastFileId,
            'version' => $version,
        ]);
        $this->response->getBody()->rewind();
        $this->lastHistoryResponse = json_decode($this->response->getBody()->getContents(), true);
    }

    #[When('I restore version :version of a file that does not exist')]
    public function iRestoreVersionOfAFileThatDoesNotExist(int $version): void
    {
        $this->sendFrontpageRequest('PUT', 'apps/onlyoffice/ajax/restore', [
            'fileId'  => 999999,
            'version' => $version,
        ]);
        $this->response->getBody()->rewind();
        $this->lastHistoryResponse = json_decode($this->response->getBody()->getContents(), true);
    }

    #[Then('the history should be retrieved successfully')]
    public function theHistoryShouldBeRetrievedSuccessfully(): void
    {
        Assert::assertSame(200, $this->response->getStatusCode());
        Assert::assertIsArray($this->lastHistoryResponse);
        Assert::assertArrayNotHasKey('error', $this->lastHistoryResponse);
    }

    #[Then('the history should contain at least 1 entry')]
    public function theHistoryShouldContainAtLeast1Entry(): void
    {
        Assert::assertNotEmpty($this->lastHistoryResponse);
    }

    #[Then('each history entry should have a key, version, and created timestamp')]
    public function eachHistoryEntryShouldHaveAKeyVersionAndCreatedTimestamp(): void
    {
        Assert::assertNotEmpty($this->lastHistoryResponse);
        foreach ($this->lastHistoryResponse as $entry) {
            Assert::assertArrayHasKey('key', $entry);
            Assert::assertArrayHasKey('version', $entry);
            Assert::assertArrayHasKey('created', $entry);
        }
    }

    #[Then('the history request should fail')]
    public function theHistoryRequestShouldFail(): void
    {
        Assert::assertSame(200, $this->response->getStatusCode());
        Assert::assertArrayHasKey('error', $this->lastHistoryResponse ?? []);
    }

    #[Then('the version data should be retrieved successfully')]
    public function theVersionDataShouldBeRetrievedSuccessfully(): void
    {
        Assert::assertSame(200, $this->response->getStatusCode());
        Assert::assertArrayNotHasKey('error', $this->lastVersionResponse ?? []);
    }

    #[Then('the version data should contain a file type and download URL')]
    public function theVersionDataShouldContainAFileTypeAndDownloadUrl(): void
    {
        Assert::assertArrayHasKey('fileType', $this->lastVersionResponse ?? []);
        Assert::assertArrayHasKey('url', $this->lastVersionResponse ?? []);
        Assert::assertArrayHasKey('key', $this->lastVersionResponse ?? []);
        Assert::assertArrayHasKey('version', $this->lastVersionResponse ?? []);
    }

    #[Then('the version request should fail')]
    public function theVersionRequestShouldFail(): void
    {
        Assert::assertSame(200, $this->response->getStatusCode());
        Assert::assertArrayHasKey('error', $this->lastVersionResponse ?? []);
    }

    #[When('I request the document key via the share token')]
    public function iRequestTheDocumentKeyViaTheShareToken(): void
    {
        Assert::assertNotNull($this->lastShareToken);
        $this->sendOcsRequest('POST', '/apps/onlyoffice/api/v1/key', [
            'shareToken' => $this->lastShareToken,
            'path'       => '',
        ]);
        $this->response->getBody()->rewind();
        $body = json_decode($this->response->getBody()->getContents(), true);
        $this->lastFederationKeyResponse = $body['ocs']['data'] ?? null;
    }

    #[When('I request the document key via an invalid share token')]
    public function iRequestTheDocumentKeyViaAnInvalidShareToken(): void
    {
        $this->sendOcsRequest('POST', '/apps/onlyoffice/api/v1/key', [
            'shareToken' => 'invalidtoken',
            'path'       => '',
        ]);
        $this->response->getBody()->rewind();
        $body = json_decode($this->response->getBody()->getContents(), true);
        $this->lastFederationKeyResponse = $body['ocs']['data'] ?? null;
    }

    #[Then('the key response should contain a document key')]
    public function theKeyResponseShouldContainADocumentKey(): void
    {
        Assert::assertSame(200, $this->response->getStatusCode());
        Assert::assertArrayNotHasKey('error', $this->lastFederationKeyResponse ?? []);
        Assert::assertArrayHasKey('key', $this->lastFederationKeyResponse ?? []);
        Assert::assertNotEmpty($this->lastFederationKeyResponse['key']);
    }

    #[Then('the key response should contain an error')]
    public function theKeyResponseShouldContainAnError(): void
    {
        Assert::assertSame(200, $this->response->getStatusCode());
        Assert::assertArrayHasKey('error', $this->lastFederationKeyResponse ?? []);
    }

    #[When('I request the federation healthcheck')]
    public function iRequestTheFederationHealthcheck(): void
    {
        $this->sendOcsRequest('GET', '/apps/onlyoffice/api/v1/healthcheck');
        $this->response->getBody()->rewind();
        $body = json_decode($this->response->getBody()->getContents(), true);
        $this->lastHealthcheckResponse = $body['ocs']['data'] ?? null;
    }

    #[Then('the healthcheck should report the service is alive')]
    public function theHealthcheckShouldReportTheServiceIsAlive(): void
    {
        Assert::assertSame(200, $this->response->getStatusCode());
        Assert::assertTrue($this->lastHealthcheckResponse['alive'] ?? false);
    }

    #[Given('a user :username exists')]
    public function aUserExists(string $username): void
    {
        if (!isset($this->createdUsers[$username])) {
            $this->createTestUser($username, null);
        }
    }

    #[Given('I am logged in as :username')]
    public function iAmLoggedInAs(string $username): void
    {
        if ($username === 'admin') {
            $this->setCurrentUser($this->adminUser);
        } else {
            $this->setCurrentUser($username);
        }
    }

    #[When('I send a GET request to :path')]
    public function iSendAGetRequestTo(string $path): void
    {
        $this->setCurrentUser($this->adminUser);
        $this->sendFrontpageRequest('GET', $path);
    }

    #[Then('the response status code should be :code')]
    public function theResponseStatusCodeShouldBe(int $code): void
    {
        Assert::assertSame($code, $this->response->getStatusCode());
    }

    #[Then('the response should be an empty JSON array')]
    public function theResponseShouldBeAnEmptyJsonArray(): void
    {
        $body = json_decode($this->response->getBody()->getContents(), true);
        Assert::assertSame([], $body);
    }

    #[Then('the response should be an empty JSON object')]
    public function theResponseShouldBeAnEmptyJsonObject(): void
    {
        $this->response->getBody()->rewind();
        $body = json_decode($this->response->getBody()->getContents(), true);
        Assert::assertSame([], $body);
    }

    #[Then('the response should contain field :field')]
    public function theResponseShouldContainField(string $field): void
    {
        $this->response->getBody()->rewind();
        $body = json_decode($this->response->getBody()->getContents(), true);
        Assert::assertIsArray($body);
        Assert::assertArrayHasKey($field, $body, "Response does not contain field \"$field\"");
    }

    #[Then('the response field :field should equal :value')]
    public function theResponseFieldShouldEqual(string $field, string $value): void
    {
        $this->response->getBody()->rewind();
        $body = json_decode($this->response->getBody()->getContents(), true);
        Assert::assertIsArray($body);
        Assert::assertArrayHasKey($field, $body, "Response does not contain field \"$field\"");
        Assert::assertSame($value, $body[$field]);
    }

    #[Then('the response field :field should be empty')]
    public function theResponseFieldShouldBeEmpty(string $field): void
    {
        $this->response->getBody()->rewind();
        $body = json_decode($this->response->getBody()->getContents(), true);
        Assert::assertIsArray($body);
        Assert::assertArrayHasKey($field, $body, "Response does not contain field \"$field\"");
        Assert::assertEmpty($body[$field], "Expected field \"$field\" to be empty");
    }

    #[When('I save valid address settings')]
    public function iSaveValidAddressSettings(): void
    {
        $this->lastAddressSettings = [
            'documentserver'         => getenv('ONLYOFFICE_DOCUMENT_SERVER_URL') ?: 'http://localhost:8080/',
            'documentserverInternal' => getenv('ONLYOFFICE_DOCUMENT_SERVER_INTERNAL_URL') ?: 'http://localhost:8080/',
            'storageUrl'             => getenv('ONLYOFFICE_STORAGE_URL') ?: 'http://localhost/',
            'verifyPeerOff'          => 'false',
            'secret'                 => getenv('ONLYOFFICE_SECRET') ?: 'secret',
            'jwtHeader'              => 'Authorization',
            'demo'                   => 'false',
        ];
        $this->sendFrontpageRequest('PUT', '/apps/onlyoffice/ajax/settings/address', $this->lastAddressSettings);
    }

    #[When('I save invalid address settings')]
    public function iSaveInvalidAddressSettings(): void
    {
        $this->sendFrontpageRequest('PUT', '/apps/onlyoffice/ajax/settings/address', [
            'documentserver'         => 'http://invalid.onlyoffice.example/',
            'documentserverInternal' => '',
            'storageUrl'             => '',
            'verifyPeerOff'          => 'false',
            'secret'                 => '',
            'jwtHeader'              => 'Authorization',
            'demo'                   => 'false',
        ]);
    }

    #[Then('the settings should be saved successfully')]
    public function theSettingsShouldBeSavedSuccessfully(): void
    {
        Assert::assertSame(200, $this->response->getStatusCode());
        $this->response->getBody()->rewind();
        $body = json_decode($this->response->getBody()->getContents(), true);
        Assert::assertEmpty($body['error'] ?? '', 'Settings save returned an error: ' . ($body['error'] ?? ''));
    }

    #[Then('the settings should report a connection error')]
    public function theSettingsShouldReportAConnectionError(): void
    {
        Assert::assertSame(200, $this->response->getStatusCode());
        $this->response->getBody()->rewind();
        $body = json_decode($this->response->getBody()->getContents(), true);
        Assert::assertNotEmpty($body['error'] ?? '', 'Expected a connection error but error field was empty');
    }

    #[Then('the settings should reflect the changes')]
    public function theSettingsShouldReflectTheChanges(): void
    {
        $this->response->getBody()->rewind();
        $body = json_decode($this->response->getBody()->getContents(), true);
        Assert::assertSame($this->lastAddressSettings['documentserver'], $body['documentserver']);
        Assert::assertSame($this->lastAddressSettings['documentserverInternal'], $body['documentserverInternal']);
        Assert::assertSame($this->lastAddressSettings['storageUrl'], $body['storageUrl']);
        Assert::assertSame($this->lastAddressSettings['jwtHeader'], $body['jwtHeader']);
    }

    #[Then('the response should contain all address settings fields')]
    public function theResponseShouldContainAllAddressSettingsFields(): void
    {
        $this->response->getBody()->rewind();
        $body = json_decode($this->response->getBody()->getContents(), true);
        Assert::assertIsArray($body);
        foreach (['documentserver', 'documentserverInternal', 'storageUrl', 'verifyPeerOff', 'secret', 'jwtHeader', 'error', 'version'] as $field) {
            Assert::assertArrayHasKey($field, $body, "Response does not contain field \"$field\"");
        }
    }

    private function deleteFileViaWebDav(string $user, string $path): void
    {
        $client = new Client();
        $auth = $user === $this->adminUser
            ? [$this->adminUser, $this->adminPassword]
            : [$user, self::TEST_PASSWORD];

        try {
            $client->delete("{$this->baseUrl}/remote.php/webdav$path", [
                'auth'    => $auth,
                'headers' => ['OCS-ApiRequest' => 'true'],
            ]);
        } catch (ClientException | ServerException) {
            // best-effort cleanup
        }
    }

    private function enableAdvancedMode(): void
    {
        $previousUser = $this->setCurrentUser($this->adminUser);
        $this->sendFrontpageRequest('PUT', 'apps/onlyoffice/ajax/settings/common', [
            'json' => [
                'defFormats'         => [],
                'editFormats'        => [],
                'restrictExternalStorage' => false,
                'sameTab'            => false,
                'enableSharing'      => false,
                'preview'            => false,
                'advanced'           => true,
                'cronChecker'        => false,
                'emailNotifications' => false,
                'versionHistory'     => false,
                'chat'               => false,
                'compactHeader'      => false,
                'feedback'           => false,
                'forcesave'          => false,
                'liveViewOnShare'    => false,
                'help'               => false,
                'reviewDisplay'      => 'original',
                'theme'              => 'default',
                'unknownAuthor'      => '',
            ],
        ]);
        $this->advancedEnabled = true;
        $this->setCurrentUser($previousUser);
    }

    private function disableAdvancedMode(): void
    {
        $previousUser = $this->setCurrentUser($this->adminUser);
        $this->sendFrontpageRequest('PUT', 'apps/onlyoffice/ajax/settings/common', [
            'json' => [
                'defFormats'         => [],
                'editFormats'        => [],
                'restrictExternalStorage' => false,
                'sameTab'            => false,
                'enableSharing'      => false,
                'preview'            => false,
                'advanced'           => false,
                'cronChecker'        => false,
                'emailNotifications' => false,
                'versionHistory'     => false,
                'chat'               => false,
                'compactHeader'      => false,
                'feedback'           => false,
                'forcesave'          => false,
                'liveViewOnShare'    => false,
                'help'               => false,
                'reviewDisplay'      => 'original',
                'theme'              => 'default',
                'unknownAuthor'      => '',
            ],
        ]);
        $this->advancedEnabled = false;
        $this->setCurrentUser($previousUser);
    }

    private function setCurrentUser(?string $user): ?string {
        $currentUser = $this->currentUser;
        $this->currentUser = $user;
        return $currentUser;
    }

    private function sendFrontpageRequest(string $verb, string $url, array|string|null $body = null, array $headers = []): void {
        $this->sendRequest($verb, "{$this->baseUrl}/index.php/$url", $body, $headers);
    }

    private function sendOcsRequest(string $verb, string $url, array|string|null $body = null, array $headers = []): void {
        $this->sendRequest($verb, "{$this->baseUrl}/ocs/v2.php/$url", $body, $headers);
    }

    private function sendRequest(string $verb, string $fullUrl, array|string|null $body = null, array $headers = []): void {
        $client = new Client();
        $options = [];

        if ($this->currentUser === $this->adminUser) {
            $options['auth'] = [$this->adminUser, $this->adminPassword];
        } elseif ($this->currentUser !== null) {
            $options['auth'] = [$this->currentUser, self::TEST_PASSWORD];
        }

        if (is_array($body) && array_key_exists('multipart', $body)) {
            $options['multipart'] = $body;
        } elseif (is_array($body) && array_key_exists('json', $body)) {
            $options['json'] = $body['json'];
        } elseif (is_array($body)) {
            $options['form_params'] = $body;
        } elseif (is_string($body)) {
            $options['body'] = $body;
        }

        $options['headers'] = [
            'OCS-ApiRequest' => 'true',
            'Accept'         => 'application/json',
            ...$headers,
        ];

        try {
            $this->response = $client->{$verb}($fullUrl, $options);
        } catch (ClientException $e) {
            $this->response = $e->getResponse();
        } catch (ServerException $e) {
            $this->response = $e->getResponse();
        }
    }
}
