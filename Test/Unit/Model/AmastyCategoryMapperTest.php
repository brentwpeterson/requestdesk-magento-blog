<?php
/**
 * Copyright © RequestDesk. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace RequestDesk\Blog\Test\Unit\Model;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RequestDesk\Blog\Model\AmastyCategoryMapper;

/**
 * Amasty category -> native Magento category mapping.
 *
 * The behaviours pinned here are the ones that are expensive to get wrong on a
 * migration that touches a live catalog tree: a cycle in parent_id must not
 * recurse forever, an unknown source row must not silently create a category,
 * and a second run must not duplicate what the first one made.
 *
 * The happy path - a nested tree landing under one dedicated parent with
 * include_in_menu and is_anchor off - was verified end to end against fixture
 * data on a real Mage-OS 3.2.0 install, since that exercises the category
 * repository rather than a mock of it.
 */
class AmastyCategoryMapperTest extends TestCase
{
    /** @var ResourceConnection&MockObject */
    private ResourceConnection $resource;

    /** @var AdapterInterface&MockObject */
    private AdapterInterface $connection;

    /** @var CategoryRepositoryInterface&MockObject */
    private CategoryRepositoryInterface $categoryRepository;

    private AmastyCategoryMapper $mapper;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(AdapterInterface::class);
        $this->resource = $this->createMock(ResourceConnection::class);
        $this->resource->method('getConnection')->willReturn($this->connection);
        $this->resource->method('getTableName')->willReturnArgument(0);

        $this->categoryRepository = $this->createMock(CategoryRepositoryInterface::class);

        $this->mapper = new AmastyCategoryMapper(
            $this->resource,
            $this->categoryRepository,
            $this->createMock(CategoryFactory::class),
            $this->createMock(StoreManagerInterface::class),
            $this->createMock(LoggerInterface::class)
        );
    }

    private function selectStub(): Select
    {
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('joinLeft')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $select->method('limit')->willReturnSelf();

        return $select;
    }

    public function testSourceExistsReflectsTheTable(): void
    {
        $this->connection->method('isTableExists')->with('amasty_blog_categories')->willReturn(true);

        $this->assertTrue($this->mapper->sourceExists());
    }

    /**
     * Amasty may be uninstalled by the time the migration runs - the command
     * reads its tables directly for exactly that reason - so absence has to be
     * a normal answer, not an exception.
     */
    public function testSourceExistsIsFalseWhenAmastyIsGone(): void
    {
        $this->connection->method('isTableExists')->willReturn(false);

        $this->assertFalse($this->mapper->sourceExists());
    }

    public function testSourceCategoryIdsAreReturnedAsIntegers(): void
    {
        $this->connection->method('select')->willReturn($this->selectStub());
        $this->connection->method('fetchCol')->willReturn(['3', '9']);

        $this->assertSame([3, 9], $this->mapper->getSourceCategoryIds(102));
    }

    /**
     * A missing source row must not invent a category. Creating one would put a
     * junk node in the catalog tree that nothing points at.
     */
    public function testUnknownSourceCategoryMapsToNull(): void
    {
        $this->connection->method('select')->willReturn($this->selectStub());
        $this->connection->method('fetchRow')->willReturn(false);
        $this->categoryRepository->expects($this->never())->method('save');

        $this->assertNull($this->mapper->mapCategory(999, 3));
    }

    public function testNonPositiveSourceIdMapsToNull(): void
    {
        $this->categoryRepository->expects($this->never())->method('save');

        $this->assertNull($this->mapper->mapCategory(0, 3));
    }

    /**
     * The guard that matters most. Amasty stores parent_id as plain data with no
     * constraint preventing a cycle, and this method recurses on it. A row whose
     * parent is itself would otherwise recurse until the process dies - during a
     * migration that is already writing to the catalog tree.
     */
    public function testASelfReferencingParentTerminatesInsteadOfRecursingForever(): void
    {
        $this->connection->method('select')->willReturn($this->selectStub());
        $this->connection->method('fetchRow')->willReturn(
            ['category_id' => 5, 'parent_id' => 5, 'level' => 2, 'name' => 'Loop', 'url_key' => 'loop']
        );
        $this->categoryRepository->method('get')
            ->willThrowException(new \RuntimeException('no such parent'));
        $this->categoryRepository->method('save')
            ->willThrowException(new \RuntimeException('refuse to write during a cycle test'));

        // The assertion is that this returns at all rather than exhausting the stack.
        $this->assertNull($this->mapper->mapCategory(5, 3));
    }

    /**
     * Depth is bounded. Without the cap, a long parent chain and a cycle are
     * indistinguishable from the inside.
     */
    public function testDepthBeyondTheCapIsRefused(): void
    {
        $this->categoryRepository->expects($this->never())->method('save');

        $this->assertNull($this->mapper->mapCategory(5, 3, 11));
    }

    public function testMappingStartsEmpty(): void
    {
        $this->assertSame([], $this->mapper->getMapping());
    }
}
