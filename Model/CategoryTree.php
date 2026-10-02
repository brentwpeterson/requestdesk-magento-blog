<?php
/**
 * RequestDesk Blog - Category tree
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Model;

use Magento\Framework\App\ResourceConnection;

/**
 * The blog category table as a depth-first list, for dropdowns.
 *
 * The table stores parent_id only - no path or level to fall out of date when a
 * category moves - so the tree is rebuilt here each time. It is a few dozen
 * rows at most, so one query and a walk in PHP is cheaper than keeping a
 * materialised path correct.
 */
class CategoryTree
{
    private const TABLE = 'requestdesk_blog_category';

    /**
     * @param ResourceConnection $resource
     */
    public function __construct(
        private readonly ResourceConnection $resource
    ) {
    }

    /**
     * Every category, parents before children, siblings by sort order then name.
     *
     * @return array<int, array{id:int, name:string, depth:int, status:int}>
     */
    public function getFlatTree(): array
    {
        $connection = $this->resource->getConnection();

        return self::flatten($connection->fetchAll(
            $connection->select()->from(
                $this->resource->getTableName(self::TABLE),
                ['category_id', 'parent_id', 'name', 'status', 'sort_order']
            )
        ));
    }

    /**
     * Order rows depth-first and annotate each with its depth.
     *
     * A row whose parent is missing is treated as top level rather than lost,
     * and a row already placed is never placed again, so a cycle in parent_id
     * cannot loop.
     *
     * @param array $rows category_id, parent_id, name, status, sort_order - array<int, array<string, mixed>>
     * @return array<int, array{id:int, name:string, depth:int, status:int}>
     */
    public static function flatten(array $rows): array // phpcs:ignore Magento2.Functions.StaticFunction
    {
        $byId = [];
        foreach ($rows as $row) {
            $byId[(int) $row['category_id']] = $row;
        }

        $children = [];
        foreach ($byId as $id => $row) {
            $parentId = (int) ($row['parent_id'] ?? 0);
            if ($parentId === $id || !isset($byId[$parentId])) {
                $parentId = 0;
            }
            $children[$parentId][] = $id;
        }

        $sort = static function (int $a, int $b) use ($byId): int {
            return [(int) $byId[$a]['sort_order'], (string) $byId[$a]['name'], $a]
                <=> [(int) $byId[$b]['sort_order'], (string) $byId[$b]['name'], $b];
        };
        foreach ($children as &$ids) {
            usort($ids, $sort);
        }
        unset($ids);

        $result = [];
        $placed = [];
        $walk = static function (int $parentId, int $depth) use (&$walk, &$result, &$placed, $children, $byId): void {
            foreach ($children[$parentId] ?? [] as $id) {
                if (isset($placed[$id])) {
                    continue;
                }
                $placed[$id] = true;
                $result[] = [
                    'id' => $id,
                    'name' => (string) $byId[$id]['name'],
                    'depth' => $depth,
                    'status' => (int) ($byId[$id]['status'] ?? 1),
                ];
                $walk($id, $depth + 1);
            }
        };
        $walk(0, 0);

        // Rows only reachable through a cycle never hang off 0. Still list them.
        foreach (array_keys($byId) as $id) {
            if (!isset($placed[$id])) {
                $placed[$id] = true;
                $result[] = [
                    'id' => $id,
                    'name' => (string) $byId[$id]['name'],
                    'depth' => 0,
                    'status' => (int) ($byId[$id]['status'] ?? 1),
                ];
            }
        }

        return $result;
    }
}
