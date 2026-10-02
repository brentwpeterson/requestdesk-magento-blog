<?php
/**
 * Copyright © RequestDesk. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace RequestDesk\Blog\Test\Unit\Model;

use PHPUnit\Framework\TestCase;
use RequestDesk\Blog\Model\CategoryTree;

/**
 * The tree is rebuilt from parent_id alone, so the ordering, the depth and the
 * handling of bad parent_ids are all this one function's job.
 */
class CategoryTreeTest extends TestCase
{
    private static function row(int $id, int $parentId, string $name, int $sortOrder = 0, int $status = 1): array
    {
        return [
            'category_id' => $id,
            'parent_id' => $parentId,
            'name' => $name,
            'sort_order' => $sortOrder,
            'status' => $status,
        ];
    }

    public function testChildrenFollowTheirParentWithDepth(): void
    {
        $flat = CategoryTree::flatten([
            self::row(3, 1, 'Hyva'),
            self::row(1, 0, 'Magento'),
            self::row(2, 0, 'Business'),
            self::row(4, 3, 'Themes'),
        ]);

        $this->assertSame(
            [['Business', 0], ['Magento', 0], ['Hyva', 1], ['Themes', 2]],
            array_map(static fn (array $c): array => [$c['name'], $c['depth']], $flat)
        );
    }

    public function testSortOrderBeatsName(): void
    {
        $flat = CategoryTree::flatten([
            self::row(1, 0, 'Alpha', 20),
            self::row(2, 0, 'Zulu', 10),
        ]);

        $this->assertSame(['Zulu', 'Alpha'], array_column($flat, 'name'));
    }

    /**
     * parent_id has no FK. A row whose parent is gone must still be listed,
     * or a post filed under it would lose the category on its next save.
     */
    public function testOrphanIsListedAtTopLevel(): void
    {
        $flat = CategoryTree::flatten([self::row(5, 99, 'Orphan')]);

        $this->assertSame([['id' => 5, 'name' => 'Orphan', 'depth' => 0, 'status' => 1]], $flat);
    }

    public function testCycleNeitherLoopsNorDropsRows(): void
    {
        $flat = CategoryTree::flatten([
            self::row(1, 2, 'A'),
            self::row(2, 1, 'B'),
            self::row(3, 3, 'Self'),
        ]);

        $ids = array_column($flat, 'id');
        sort($ids);
        $this->assertSame([1, 2, 3], $ids);
    }

    public function testStatusIsCarried(): void
    {
        $flat = CategoryTree::flatten([self::row(1, 0, 'Off', 0, 0)]);

        $this->assertSame(0, $flat[0]['status']);
    }
}
