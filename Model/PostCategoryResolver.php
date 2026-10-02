<?php
/**
 * RequestDesk Blog - Post Category Resolver (blog categories)
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\UrlInterface;
use RequestDesk\Blog\Block\ArchiveUrl;

/**
 * Links blog posts to blog categories (requestdesk_blog_category).
 *
 * Until 1.13.0 posts were filed under native catalog categories; that link
 * table (requestdesk_blog_post_category) is no longer read or written.
 *
 * Everything the storefront sees goes through the enabled filter: a disabled
 * category keeps its links, so enabling it again restores it, but it shows on
 * no card, no sidebar and no archive in the meantime.
 */
class PostCategoryResolver
{
    private const LINK_TABLE = 'requestdesk_blog_category_post';
    private const CATEGORY_TABLE = 'requestdesk_blog_category';

    /**
     * @param ResourceConnection $resource
     * @param UrlInterface $urlBuilder
     * @param Config $config
     */
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly UrlInterface $urlBuilder,
        private readonly Config $config
    ) {
    }

    /**
     * Enabled categories assigned to a post, as [id, name, url].
     *
     * @param int $postId
     * @return array<int, array{id:int, name:string, url:string}>
     */
    public function getCategoriesForPost(int $postId): array
    {
        return $this->getCategoriesForPosts([$postId])[$postId] ?? [];
    }

    /**
     * Enabled categories for a whole page of posts in one query.
     *
     * @param int[] $postIds
     * @return array<int, array<int, array{id:int, name:string, url:string}>> post_id => categories
     */
    public function getCategoriesForPosts(array $postIds): array
    {
        $postIds = array_values(array_unique(array_filter(array_map('intval', $postIds))));
        if ($postIds === []) {
            return [];
        }

        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from(['l' => $this->resource->getTableName(self::LINK_TABLE)], ['post_id'])
            ->join(
                ['c' => $this->resource->getTableName(self::CATEGORY_TABLE)],
                'c.category_id = l.category_id',
                ['category_id', 'name', 'url_key']
            )
            ->where('l.post_id IN (?)', $postIds)
            ->where('c.status = ?', Category::STATUS_ENABLED)
            ->order(['c.sort_order ASC', 'c.name ASC']);

        $result = [];
        foreach ($connection->fetchAll($select) as $row) {
            $result[(int) $row['post_id']][] = $this->toCategory($row);
        }
        return $result;
    }

    /**
     * Every enabled category with a published post, with its post count.
     *
     * For the blog sidebar. Largest first, then by name.
     *
     * @return array<int, array{id:int, name:string, url:string, count:int}>
     */
    public function getCategoriesWithPostCounts(): array
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from(['l' => $this->resource->getTableName(self::LINK_TABLE)], [])
            ->join(
                ['c' => $this->resource->getTableName(self::CATEGORY_TABLE)],
                'c.category_id = l.category_id',
                ['category_id', 'name', 'url_key']
            )
            ->join(
                ['post' => $this->resource->getTableName('requestdesk_blog_post')],
                'post.post_id = l.post_id',
                ['count' => new \Zend_Db_Expr('COUNT(DISTINCT l.post_id)')]
            )
            ->where('post.status = ?', 1)
            ->where('c.status = ?', Category::STATUS_ENABLED)
            ->group('c.category_id');

        $result = [];
        foreach ($connection->fetchAll($select) as $row) {
            $result[] = $this->toCategory($row) + ['count' => (int) $row['count']];
        }

        usort(
            $result,
            static fn (array $a, array $b): int => [$b['count'], $a['name']] <=> [$a['count'], $b['name']]
        );

        return $result;
    }

    /**
     * Post ids assigned to a category.
     *
     * @param int $categoryId
     * @return int[]
     */
    public function getPostIdsInCategory(int $categoryId): array
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName(self::LINK_TABLE), ['post_id'])
            ->where('category_id = ?', $categoryId);
        return array_map('intval', $connection->fetchCol($select));
    }

    /**
     * Assign a post to a category (idempotent).
     *
     * An id with no category behind it is ignored rather than tripping the
     * foreign key.
     *
     * @param int $postId
     * @param int $categoryId
     * @return void
     */
    public function attach(int $postId, int $categoryId): void
    {
        if ($this->existingCategoryIds([$categoryId]) === []) {
            return;
        }

        $this->resource->getConnection()->insertOnDuplicate(
            $this->resource->getTableName(self::LINK_TABLE),
            ['post_id' => $postId, 'category_id' => $categoryId],
            ['post_id']
        );
    }

    /**
     * Category ids assigned to a post, enabled or not (the admin form).
     *
     * @param int $postId
     * @return int[]
     */
    public function getCategoryIdsForPost(int $postId): array
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName(self::LINK_TABLE), ['category_id'])
            ->where('post_id = ?', $postId);
        return array_map('intval', $connection->fetchCol($select));
    }

    /**
     * Replace a post's category links with exactly the given set.
     *
     * Ids with no category behind them are dropped. The REST API passes caller
     * input straight through here, and callers that still send the old native
     * catalog ids would otherwise fail the whole publish on the foreign key.
     *
     * @param int $postId
     * @param int[] $categoryIds
     * @return void
     */
    public function syncForPost(int $postId, array $categoryIds): void
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName(self::LINK_TABLE);
        $connection->delete($table, ['post_id = ?' => $postId]);

        $rows = [];
        foreach ($this->existingCategoryIds($categoryIds) as $categoryId) {
            $rows[] = ['post_id' => $postId, 'category_id' => $categoryId];
        }
        if ($rows !== []) {
            $connection->insertMultiple($table, $rows);
        }
    }

    /**
     * The subset of ids that are real categories.
     *
     * @param array $categoryIds array<int|string>
     * @return int[]
     */
    private function existingCategoryIds(array $categoryIds): array
    {
        $categoryIds = array_values(array_unique(array_filter(array_map('intval', $categoryIds))));
        if ($categoryIds === []) {
            return [];
        }

        $connection = $this->resource->getConnection();

        return array_map('intval', $connection->fetchCol(
            $connection->select()
                ->from($this->resource->getTableName(self::CATEGORY_TABLE), ['category_id'])
                ->where('category_id IN (?)', $categoryIds)
        ));
    }

    /**
     * To category
     *
     * @param array $row category_id, name, url_key - array<string, mixed>
     * @return array{id:int, name:string, url:string}
     */
    private function toCategory(array $row): array
    {
        $categoryId = (int) $row['category_id'];

        return [
            'id' => $categoryId,
            'name' => (string) $row['name'],
            'url' => ArchiveUrl::resolve(
                ArchiveUrl::TYPE_CATEGORY,
                $categoryId,
                (string) $row['url_key'],
                $this->urlBuilder,
                $this->config->getUrlPrefix()
            ),
        ];
    }
}
