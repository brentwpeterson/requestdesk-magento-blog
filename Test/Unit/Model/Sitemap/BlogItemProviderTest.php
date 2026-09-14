<?php
/**
 * Copyright © RequestDesk. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace RequestDesk\Blog\Test\Unit\Model\Sitemap;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Sitemap\Model\SitemapItem;
use Magento\Sitemap\Model\SitemapItemInterfaceFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RequestDesk\Blog\Model\Sitemap\BlogConfigReader;
use RequestDesk\Blog\Model\Sitemap\BlogItemProvider;

/**
 * A store moving off Amasty Blog got its blog sitemap entries from the
 * amasty/blog-sitemap add-on, so switching Amasty off would have dropped the
 * whole blog out of the sitemap. These tests pin which URLs the provider emits
 * and in what form, since a wrong form in a sitemap is invisible until search
 * traffic drops.
 */
class BlogItemProviderTest extends TestCase
{
    /** @var AdapterInterface&MockObject */
    private AdapterInterface $connection;

    /** @var BlogConfigReader&MockObject */
    private BlogConfigReader $configReader;

    /** @var ScopeConfigInterface&MockObject */
    private ScopeConfigInterface $scopeConfig;

    private BlogItemProvider $provider;

    protected function setUp(): void
    {
        $select = $this->createMock(Select::class);
        foreach (['from', 'where', 'order', 'join', 'joinLeft', 'group'] as $method) {
            $select->method($method)->willReturnSelf();
        }

        $this->connection = $this->createMock(AdapterInterface::class);
        $this->connection->method('select')->willReturn($select);
        $this->connection->method('describeTable')->willReturn([]);

        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($this->connection);
        $resource->method('getTableName')->willReturnArgument(0);

        $itemFactory = $this->createMock(SitemapItemInterfaceFactory::class);
        $itemFactory->method('create')->willReturnCallback(
            static fn (array $data) => new SitemapItem(
                $data['url'],
                $data['priority'],
                $data['changeFrequency'],
                $data['updatedAt']
            )
        );

        $this->configReader = $this->createMock(BlogConfigReader::class);
        $this->configReader->method('getPriority')->willReturn('0.5');
        $this->configReader->method('getChangeFrequency')->willReturn('weekly');

        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);

        $this->provider = new BlogItemProvider($resource, $itemFactory, $this->configReader, $this->scopeConfig);
    }

    /**
     * @return array<string, string|null> url => lastmod
     */
    private function urls(): array
    {
        $urls = [];
        foreach ($this->provider->getItems(1) as $item) {
            $urls[$item->getUrl()] = $item->getUpdatedAt();
        }

        return $urls;
    }

    public function testNothingIsEmittedWhenBlogSitemapIsSwitchedOff(): void
    {
        $this->configReader->method('isEnabled')->willReturn(false);
        $this->scopeConfig->method('isSetFlag')->willReturn(true);
        $this->connection->expects($this->never())->method('fetchAll');

        $this->assertSame([], $this->provider->getItems(1));
    }

    public function testNothingIsEmittedWhenTheBlogItselfIsDisabled(): void
    {
        $this->configReader->method('isEnabled')->willReturn(true);
        $this->scopeConfig->method('isSetFlag')->willReturn(false);
        $this->connection->expects($this->never())->method('fetchAll');

        $this->assertSame([], $this->provider->getItems(1));
    }

    /**
     * No published posts means no /blog entry either: the listing would be an
     * empty page.
     */
    public function testNoPublishedPostsMeansNoBlogEntries(): void
    {
        $this->configReader->method('isEnabled')->willReturn(true);
        $this->scopeConfig->method('isSetFlag')->willReturn(true);
        $this->connection->expects($this->once())->method('fetchAll')->willReturn([]);

        $this->assertSame([], $this->provider->getItems(1));
    }

    public function testPostsAndArchivesAreEmittedInTheRoutedForms(): void
    {
        $this->configReader->method('isEnabled')->willReturn(true);
        $this->scopeConfig->method('isSetFlag')->willReturn(true);
        $this->connection->method('fetchAll')->willReturnOnConsecutiveCalls(
            // posts
            [
                ['post_id' => '1', 'url_key' => 'mage-os-version-update', 'updated_at' => '2026-01-10 00:00:00'],
                ['post_id' => '2', 'url_key' => '', 'updated_at' => '2026-03-27 12:00:00'],
            ],
            // categories: 90 shares a key with 76, 91 has no key
            [
                ['id' => '76', 'url_key' => 'magento-2', 'updated_at' => '2026-01-10 00:00:00'],
                ['id' => '90', 'url_key' => 'magento-2', 'updated_at' => '2026-02-01 00:00:00'],
                ['id' => '91', 'url_key' => null, 'updated_at' => '2026-02-02 00:00:00'],
            ],
            // tags
            [['id' => '42', 'url_key' => 'magento-development-company', 'updated_at' => '2026-03-27 12:00:00']],
            // authors
            [['id' => '10', 'url_key' => 'kapil-nandani', 'updated_at' => '2026-03-27 12:00:00']]
        );

        $this->assertSame(
            [
                'blog' => '2026-03-27 12:00:00',
                'blog/mage-os-version-update' => '2026-01-10 00:00:00',
                'blog/post/view/id/2' => '2026-03-27 12:00:00',
                'blog/category/magento-2' => '2026-01-10 00:00:00',
                'blog/category/view/id/90' => '2026-02-01 00:00:00',
                'blog/category/view/id/91' => '2026-02-02 00:00:00',
                'blog/tag/magento-development-company' => '2026-03-27 12:00:00',
                'blog/author/kapil-nandani' => '2026-03-27 12:00:00',
            ],
            $this->urls()
        );
    }
}
