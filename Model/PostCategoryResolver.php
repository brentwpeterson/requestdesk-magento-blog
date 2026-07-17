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
     */
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly LoggerInterface $logger,
        private readonly UrlInterface $urlBuilder
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
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName(self::LINK_TABLE), ['category_id'])
            ->where('post_id = ?', $postId);

        $categories = [];
        foreach ($connection->fetchCol($select) as $categoryId) {
            $categoryId = (int) $categoryId;
            try {
                $category = $this->categoryRepository->get($categoryId);
                $categories[] = [
                    'id' => $categoryId,
                    'name' => (string) $category->getName(),
                    'url' => $this->urlBuilder->getUrl('blog/category/view', ['id' => $categoryId]),
                ];
            } catch (\Throwable $e) {
                // category removed from the catalog — skip it
                $this->logger->debug('RequestDesk Blog: linked category missing', [
                    'category_id' => $categoryId,
                    'post_id' => $postId,
                ]);
            }
        }
        return $categories;
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
