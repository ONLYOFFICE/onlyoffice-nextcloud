<?php

declare(strict_types=1);

require __DIR__ . '/../../../../../vendor-bin/behat/vendor/autoload.php';

use Behat\Behat\Context\Context;
use Behat\Hook\AfterScenario;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Behat\Hook\BeforeSuite;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use Behat\Gherkin\Node\TableNode;
use PHPUnit\Framework\Assert;
use Psr\Http\Message\ResponseInterface;

class AdminContext implements Context
{
    const TEST_PASSWORD = "password";

    private ResponseInterface $response;
    private ?string $currentUser = null;
    private array $createdUsers = [];
    private array $createdTemplateIds = [];
    private ?int $lastTemplateId = null;
    private array $lastAddressSettings = [];

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
        $this->setCurrentUser($this->adminUser);
        $this->cleanUpUsers();
        $this->cleanUpTemplates();
        $this->restoreValidDocsConnection();
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

    // --- Settings steps ---

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

    #[When('I save common settings')]
    public function iSaveCommonSettings(): void
    {
        $this->sendFrontpageRequest('PUT', '/apps/onlyoffice/ajax/settings/common', [
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
    }

    #[When('I save security settings')]
    public function iSaveSecuritySettings(): void
    {
        $this->sendFrontpageRequest('PUT', '/apps/onlyoffice/ajax/settings/security', [
            'json' => [
                'plugins'    => false,
                'macros'     => false,
                'protection' => 'owner',
                'watermarks' => [
                    'enabled' => false,
                    'text'    => '',
                ],
            ],
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

    #[Then('the common settings should be saved successfully')]
    public function theCommonSettingsShouldBeSavedSuccessfully(): void
    {
        Assert::assertSame(200, $this->response->getStatusCode());
        $this->response->getBody()->rewind();
        $body = json_decode($this->response->getBody()->getContents(), true);
        Assert::assertSame([], $body);
    }

    #[Then('the security settings should be saved successfully')]
    public function theSecuritySettingsShouldBeSavedSuccessfully(): void
    {
        Assert::assertSame(200, $this->response->getStatusCode());
        $this->response->getBody()->rewind();
        $body = json_decode($this->response->getBody()->getContents(), true);
        Assert::assertSame([], $body);
    }

    #[When('I clear the version history')]
    public function iClearTheVersionHistory(): void
    {
        $this->sendFrontpageRequest('DELETE', '/apps/onlyoffice/ajax/settings/history');
    }

    #[Then('the version history should be cleared successfully')]
    public function theVersionHistoryShouldBeClearedSuccessfully(): void
    {
        Assert::assertSame(200, $this->response->getStatusCode());
        $this->response->getBody()->rewind();
        $body = json_decode($this->response->getBody()->getContents(), true);
        Assert::assertSame([], $body);
    }

    // --- Template steps ---

    #[Given('there are no global templates')]
    public function thereAreNoGlobalTemplates(): void
    {
        $this->sendFrontpageRequest('GET', 'apps/onlyoffice/ajax/template');
        $content = $this->response->getBody()->getContents();
        $templates = json_decode($content, true);
        foreach ($templates as $template) {
            $this->sendFrontpageRequest(
                'DELETE',
                'apps/onlyoffice/ajax/template',
                ['templateId' => $template['id']]
            );
        }
    }

    #[When('I upload the template :name as a file')]
    public function iUploadTheTemplateAsAFile(string $name): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'behat_') . '.' . pathinfo($name, PATHINFO_EXTENSION);
        file_put_contents($tmp, '');

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
        if (is_array($body) && isset($body['id'])) {
            $this->lastTemplateId = $body['id'];
            $this->createdTemplateIds[] = $body['id'];
        }
    }

    #[When('I retrieve the template list')]
    public function iRetrieveTheTemplateList(): void
    {
        $this->sendFrontpageRequest('GET', 'apps/onlyoffice/ajax/template');
    }

    #[When('I delete the last uploaded template')]
    public function iDeleteTheLastUploadedTemplate(): void
    {
        Assert::assertNotNull($this->lastTemplateId, 'No template was uploaded in this scenario');
        $this->sendFrontpageRequest(
            'DELETE',
            'apps/onlyoffice/ajax/template',
            ['templateId' => $this->lastTemplateId]
        );
    }

    #[When('I delete a non-existent template')]
    public function iDeleteANonExistentTemplate(): void
    {
        $this->sendFrontpageRequest('DELETE', 'apps/onlyoffice/ajax/template', ['templateId' => 999999]);
    }

    #[Then('the template should be uploaded successfully with its metadata')]
    public function theTemplateShouldBeUploadedSuccessfullyWithItsMetadata(): void
    {
        $this->response->getBody()->rewind();
        $body = json_decode($this->response->getBody()->getContents(), true);
        Assert::assertIsArray($body);
        Assert::assertArrayNotHasKey('error', $body, 'Upload returned an error: ' . ($body['error'] ?? ''));
        foreach (['id', 'name', 'type', 'icon'] as $field) {
            Assert::assertArrayHasKey($field, $body, "Response is missing field \"$field\"");
        }
    }

    #[Then('the upload should be rejected as unsupported')]
    public function theUploadShouldBeRejectedAsUnsupported(): void
    {
        $this->response->getBody()->rewind();
        $body = json_decode($this->response->getBody()->getContents(), true);
        Assert::assertArrayHasKey('error', $body, 'Expected an unsupported-format error but none was returned');
        Assert::assertNotEmpty($body['error']);
    }

    #[Then('the upload should be rejected as a duplicate')]
    public function theUploadShouldBeRejectedAsADuplicate(): void
    {
        $this->response->getBody()->rewind();
        $body = json_decode($this->response->getBody()->getContents(), true);
        Assert::assertArrayHasKey('error', $body, 'Expected a duplicate error but none was returned');
        Assert::assertNotEmpty($body['error']);
    }

    #[Then('the template list should be empty')]
    public function theTemplateListShouldBeEmpty(): void
    {
        $this->response->getBody()->rewind();
        $body = json_decode($this->response->getBody()->getContents(), true);
        Assert::assertSame([], $body);
    }

    #[Then('the response should contain :count templates')]
    public function theResponseShouldContainTemplates(int $count): void
    {
        $this->response->getBody()->rewind();
        $body = json_decode($this->response->getBody()->getContents(), true);
        Assert::assertCount($count, $body);
    }

    #[Then('each template should have the required metadata')]
    public function eachTemplateShouldHaveTheRequiredMetadata(): void
    {
        $this->response->getBody()->rewind();
        $body = json_decode($this->response->getBody()->getContents(), true);
        foreach ($body as $template) {
            foreach (['id', 'name', 'type', 'icon'] as $field) {
                Assert::assertArrayHasKey($field, $template, "Template is missing field \"$field\"");
            }
        }
    }

    #[Then('the response should contain a template with name :name and type :type')]
    public function theResponseShouldContainATemplateWithNameAndType(string $name, string $type): void
    {
        $this->response->getBody()->rewind();
        $body = json_decode($this->response->getBody()->getContents(), true);
        $found = array_filter($body, fn($t) => $t['name'] === $name);
        Assert::assertNotEmpty($found, "No template found with name \"$name\"");
        Assert::assertSame($type, array_values($found)[0]['type']);
    }

    #[Then('the template should be deleted successfully')]
    public function theTemplateShouldBeDeletedSuccessfully(): void
    {
        $this->response->getBody()->rewind();
        $body = json_decode($this->response->getBody()->getContents(), true);
        Assert::assertSame([], $body, 'Expected empty response but got: ' . json_encode($body));
    }

    #[Then('the deletion should be rejected as not found')]
    public function theDeletionShouldBeRejectedAsNotFound(): void
    {
        $this->response->getBody()->rewind();
        $body = json_decode($this->response->getBody()->getContents(), true);
        Assert::assertArrayHasKey('error', $body, 'Expected a not-found error but none was returned');
        Assert::assertNotEmpty($body['error']);
    }

    #[Then('the last uploaded template should no longer exist')]
    public function theLastUploadedTemplateShouldNoLongerExist(): void
    {
        Assert::assertNotNull($this->lastTemplateId);
        $this->sendFrontpageRequest('GET', 'apps/onlyoffice/ajax/template');
        $this->response->getBody()->rewind();
        $body = json_decode($this->response->getBody()->getContents(), true);
        $ids = array_column($body, 'id');
        Assert::assertNotContains($this->lastTemplateId, $ids);
    }

    #[Given('the following global templates exist:')]
    public function theFollowingGlobalTemplatesExist(TableNode $table): void
    {
        foreach ($table->getColumnsHash() as $row) {
            $this->uploadTemplate($row['name']);
        }
    }

    #[Given('a global template :name exists')]
    public function aGlobalTemplateExists(string $name): void
    {
        $this->uploadTemplate($name);
    }

    private function uploadTemplate(string $name): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'behat_') . '.' . pathinfo($name, PATHINFO_EXTENSION);
        file_put_contents($tmp, '');

        $this->sendFrontpageRequest('POST', 'apps/onlyoffice/ajax/template', [
            'multipart' => [
                'name'     => 'file',
                'contents' => fopen($tmp, 'r'),
                'filename' => $name,
            ],
        ]);

        unlink($tmp);

        Assert::assertSame($this->response->getStatusCode(), 200);

        $body = json_decode($this->response->getBody()->getContents(), true);

        Assert::assertArrayHasKey('id', $body);
        $this->createdTemplateIds[] = $body['id'];
    }

    private function setCurrentUser(?string $user): ?string {
        $currentUser = $this->currentUser;
        $this->currentUser = $user;
        return $currentUser;
    }

    private function sendFrontpageRequest(string $verb, string $url, TableNode|array|string|null $body = null, array $headers = [], array $options = []): void {
        $fullUrl = "{$this->baseUrl}/index.php/$url";
        $this->sendRequest($verb, $fullUrl, $body, $headers, $options);
    }

    private function sendOcsRequest(string $verb, string $url, TableNode|array|string|null $body = null, array $headers = [], array $options = []): void {
        $fullUrl = "{$this->baseUrl}/ocs/v2.php/$url";
        $this->sendRequest($verb, $fullUrl, $body, $headers, $options);
    }

    private function sendRequest(string $verb, string $fullUrl, TableNode|array|string|null $body = null, array $headers = [], array $options = []): void {
        $client = new Client();

        if ($this->currentUser === $this->adminUser) {
            $options['auth'] = [$this->adminUser, $this->adminPassword];
        } elseif ($this->currentUser !== null && $this->currentUser !== 'guest') {
            $options['auth'] = [$this->currentUser, self::TEST_PASSWORD];
        }

        if ($body instanceof TableNode) {
            $options['form_params'] = $body->getRowsHash();
        } elseif (is_array($body) && array_key_exists('multipart', $body)) {
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
            'Accept' => 'application/json',
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

    public function cleanUpTemplates(): void
    {
        foreach ($this->createdTemplateIds as $id) {
            $this->sendFrontpageRequest(
                'DELETE',
                'apps/onlyoffice/ajax/template',
                ['templateId' => $id],
            );
        }
        $this->createdTemplateIds = [];
        $this->lastTemplateId = null;
    }

    public function cleanUpUsers(): void
    {
        foreach ($this->createdUsers as $username) {
            $this->sendOcsRequest('DELETE', "/cloud/users/$username");
        }
        $this->createdUsers = [];
    }

    public function restoreValidDocsConnection(): void
    {
        $this->sendFrontpageRequest('PUT', '/apps/onlyoffice/ajax/settings/address', [
            'documentserver'         => getenv('ONLYOFFICE_DOCUMENT_SERVER_URL') ?: 'http://localhost:8080/',
            'documentserverInternal' => getenv('ONLYOFFICE_DOCUMENT_SERVER_INTERNAL_URL') ?: 'http://localhost:8080/',
            'storageUrl'             => getenv('ONLYOFFICE_STORAGE_URL') ?: 'http://localhost/',
            'verifyPeerOff'          => 'false',
            'secret'                 => getenv('ONLYOFFICE_SECRET') ?: 'secret',
            'jwtHeader'              => 'Authorization',
            'demo'                   => 'false',
        ]);
    }
}
