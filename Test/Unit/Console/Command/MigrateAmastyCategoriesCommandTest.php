<?php
/**
 * Copyright © RequestDesk. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace RequestDesk\Blog\Test\Unit\Console\Command;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RequestDesk\Blog\Console\Command\MigrateAmastyCategoriesCommand;
use RequestDesk\Blog\Model\AmastyCategoryImporter;
use RequestDesk\Blog\Model\PostCategoryResolver;
use Symfony\Component\Console\Tester\CommandTester;

class MigrateAmastyCategoriesCommandTest extends TestCase
{
    /** @var AmastyCategoryImporter&MockObject */
    private AmastyCategoryImporter $importer;

    /** @var PostCategoryResolver&MockObject */
    private PostCategoryResolver $postCategoryResolver;

    /** @var AdapterInterface&MockObject */
    private AdapterInterface $connection;

    /** @var CommandTester */
    private CommandTester $tester;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(AdapterInterface::class);
        $select = $this->createMock(Select::class);
        foreach (['from', 'where', 'limit'] as $method) {
            $select->method($method)->willReturnSelf();
        }
        $this->connection->method('select')->willReturn($select);

        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($this->connection);
        $resource->method('getTableName')->willReturnArgument(0);

        $this->importer = $this->createMock(AmastyCategoryImporter::class);
        $this->postCategoryResolver = $this->createMock(PostCategoryResolver::class);

        $this->tester = new CommandTester(
            new MigrateAmastyCategoriesCommand($resource, $this->importer, $this->postCategoryResolver)
        );
    }

    public function testNoAmastyTablesFails(): void
    {
        $this->importer->method('sourceExists')->willReturn(false);
        $this->importer->expects($this->never())->method('mapCategory');

        $this->assertSame(1, $this->tester->execute([]));
        $this->assertStringContainsString('nothing to migrate', $this->tester->getDisplay());
    }

    public function testImportsCategoriesAndLinksMigratedPosts(): void
    {
        $this->importer->method('sourceExists')->willReturn(true);
        $this->importer->method('getAllSourceCategoryIds')->willReturn([3, 4]);
        $this->importer->expects($this->exactly(2))->method('mapCategory')
            ->willReturnCallback(static fn (int $id): int => $id * 10);
        $this->importer->method('getMapping')->willReturn([3 => 30, 4 => 40]);
        $this->importer->method('getSourcePostLinks')->willReturn([
            ['url_key' => 'known-post', 'category_id' => 4],
            ['url_key' => 'not-migrated', 'category_id' => 3],
        ]);
        $this->connection->method('fetchOne')->willReturnOnConsecutiveCalls('101', false);

        $this->postCategoryResolver->expects($this->once())->method('attach')->with(101, 40);

        $this->assertSame(0, $this->tester->execute([]));
        $this->assertStringContainsString('posts not found: 1', $this->tester->getDisplay());
    }

    public function testDryRunWritesNothing(): void
    {
        $this->importer->method('sourceExists')->willReturn(true);
        $this->importer->method('getAllSourceCategoryIds')->willReturn([3]);
        $this->importer->method('getSourcePostLinks')->willReturn([['url_key' => 'p', 'category_id' => 3]]);
        $this->connection->method('fetchOne')->willReturn('101');

        $this->importer->expects($this->never())->method('mapCategory');
        $this->postCategoryResolver->expects($this->never())->method('attach');

        $this->assertSame(0, $this->tester->execute(['--dry-run' => true]));
        $this->assertStringContainsString('DRY RUN', $this->tester->getDisplay());
    }
}
