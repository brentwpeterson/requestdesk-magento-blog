<?php
/**
 * RequestDesk Blog - Amasty category -> native Magento category mapping
 *
 * The migration deliberately does not bring Amasty's blog taxonomy across as a
 * second category tree. Blog posts use native Magento categories, so an Amasty
 * category becomes a real catalog category and the blog reuses everything
 * Magento already provides for them: admin CRUD, URL rewrites, store scoping,
 * permissions.
 *
 * Everything is created under ONE dedicated parent with include_in_menu and
 * is_anchor both off. Without that, imported blog categories surface in product
 * navigation, layered navigation and the catalog sitemap - the real cost of
 * reusing the catalog tree, and the whole reason this class does not simply
 * create categories at the root.
 *
 * Reads Amasty's tables directly, so Amasty itself does not need to be
 * installed and can be removed before or after the migration runs.
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Model;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class AmastyCategoryMapper
{
    private const SRC_CATEGORIES = 'amasty_blog_categories';
    private const SRC_CATEGORIES_STORE = 'amasty_blog_categories_store';
    private const SRC_POST_CATEGORY = 'amasty_blog_posts_category';

    /** Amasty keeps default-scope text under store 0. */
    private const DEFAULT_STORE = 0;

    /** Guards against a cycle in parent_id, which would otherwise recurse forever. */
    private const MAX_DEPTH = 10;

    /** @var array<int, int> amasty category_id => native category id, per run */
    private array $mapped = [];

    /**
     * @param ResourceConnection $resource
     * @param CategoryRepositoryInterface $categoryRepository
     * @param CategoryFactory $categoryFactory
     * @param StoreManagerInterface $storeManager
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly CategoryFactory $categoryFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Is Amasty blog data present at all? The tables are read directly, so this
     * is the only honest way to tell - the module may well be uninstalled.
     *
     * @return bool
     */
    public function sourceExists(): bool
    {
        $connection = $this->resource->getConnection();

        return $connection->isTableExists($this->resource->getTableName(self::SRC_CATEGORIES));
    }

    /**
     * Amasty category ids attached to one Amasty post.
     *
     * @param int $srcPostId
     * @return int[]
     */
    public function getSourceCategoryIds(int $srcPostId): array
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName(self::SRC_POST_CATEGORY), ['category_id'])
            ->where('post_id = ?', $srcPostId);

        return array_map('intval', $connection->fetchCol($select));
    }

    /**
     * Native category id for an Amasty category, creating it if needed.
     *
     * Parents are resolved first and recursively, so a nested Amasty tree lands
     * as a nested Magento one. Depth is not assumed: Amasty stores parent_id and
     * level, and a store can nest as deeply as it likes.
     *
     * @param int $srcCategoryId
     * @param int $rootParentId the dedicated parent everything hangs under
     * @param int $depth
     * @return int|null null when the source row is missing or creation failed
     */
    public function mapCategory(int $srcCategoryId, int $rootParentId, int $depth = 0): ?int
    {
        if ($srcCategoryId <= 0 || $depth > self::MAX_DEPTH) {
            return null;
        }
        if (isset($this->mapped[$srcCategoryId])) {
            return $this->mapped[$srcCategoryId];
        }

        $src = $this->fetchSourceCategory($srcCategoryId);
        if ($src === null) {
            return null;
        }

        // Resolve the parent first. An Amasty parent_id of 0 means top level,
        // which here means "directly under the dedicated blog parent".
        $parentId = $rootParentId;
        $srcParentId = (int) ($src['parent_id'] ?? 0);
        if ($srcParentId > 0) {
            $parentId = $this->mapCategory($srcParentId, $rootParentId, $depth + 1) ?? $rootParentId;
        }

        $urlKey = (string) ($src['url_key'] ?? '');
        $name = (string) ($src['name'] ?? '');
        if ($name === '') {
            $name = $urlKey !== '' ? $urlKey : ('Category ' . $srcCategoryId);
        }

        $existing = $this->findChildByUrlKey($parentId, $urlKey);
        if ($existing !== null) {
            $this->mapped[$srcCategoryId] = $existing;
            return $existing;
        }

        $created = $this->createCategory($name, $urlKey, $parentId);
        if ($created !== null) {
            $this->mapped[$srcCategoryId] = $created;
        }

        return $created;
    }

    /**
     * Find, or create, the single parent every imported blog category sits under.
     *
     * @param string $name
     * @param string $urlKey
     * @return int|null
     */
    public function getOrCreateRootParent(string $name = 'Blog', string $urlKey = 'blog'): ?int
    {
        try {
            $storeRootId = (int) $this->storeManager->getStore()->getRootCategoryId();
        } catch (\Throwable $e) {
            $this->logger->error('[RequestDesk_Blog] cannot resolve store root category: ' . $e->getMessage());
            return null;
        }

        $existing = $this->findChildByUrlKey($storeRootId, $urlKey);
        if ($existing !== null) {
            return $existing;
        }

        return $this->createCategory($name, $urlKey, $storeRootId);
    }

    /**
     * @return array<int, int> amasty id => native id, for this run
     */
    public function getMapping(): array
    {
        return $this->mapped;
    }

    /**
     * Amasty splits a category across two tables: structure in _categories,
     * localized name and url_key in _categories_store.
     *
     * @param int $srcCategoryId
     * @return array<string, mixed>|null
     */
    private function fetchSourceCategory(int $srcCategoryId): ?array
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from(
                ['c' => $this->resource->getTableName(self::SRC_CATEGORIES)],
                ['category_id', 'parent_id', 'level']
            )
            ->joinLeft(
                ['s' => $this->resource->getTableName(self::SRC_CATEGORIES_STORE)],
                's.category_id = c.category_id AND s.store_id = ' . self::DEFAULT_STORE,
                ['name', 'url_key']
            )
            ->where('c.category_id = ?', $srcCategoryId)
            ->limit(1);

        $row = $connection->fetchRow($select);

        return $row ?: null;
    }

    /**
     * A direct child of $parentId with this url_key, if there is one. Matching on
     * url_key rather than name is what makes the migration re-runnable: a second
     * pass finds what the first created instead of duplicating it.
     *
     * @param int $parentId
     * @param string $urlKey
     * @return int|null
     */
    private function findChildByUrlKey(int $parentId, string $urlKey): ?int
    {
        if ($urlKey === '') {
            return null;
        }

        try {
            $parent = $this->categoryRepository->get($parentId);
        } catch (\Throwable $e) {
            return null;
        }

        foreach ($parent->getChildrenCategories() as $child) {
            if ((string) $child->getUrlKey() === $urlKey) {
                return (int) $child->getId();
            }
        }

        return null;
    }

    /**
     * @param string $name
     * @param string $urlKey
     * @param int $parentId
     * @return int|null
     */
    private function createCategory(string $name, string $urlKey, int $parentId): ?int
    {
        try {
            /** @var CategoryInterface $category */
            $category = $this->categoryFactory->create();
            $category->setName($name)
                ->setParentId($parentId)
                ->setIsActive(true)
                // Both off deliberately: these are blog categories living in the
                // catalog tree, and neither belongs in product navigation.
                ->setIncludeInMenu(false)
                ->setData('is_anchor', 0)
                ->setAttributeSetId($category->getDefaultAttributeSetId());

            if ($urlKey !== '') {
                $category->setUrlKey($urlKey);
            }

            $saved = $this->categoryRepository->save($category);

            return (int) $saved->getId();
        } catch (\Throwable $e) {
            $this->logger->error(
                sprintf('[RequestDesk_Blog] could not create category "%s": %s', $name, $e->getMessage())
            );

            return null;
        }
    }
}
