<?php
/**
 * Copyright © RequestDesk. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace RequestDesk\Blog\Test\Unit\Service;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RequestDesk\Blog\Api\Data\PostInterface;
use RequestDesk\Blog\Api\PostRepositoryInterface;
use RequestDesk\Blog\Model\PostCategoryResolver;
use RequestDesk\Blog\Model\PostFactory;
use RequestDesk\Blog\Model\Qa\ContentQaSync;
use RequestDesk\Blog\Model\TagResolver;
use RequestDesk\Blog\Service\PostImportService;

/**
 * RequestDesk's list call returns posts without their body. Up to 1.14.3 the
 * import saved straight from that list, so every imported post got an empty
 * body, and a re-import emptied a post that already had one. RequestDesk also
 * ignores the sync_status filter, so every published post came back each hour.
 * Categories were never read, and tags sent as objects were dropped.
 */
class PostImportServiceTest extends TestCase
{
    private const RD_ID = '6b046454e9d69a17453d70bb';
    private const BODY = '<p>How to choose a Magento support company.</p>';

    /** @var Curl&MockObject */
    private Curl $curl;

    /** @var PostRepositoryInterface&MockObject */
    private PostRepositoryInterface $postRepository;

    /** @var PostFactory&MockObject */
    private PostFactory $postFactory;

    /** @var TagResolver&MockObject */
    private TagResolver $tagResolver;

    /** @var PostCategoryResolver&MockObject */
    private PostCategoryResolver $categoryResolver;

    /** @var string[] URLs fetched, in order */
    private array $fetched = [];

    private PostImportService $service;

    protected function setUp(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnMap([
            ['requestdesk_blog/api/api_key', 'store', null, 'encrypted'],
            ['requestdesk_blog/api/endpoint_url', 'store', null, 'https://rd.test'],
        ]);
        $encryptor = $this->createMock(EncryptorInterface::class);
        $encryptor->method('decrypt')->willReturn('agent-key');

        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn(1);
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $select = $this->createMock(Select::class);
        foreach (['from', 'where', 'orWhere', 'limit'] as $method) {
            $select->method($method)->willReturnSelf();
        }
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchOne')->willReturn(false);
        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);

        $contentQaSync = $this->createMock(ContentQaSync::class);
        $contentQaSync->method('prepare')
            ->willReturn(['status' => ContentQaSync::STATUS_NONE, 'qa_ids' => [], 'pairs' => 0]);
        $contentQaSync->method('finish')->willReturnArgument(1);

        $this->curl = $this->createMock(Curl::class);
        $this->postRepository = $this->createMock(PostRepositoryInterface::class);
        $this->postFactory = $this->createMock(PostFactory::class);
        $this->tagResolver = $this->createMock(TagResolver::class);
        $this->categoryResolver = $this->createMock(PostCategoryResolver::class);

