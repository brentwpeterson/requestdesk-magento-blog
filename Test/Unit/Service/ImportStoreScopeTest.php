<?php
/**
 * Copyright © RequestDesk. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace RequestDesk\Blog\Test\Unit\Service;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\Pdo\Mysql;
use Magento\Framework\DB\Select;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\Website;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RequestDesk\Blog\Api\PostRepositoryInterface;
use RequestDesk\Blog\Model\Post;
use RequestDesk\Blog\Model\PostFactory;
use RequestDesk\Blog\Model\TagResolver;
use RequestDesk\Blog\Service\PostImportService;

/**
 * Imported posts land on one website's store view, never on store 0.
 *
 * Before 1.15.0 the import read one API key for the whole install and set
 * store_id from the current store. Cron runs in admin scope, so every post went
 * to store 0 (all store views). With Evrig and CommerceKing on one install that
 * put each website's posts on the other's blog.
 */
class ImportStoreScopeTest extends TestCase
{
    private const EVRIG = 1;
    private const CK = 2;

    /** @var array<string, mixed> config values keyed "path|websiteId" */
    private array $config = [];

    /** @var array<int, Website> */
    private array $websites = [];

    /** @var array<int, Store> */
    private array $stores = [];

    /** @var array<string, Post> existing posts keyed by RequestDesk id */
    private array $existing = [];

    /** @var array<string, int> store_id each saved post ended on, keyed by RequestDesk id */
    private array $saved = [];

    /** @var string[] API keys the import sent, in order */
    private array $keysSent = [];

    private string $responseBody = '';

    protected function setUp(): void
    {
        $this->addWebsite(self::EVRIG, 'base', 1, 'default');
        $this->addWebsite(self::CK, 'commerceking', 3, 'ck_en');
        $this->addStore(4, 'ck_es', self::CK);

        $this->responseBody = (string) json_encode([
            'total' => 1,
            'posts' => [['id' => 'rd-1', 'title' => 'Hello', 'slug' => 'hello', 'status' => 'publish']],
        ]);
    }

    private function addWebsite(int $id, string $code, int $defaultStoreId, string $storeCode): void
    {
        $store = $this->addStore($defaultStoreId, $storeCode, $id);
        $website = $this->createMock(Website::class);
        $website->method('getId')->willReturn($id);
        $website->method('getCode')->willReturn($code);
        $website->method('getDefaultStore')->willReturn($store);
        $this->websites[$id] = $website;
    }

    private function addStore(int $id, string $code, int $websiteId): Store
    {
        $store = $this->createMock(Store::class);
        $store->method('getId')->willReturn($id);
        $store->method('getCode')->willReturn($code);
        $store->method('getWebsiteId')->willReturn($websiteId);
        $this->stores[$id] = $store;
        return $store;
    }

    private function service(): PostImportService
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(
            function (string $path, string $scope = 'default', $scopeCode = null) {
                $this->assertSame(ScopeInterface::SCOPE_WEBSITES, $scope, $path . ' must be read at website scope');
                return $this->config[$path . '|' . $scopeCode] ?? null;
            }
        );
        $scopeConfig->method('isSetFlag')->willReturnCallback(
            fn (string $path, string $scope = 'default', $scopeCode = null): bool
                => (bool) ($this->config[$path . '|' . $scopeCode] ?? false)
        );

        $encryptor = $this->createMock(EncryptorInterface::class);
        $encryptor->method('decrypt')->willReturnArgument(0);

        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getWebsites')->willReturnCallback(fn () => array_values($this->websites));
        $storeManager->method('getWebsite')->willReturnCallback(fn ($id) => $this->websites[(int) $id]);
        $storeManager->method('getStore')->willReturnCallback(fn ($id) => $this->stores[(int) $id]);
        $storeManager->method('getDefaultStoreView')->willReturnCallback(fn () => $this->stores[1]);

        $curl = $this->createMock(Curl::class);
        $curl->method('setHeaders')->willReturnCallback(function (array $headers): void {
            $this->keysSent[] = $headers['X-API-Key'];
        });
        $curl->method('getStatus')->willReturn(200);
        $curl->method('getBody')->willReturnCallback(fn () => $this->responseBody);

        $repository = $this->createMock(PostRepositoryInterface::class);
        $repository->method('getByRequestdeskPostId')->willReturnCallback(function (string $id) {
            if (!isset($this->existing[$id])) {
                throw new NoSuchEntityException(__('none'));
            }
            return $this->existing[$id];
        });
        $repository->method('save')->willReturnCallback(function (Post $post) {
            $this->saved[$post->getRequestdeskPostId()] = $post->getStoreId();
            return $post;
        });

        $factory = $this->createMock(PostFactory::class);
        $factory->method('create')->willReturnCallback(fn () => $this->post(null));

