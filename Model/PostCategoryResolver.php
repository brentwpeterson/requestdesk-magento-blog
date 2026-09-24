<?php
/**
 * RequestDesk Blog - Post Category Resolver (native Magento categories)
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Model;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\UrlInterface;
use RequestDesk\Blog\Block\ArchiveUrl;
use Psr\Log\LoggerInterface;

/**
 * Links blog posts to NATIVE Magento catalog categories (catalog_category_entity)
 * instead of an invented blog taxonomy. Reads category name/URL from the real
 * category tree so the blog reuses whatever categories the store already has.
 */
class PostCategoryResolver
{
    private const LINK_TABLE = 'requestdesk_blog_post_category';

    /**
     * @param ResourceConnection $resource
     * @param CategoryRepositoryInterface $categoryRepository
     * @param LoggerInterface $logger
     * @param Config $config
     */
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly LoggerInterface $logger,
        private readonly UrlInterface $urlBuilder,
        private readonly Config $config
    ) {
    }

    /**
     * Native categories assigned to a post, as [id, name, url].
     *
     * @param int $postId
     * @return array<int, array{id:int, name:string, url:string}>
     */
    public function getCategoriesForPost(int $postId): array
    {
        return $this->getCategoriesForPosts([$postId])[$postId] ?? [];
    }

    /**
     * Native categories for a whole page of posts in one pass.
     *
     * The listing calls this for every card it renders; one link-table query
     * and one load per unique category beats a round trip per post.
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
            ->from($this->resource->getTableName(self::LINK_TABLE), ['post_id', 'category_id'])
            ->where('post_id IN (?)', $postIds);

        $categoryIdsByPost = [];
        foreach ($connection->fetchAll($select) as $row) {
            $categoryIdsByPost[(int) $row['post_id']][] = (int) $row['category_id'];
        }

        $categoriesById = $this->loadCategories(
            array_unique(array_merge(...array_values($categoryIdsByPost ?: [[]])))
        );

        $result = [];
        foreach ($categoryIdsByPost as $postId => $categoryIds) {
            foreach ($categoryIds as $categoryId) {
                if (isset($categoriesById[$categoryId])) {
                    $result[$postId][] = $categoriesById[$categoryId];
                }
            }
        }
        return $result;
    }

    /**
     * Load the category records behind the link rows, skipping ids that no
     * longer exist in the catalog.
     *
     * @param int[] $categoryIds
     * @return array<int, array{id:int, name:string, url:string}>
     */
    private function loadCategories(array $categoryIds): array
    {
        $categories = [];
        foreach ($categoryIds as $categoryId) {
            try {
                $category = $this->categoryRepository->get($categoryId);
                $categories[$categoryId] = [
                    'id' => $categoryId,
                    'name' => (string) $category->getName(),
                    'url' => ArchiveUrl::resolve(
                        ArchiveUrl::TYPE_CATEGORY,
                        $categoryId,
                        $category->getUrlKey(),
                        $this->urlBuilder,
                        $this->config->getUrlPrefix()
                    ),
                ];
            } catch (\Throwable $e) {
                // category removed from the catalog — skip it
                $this->logger->debug('RequestDesk Blog: linked category missing', [
                    'category_id' => $categoryId,
                ]);
            }
        }
        return $categories;
    }

    /**
     * Every category with at least one published post, with that count, for
     * the blog sidebar. Largest first, then by name.
     *
     * Counts published posts only, so the number next to a category is the
     * number of posts its archive page lists.
     *
     * @return array<int, array{id:int, name:string, url:string, count:int}>
     */
    public function getCategoriesWithPostCounts(): array
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from(['link' => $this->resource->getTableName(self::LINK_TABLE)], ['category_id'])
            ->join(
                ['post' => $this->resource->getTableName('requestdesk_blog_post')],
                'post.post_id = link.post_id',
                ['count' => new \Zend_Db_Expr('COUNT(DISTINCT link.post_id)')]
            )
            ->where('post.status = ?', 1)
            ->group('link.category_id');

        $counts = [];
        foreach ($connection->fetchAll($select) as $row) {
            $counts[(int) $row['category_id']] = (int) $row['count'];
        }

        $result = [];
        foreach ($this->loadCategories(array_keys($counts)) as $categoryId => $category) {
            $result[] = $category + ['count' => $counts[$categoryId]];
        }

        usort(
            $result,
            static fn (array $a, array $b): int => [$b['count'], $a['name']] <=> [$a['count'], $b['name']]
        );

        return $result;
    }

    /**
     * Post ids assigned to a native category.
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
     * Assign a post to a native category (idempotent).
     *
     * @param int $postId
     * @param int $categoryId
     * @return void
     */
    public function attach(int $postId, int $categoryId): void
    {
        $connection = $this->resource->getConnection();
        $connection->insertOnDuplicate(
            $this->resource->getTableName(self::LINK_TABLE),
            ['post_id' => $postId, 'category_id' => $categoryId],
            ['post_id']
        );
    }

    /**
     * Native category ids assigned to a post.
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
        foreach (array_unique(array_filter(array_map('intval', $categoryIds))) as $categoryId) {
            $rows[] = ['post_id' => $postId, 'category_id' => $categoryId];
        }
        if ($rows !== []) {
            $connection->insertMultiple($table, $rows);
        }
    }
}