        $this->service = new PostImportService(
            $scopeConfig,
            $encryptor,
            $storeManager,
            $this->curl,
            $this->createMock(LoggerInterface::class),
            $this->postRepository,
            $this->postFactory,
            $resource,
            $this->tagResolver,
            $contentQaSync,
            $this->categoryResolver
        );
    }

    public function testNewPostIsSavedWithTheBodyFromTheSinglePostCall(): void
    {
        $this->respond($this->listed(), $this->full(self::BODY));
        $post = $this->newPost();
        $post->expects($this->once())->method('setContent')->with(self::BODY);
        $this->postRepository->expects($this->once())->method('save')->willReturn($post);

        $result = $this->service->importPosts();

        $this->assertSame(1, $result['imported']);
        $this->assertSame(0, $result['failed']);
        $this->assertSame('https://rd.test/api/public-posts/how-to-choose', $this->fetched[1]);
    }

    public function testPostWithNoBodyIsNeverSaved(): void
    {
        $this->respond($this->listed(), $this->full(''));
        $this->newPost();
        $this->postRepository->expects($this->never())->method('save');

        $result = $this->service->importPosts();

        $this->assertSame(1, $result['failed']);
        $this->assertStringContainsString('no body', $result['errors'][0]);
    }

    public function testFailedBodyFetchLeavesTheExistingPostAlone(): void
    {
        $this->respond($this->listed(), null);
        $this->existingPost('2026-11-01 00:00:00', self::BODY);
        $this->postRepository->expects($this->never())->method('save');

        $result = $this->service->importPosts();

        $this->assertSame(1, $result['failed']);
    }

    public function testPostUnchangedSinceItsLastImportIsSkipped(): void
    {
        $this->respond($this->listed('2026-11-25T19:00:00'));
        $this->existingPost('2026-11-25 19:47:54', self::BODY);
        $this->postRepository->expects($this->never())->method('save');

        $result = $this->service->importPosts();

        $this->assertSame(1, $result['unchanged']);
        $this->assertCount(1, $this->fetched, 'The body of an unchanged post is not fetched.');
    }

    public function testPostChangedInRequestDeskIsUpdated(): void
    {
        $this->respond($this->listed('2026-11-26T09:00:00'), $this->full(self::BODY));
        $post = $this->existingPost('2026-11-25 19:47:54', self::BODY);
        $post->expects($this->once())->method('setContent')->with(self::BODY);
        $this->postRepository->expects($this->once())->method('save')->willReturn($post);

        $this->assertSame(1, $this->service->importPosts()['updated']);
    }

    public function testPostWithAnEmptyStoredBodyIsRepairedEvenWhenUnchanged(): void
    {
        $this->respond($this->listed('2026-11-25T19:00:00'), $this->full(self::BODY));
        $post = $this->existingPost('2026-11-25 19:47:54', '');
        $post->expects($this->once())->method('setContent')->with(self::BODY);
        $this->postRepository->expects($this->once())->method('save')->willReturn($post);

        $this->assertSame(1, $this->service->importPosts()['updated']);
    }

    public function testCategoryAndTagObjectsAreLinked(): void
    {
        $this->respond($this->listed(), $this->full(self::BODY, [
            'categories' => [['id' => 'c1', 'name' => 'Magento Support', 'slug' => 'magento-support']],
            'tags' => [['id' => 't1', 'name' => 'Patching', 'slug' => 'patching'], 'Adobe Commerce'],
        ]));
        $this->postRepository->method('save')->willReturn($this->newPost());

        $this->categoryResolver->expects($this->once())->method('getOrCreateByName')
            ->with('Magento Support', 'magento-support')->willReturn(7);
        $this->categoryResolver->expects($this->once())->method('syncForPost')->with(15, [7]);
        $this->tagResolver->method('getOrCreateByName')->willReturnMap([
            ['Patching', 'patching', 3],
            ['Adobe Commerce', null, 4],
        ]);
        $this->tagResolver->expects($this->once())->method('syncForPost')->with(15, [3, 4]);

        $this->service->importPosts();
    }

    public function testSlugThatIsNotAUrlKeyIsDropped(): void
    {
        $this->respond($this->listed(), $this->full(self::BODY, [
            'categories' => [['name' => 'Case Studies', 'slug' => 'case studies']],
        ]));
        $this->postRepository->method('save')->willReturn($this->newPost());

        $this->categoryResolver->expects($this->once())->method('getOrCreateByName')
            ->with('Case Studies', null)->willReturn(9);

        $this->service->importPosts();
    }

    public function testPostWithNoCategoriesKeepsItsAdminCategories(): void
    {
        $this->respond($this->listed(), $this->full(self::BODY, ['categories' => [], 'tags' => []]));
        $this->postRepository->method('save')->willReturn($this->newPost());

        $this->categoryResolver->expects($this->never())->method('syncForPost');
        $this->tagResolver->expects($this->never())->method('syncForPost');

        $this->service->importPosts();
    }

    /**
     * Queue the list response, then the single-post response (null = HTTP 500).
     *
     * @param array $list
     * @param array|null $full
     * @return void
     */
    private function respond(array $list, ?array $full = null): void
    {
        $responses = [[200, $list]];
        if (func_num_args() > 1) {
            $responses[] = $full === null ? [500, ['detail' => 'boom']] : [200, $full];
        }

        $this->curl->method('get')->willReturnCallback(function (string $url): void {
            $this->fetched[] = $url;
        });
        $this->curl->method('getStatus')->willReturnCallback(
            fn () => $responses[count($this->fetched) - 1][0]
        );
        $this->curl->method('getBody')->willReturnCallback(
            fn () => json_encode($responses[count($this->fetched) - 1][1])
        );
    }

    private function listed(string $modifiedAt = '2026-11-25T19:00:00'): array
    {
        return [
            'posts' => [[
                'id' => self::RD_ID,
                'title' => 'How to Choose a Magento Support Company',
                'slug' => 'how-to-choose',
                'status' => 'publish',
                'modified_at' => $modifiedAt,
                'categories' => [],
                'tags' => [],
            ]],
            'total' => 1,
            'has_more' => false,
        ];
    }

    private function full(string $content, array $extra = []): array
    {
        return ['post' => array_merge($this->listed()['posts'][0], ['content' => $content], $extra)];
    }

    /**
     * @return PostInterface&MockObject
     */
    private function newPost(): PostInterface
    {
        $post = $this->createMock(PostInterface::class);
        $post->method('getPostId')->willReturn(15);
        $this->postRepository->method('getByRequestdeskPostId')
            ->willThrowException(new NoSuchEntityException(__('missing')));
        $this->postFactory->method('create')->willReturn($post);
        return $post;
    }

    /**
     * @param string $lastSync
     * @param string $content
     * @return PostInterface&MockObject
     */
    private function existingPost(string $lastSync, string $content): PostInterface
    {
        $post = $this->createMock(PostInterface::class);
        $post->method('getPostId')->willReturn(15);
        $post->method('getContent')->willReturn($content);
        $post->method('getRequestdeskLastSync')->willReturn($lastSync);
        $this->postRepository->method('getByRequestdeskPostId')->with(self::RD_ID)->willReturn($post);
        $this->postFactory->expects($this->never())->method('create');
        return $post;
    }
}