        $select = $this->createMock(Select::class);
        foreach (['from', 'where', 'orWhere', 'limit'] as $method) {
            $select->method($method)->willReturnSelf();
        }
        $connection = $this->createMock(Mysql::class);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchOne')->willReturn('0');
        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);

        return new PostImportService(
            $scopeConfig,
            $encryptor,
            $storeManager,
            $curl,
            $this->createMock(LoggerInterface::class),
            $repository,
            $factory,
            $resource,
            $this->createMock(TagResolver::class)
        );
    }

    /**
     * A post that remembers the store_id and RequestDesk id set on it.
     */
    private function post(?int $storeId): Post
    {
        $data = ['store_id' => $storeId ?? 0, 'requestdesk_post_id' => null];
        $post = $this->getMockBuilder(Post::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getStoreId', 'setStoreId', 'getRequestdeskPostId', 'setRequestdeskPostId', 'getPostId'])
            ->getMock();
        $post->method('getStoreId')->willReturnCallback(function () use (&$data) {
            return $data['store_id'];
        });
        $post->method('setStoreId')->willReturnCallback(function (int $id) use (&$data, &$post) {
            $data['store_id'] = $id;
            return $post;
        });
        $post->method('getRequestdeskPostId')->willReturnCallback(function () use (&$data) {
            return $data['requestdesk_post_id'];
        });
        $post->method('setRequestdeskPostId')->willReturnCallback(function ($id) use (&$data, &$post) {
            $data['requestdesk_post_id'] = $id;
            return $post;
        });
        $post->method('getPostId')->willReturn(7);
        return $post;
    }

    private function setKey(int $websiteId, string $key): void
    {
        $this->config['requestdesk_blog/api/api_key|' . $websiteId] = $key;
        $this->config['requestdesk_blog/api/endpoint_url|' . $websiteId] = 'https://app.requestdesk.ai';
    }

    public function testTargetIsTheWebsiteDefaultStoreView(): void
    {
        $this->assertSame(3, $this->service()->resolveImportStoreId(self::CK));
    }

    public function testConfiguredStoreViewOfTheSameWebsiteIsUsed(): void
    {
        $this->config['requestdesk_blog/api/import_store_id|' . self::CK] = '4';
        $this->assertSame(4, $this->service()->resolveImportStoreId(self::CK));
    }

    public function testConfiguredStoreViewOfAnotherWebsiteIsRefused(): void
    {
        $this->config['requestdesk_blog/api/import_store_id|' . self::CK] = '1';
        $this->expectException(LocalizedException::class);
        $this->service()->resolveImportStoreId(self::CK);
    }

    public function testWebsiteWithoutDefaultStoreViewIsRefused(): void
    {
        $website = $this->createMock(Website::class);
        $website->method('getCode')->willReturn('empty');
        $website->method('getDefaultStore')->willReturn(null);
        $this->websites[9] = $website;

        $this->expectException(LocalizedException::class);
        $this->service()->resolveImportStoreId(9);
    }

    public function testNewPostLandsOnTheTargetStoreViewNotStoreZero(): void
    {
        $this->setKey(self::CK, 'ck-key');

        $result = $this->service()->importPosts('publish', null, 1, 20, self::CK);

        $this->assertTrue($result['success']);
        $this->assertSame(['rd-1' => 3], $this->saved);
        $this->assertSame(['ck-key'], $this->keysSent);
    }

    public function testPostMovedByHandStaysOnItsStoreView(): void
    {
        $this->setKey(self::CK, 'ck-key');
        $this->existing['rd-1'] = $this->post(4);
        $this->existing['rd-1']->setRequestdeskPostId('rd-1');

        $this->service()->importPosts('publish', null, 1, 20, self::CK);

        $this->assertSame(['rd-1' => 4], $this->saved);
    }

    public function testPostOnStoreZeroIsMovedToTheTargetStoreView(): void
    {
        $this->setKey(self::CK, 'ck-key');
        $this->existing['rd-1'] = $this->post(0);
        $this->existing['rd-1']->setRequestdeskPostId('rd-1');

        $this->service()->importPosts('publish', null, 1, 20, self::CK);

        $this->assertSame(['rd-1' => 3], $this->saved);
    }

    public function testEachWebsiteImportsWithItsOwnKey(): void
    {
        $this->setKey(self::EVRIG, 'evrig-key');
        $this->setKey(self::CK, 'ck-key');

        $result = $this->service()->importForAllWebsites();

        $this->assertTrue($result['success']);
        $this->assertSame(['evrig-key', 'ck-key'], $this->keysSent);
        $this->assertSame(['base', 'commerceking'], array_keys($result['websites']));
    }

    public function testWebsiteSharingAnotherWebsitesKeyIsSkippedAndNamed(): void
    {
        $this->setKey(self::EVRIG, 'shared-key');
        $this->setKey(self::CK, 'shared-key');

        $result = $this->service()->importForAllWebsites();

        $this->assertSame(['shared-key'], $this->keysSent);
        $this->assertSame(['base'], array_keys($result['websites']));
        $this->assertStringContainsString('commerceking skipped', $result['errors'][0]);
    }

    public function testCronSkipsWebsitesWithAutomatedImportOff(): void
    {
        $this->setKey(self::EVRIG, 'evrig-key');
        $this->setKey(self::CK, 'ck-key');
        $this->config['requestdesk_blog/cron/enabled|' . self::CK] = '1';

        $result = $this->service()->importForAllWebsites('publish', 'not_synced', 1, 50, true);

        $this->assertSame(['ck-key'], $this->keysSent);
        $this->assertSame(['commerceking'], array_keys($result['websites']));
    }

    public function testCronWithAutomatedImportOffEverywhereReportsDisabled(): void
    {
        $this->setKey(self::EVRIG, 'evrig-key');

        $result = $this->service()->importForAllWebsites('publish', 'not_synced', 1, 50, true);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['disabled']);
        $this->assertSame([], $this->keysSent);
    }
}
