<?php
/**
 * Copyright © RequestDesk. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace RequestDesk\Blog\Test\Unit\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RequestDesk\Blog\Model\AmastyCategoryImporter;

class AmastyCategoryImporterTest extends TestCase
{
    /** @var AdapterInterface&MockObject */
    private AdapterInterface $connection;

    /** @var AmastyCategoryImporter */
    private AmastyCategoryImporter $importer;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(AdapterInterface::class);
        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($this->connection);
        $resource->method('getTableName')->willReturnArgument(0);

        $select = $this->createMock(Select::class);
        foreach (['from', 'join', 'joinLeft', 'where', 'order', 'limit'] as $method) {
            $select->method($method)->willReturnSelf();
        }
        $this->connection->method('select')->willReturn($select);

        $this->importer = new AmastyCategoryImporter($resource);
    }

    public function testSourceExistsNeedsBothTables(): void
    {
        $this->connection->method('isTableExists')
            ->willReturnCallback(static fn (string $table): bool => $table === 'amasty_blog_categories');

        $this->assertFalse($this->importer->sourceExists());
    }

    /**
     * A re-run must find what the last run made, not insert a second copy -
     * and must not overwrite edits made since.
     */
    public function testAlreadyImportedCategoryIsReturnedWithoutWriting(): void
    {
        $this->connection->method('fetchOne')->willReturn('42');
        $this->connection->expects($this->never())->method('insert');
        $this->connection->expects($this->never())->method('update');

        $this->assertSame(42, $this->importer->mapCategory(7));
        $this->assertSame([7 => 42], $this->importer->getMapping());
        $this->assertSame(0, $this->importer->getCreatedCount());
    }

    public function testMissingSourceRowMapsToNull(): void
    {
        $this->connection->method('fetchOne')->willReturn(false);
        $this->connection->method('describeTable')->willReturn(['category_id' => [], 'parent_id' => []]);
        $this->connection->method('fetchRow')->willReturn(false);
        $this->connection->expects($this->never())->method('insert');

        $this->assertNull($this->importer->mapCategory(999));
    }

    public function testNonPositiveSourceIdMapsToNull(): void
    {
        $this->connection->expects($this->never())->method('fetchOne');

        $this->assertNull($this->importer->mapCategory(0));
    }

    /**
     * @dataProvider urlKeyProvider
     */
    public function testNormalizeUrlKey(string $urlKey, string $name, string $expected): void
    {
        $this->assertSame($expected, AmastyCategoryImporter::normalizeUrlKey($urlKey, $name, 12));
    }

    public static function urlKeyProvider(): array
    {
        return [
            'source key kept' => ['magento-2', 'Magento 2', 'magento-2'],
            'source key cleaned' => [' Hyva Themes! ', 'x', 'hyva-themes'],
            'falls back to name' => ['', 'Business Tips', 'business-tips'],
            'falls back to id' => ['', '!!!', 'category-12'],
        ];
    }
}
