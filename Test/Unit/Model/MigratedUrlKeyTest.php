<?php
/**
 * Copyright © RequestDesk. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace RequestDesk\Blog\Test\Unit\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\Pdo\Mysql;
use Magento\Framework\DB\Select;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RequestDesk\Blog\Model\AuthorResolver;
use RequestDesk\Blog\Model\BlogCache;
use RequestDesk\Blog\Model\Config;
use RequestDesk\Blog\Model\TagResolver;

/**
 * A migrated author or tag keeps the url_key the old blog published.
 *
 * The first Magento Association rehearsal built keys from names, which turned
 * "Andrea Saccà" into andrea-sacc and "magento 2.4" into magento-2-4. Their
 * pages answered on those addresses and returned 404 on the ones search engines
 * had indexed (andrea-sacca, magento-2.4), twelve authors and two tags in all.
 */
class MigratedUrlKeyTest extends TestCase
{
    /** @var Mysql&MockObject */
    private Mysql $connection;

    /** @var array<string, mixed>|null the row the resolver inserted */
    private ?array $inserted = null;

    private ResourceConnection $resource;

    protected function setUp(): void
    {
        $select = $this->createMock(Select::class);
        foreach (['from', 'where', 'orWhere', 'limit', 'join', 'joinLeft', 'columns'] as $method) {
            $select->method($method)->willReturnSelf();
        }

        $this->connection = $this->createMock(Mysql::class);
        $this->connection->method('select')->willReturn($select);
        // Nothing exists yet: no author or tag with this name, no key collision.
        $this->connection->method('fetchOne')->willReturn('0');
        $this->connection->method('isTableExists')->willReturn(false);
        $this->connection->method('insert')->willReturnCallback(function (string $table, array $row): int {
            $this->inserted = $row;
            return 1;
        });
        $this->connection->method('lastInsertId')->willReturn('9');

        $this->resource = $this->createMock(ResourceConnection::class);
        $this->resource->method('getConnection')->willReturn($this->connection);
        $this->resource->method('getTableName')->willReturnArgument(0);
    }

    private function authors(): AuthorResolver
    {
        return new AuthorResolver(
            $this->resource,
            $this->createMock(UrlInterface::class),
            $this->createMock(StoreManagerInterface::class),
            $this->createMock(Config::class)
        );
    }

    private function tags(): TagResolver
    {
        return new TagResolver(
            $this->resource,
            $this->createMock(UrlInterface::class),
            $this->createMock(Config::class),
            $this->createMock(BlogCache::class)
        );
    }

    public function testAnAuthorKeepsTheKeyTheOldBlogPublished(): void
    {
        $this->authors()->getOrCreateByName('Andrea Saccà', null, null, 'andrea-sacca');

        $this->assertSame('andrea-sacca', $this->inserted['url_key']);
    }

    /**
     * Amasty allowed capitals and underscores in a key. Lowercasing them would
     * move the address, so the key is kept exactly.
     */
    public function testTheKeysSpellingIsKeptExactly(): void
    {
        $this->authors()->getOrCreateByName('Isaac Morey', null, null, '  IsaacMorey ');

        $this->assertSame('IsaacMorey', $this->inserted['url_key']);
    }

    public function testAnAuthorWithoutASourceKeyGetsOneFromTheName(): void
    {
        $this->authors()->getOrCreateByName('Isaac Morey');

        $this->assertSame('isaac-morey', $this->inserted['url_key']);
    }

    public function testABlankSourceKeyFallsBackToTheName(): void
    {
        $this->authors()->getOrCreateByName('Isaac Morey', null, null, '   ');

        $this->assertSame('isaac-morey', $this->inserted['url_key']);
    }

    public function testATagKeepsTheKeyTheOldBlogPublished(): void
    {
        $this->tags()->getOrCreateByName('magento 2.4', 'magento-2.4');

        $this->assertSame('magento-2.4', $this->inserted['url_key']);
    }

    public function testATagWithoutASourceKeyGetsOneFromTheName(): void
    {
        $this->tags()->getOrCreateByName('Open Source');

        $this->assertSame('open-source', $this->inserted['url_key']);
    }
}
