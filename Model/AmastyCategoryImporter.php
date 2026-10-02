<?php
/**
 * RequestDesk Blog - Amasty category -> blog category import
 *
 * Copies Amasty Blog categories into requestdesk_blog_category, keeping the
 * tree, and is what requestdesk:blog:migrate-amasty-categories and
 * requestdesk:blog:migrate-amasty use to file posts.
 *
 * Replaces AmastyCategoryMapper, which turned each Amasty category into a
 * native catalog category. Blog categories now have their own table, so the
 * catalog is no longer touched at all.
 *
 * Idempotent: each imported row records its amasty_category_id, and a re-run
 * returns that row instead of making another. A category someone has since
 * edited in the admin is left as they left it.
 *
 * Reads Amasty's tables directly, so Amasty itself does not need to be
 * installed and can be removed before or after the import runs.
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength.TooLong

namespace RequestDesk\Blog\Model;

use Magento\Framework\App\ResourceConnection;

class AmastyCategoryImporter
{
    private const SRC_CATEGORIES = 'amasty_blog_categories';
    private const SRC_CATEGORIES_STORE = 'amasty_blog_categories_store';
    private const SRC_POST_CATEGORY = 'amasty_blog_posts_category';
    private const SRC_POSTS = 'amasty_blog_posts';
    private const TARGET = 'requestdesk_blog_category';

    /** Amasty keeps default-scope text under store 0. */
    private const DEFAULT_STORE = 0;

    /** Guards against a cycle in parent_id, which would otherwise recurse forever. */
    private const MAX_DEPTH = 10;

    /** Store-scoped columns copied across, in Amasty's spelling and ours. */
    private const TEXT_COLUMNS = ['name', 'url_key', 'description', 'status', 'meta_title', 'meta_tags', 'meta_description', 'meta_robots'];

    /** @var array<int, int> amasty category_id => blog category_id, per run */
    private array $mapped = [];

    /** @var array<int, true> blog category ids created this run */
    private array $created = [];

    /**
     * @param ResourceConnection $resource
     */
    public function __construct(
        private readonly ResourceConnection $resource
    ) {
    }

    /**
     * Is Amasty category data present at all?
     *
     * The tables are read directly, so this is the only honest way to tell -
     * the module may well be uninstalled.
     *
     * @return bool
     */
    public function sourceExists(): bool
    {
        $connection = $this->resource->getConnection();

        return $connection->isTableExists($this->resource->getTableName(self::SRC_CATEGORIES))
            && $connection->isTableExists($this->resource->getTableName(self::SRC_CATEGORIES_STORE));
    }

    /**
     * Every Amasty category id.
     *
     * Order does not matter: mapCategory() resolves each parent before its
     * child whatever order they arrive in.
     *
     * @return int[]
     */
    public function getAllSourceCategoryIds(): array
    {
        $connection = $this->resource->getConnection();

        return array_map('intval', $connection->fetchCol(
            $connection->select()
                ->from($this->resource->getTableName(self::SRC_CATEGORIES), ['category_id'])
                ->order('category_id ASC')
        ));
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
        if (!$connection->isTableExists($this->resource->getTableName(self::SRC_POST_CATEGORY))) {
            return [];
        }

        return array_map('intval', $connection->fetchCol(
            $connection->select()
                ->from($this->resource->getTableName(self::SRC_POST_CATEGORY), ['category_id'])
                ->where('post_id = ?', $srcPostId)
        ));
    }

    /**
     * Every Amasty post-to-category link, with the post's url_key.
     *
     * The url_key is what matches it to the migrated post.
     *
     * @return array<int, array{url_key:string, category_id:int}>
     */
    public function getSourcePostLinks(): array
    {
        $connection = $this->resource->getConnection();
        foreach ([self::SRC_POST_CATEGORY, self::SRC_POSTS] as $table) {
            if (!$connection->isTableExists($this->resource->getTableName($table))) {
                return [];
            }
        }

        $rows = $connection->fetchAll(
            $connection->select()
                ->from(['l' => $this->resource->getTableName(self::SRC_POST_CATEGORY)], ['category_id'])
                ->join(
                    ['p' => $this->resource->getTableName(self::SRC_POSTS)],
                    'p.post_id = l.post_id',
                    ['url_key']
                )
                ->order(['l.post_id ASC', 'l.category_id ASC'])
        );

        return array_map(
            static fn (array $row): array => [
                'url_key' => (string) $row['url_key'],
                'category_id' => (int) $row['category_id'],
            ],
            $rows
        );
    }

    /**
     * Blog category id for an Amasty category, creating it if needed.
     *
     * Parents are resolved first and recursively, so a nested Amasty tree lands
     * as the same nested tree here.
     *
     * @param int $srcCategoryId
     * @param int $depth
     * @return int|null null when the source row is missing
     */
    public function mapCategory(int $srcCategoryId, int $depth = 0): ?int
    {
        if ($srcCategoryId <= 0 || $depth > self::MAX_DEPTH) {
            return null;
        }
        if (isset($this->mapped[$srcCategoryId])) {
            return $this->mapped[$srcCategoryId];
        }

        $existing = $this->findByAmastyId($srcCategoryId);
        if ($existing !== null) {
            return $this->mapped[$srcCategoryId] = $existing;
        }

        $src = $this->fetchSourceCategory($srcCategoryId);
        if ($src === null) {
            return null;
        }

        $parentId = 0;
        $srcParentId = (int) ($src['parent_id'] ?? 0);
        if ($srcParentId > 0 && $srcParentId !== $srcCategoryId) {
            $parentId = $this->mapCategory($srcParentId, $depth + 1) ?? 0;
        }

        $name = trim((string) ($src['name'] ?? ''));
        $urlKey = self::normalizeUrlKey((string) ($src['url_key'] ?? ''), $name, $srcCategoryId);
        if ($name === '') {
            $name = $urlKey;
        }

        // A category made by hand before the import, with the same url_key, is
        // taken over rather than duplicated - which also keeps the url_key
        // unique. One already claimed by a different Amasty category is not:
        // that is a real clash, so this one gets a suffixed key instead.
        $byUrlKey = $this->findByUrlKey($urlKey);
        if ($byUrlKey !== null) {
            if ($byUrlKey['amasty_category_id'] === null) {
                $this->claim($byUrlKey['category_id'], $srcCategoryId);
                return $this->mapped[$srcCategoryId] = $byUrlKey['category_id'];
            }
            $urlKey .= '-' . $srcCategoryId;
        }

        $connection = $this->resource->getConnection();
        $connection->insert($this->resource->getTableName(self::TARGET), [
            'parent_id' => $parentId,
            'name' => $name,
            'url_key' => $urlKey,
            // Same image-path move the posts get: media now lives under blog/.
            'description' => self::nullIfBlank(AmastyMediaPath::rewriteContent((string) ($src['description'] ?? ''))),
            'status' => ($src['status'] ?? null) === null || (int) $src['status'] === Category::STATUS_ENABLED
                ? Category::STATUS_ENABLED
                : Category::STATUS_DISABLED,
            'sort_order' => max(0, (int) ($src['sort_order'] ?? 0)),
            'meta_title' => self::nullIfBlank($src['meta_title'] ?? null),
            'meta_tags' => self::nullIfBlank($src['meta_tags'] ?? null),
            'meta_description' => self::nullIfBlank($src['meta_description'] ?? null),
            'meta_robots' => self::nullIfBlank($src['meta_robots'] ?? null),
            'amasty_category_id' => $srcCategoryId,
        ]);
        $id = (int) $connection->lastInsertId($this->resource->getTableName(self::TARGET));

        $this->created[$id] = true;

        return $this->mapped[$srcCategoryId] = $id;
    }

    /**
     * Get mapping
     *
     * @return array<int, int> amasty id => blog category id, for this run
     */
    public function getMapping(): array
    {
        return $this->mapped;
    }

    /**
     * How many categories this run created, as opposed to found.
     *
     * @return int
     */
    public function getCreatedCount(): int
    {
        return count($this->created);
    }

    /**
     * A url_key for the blog table from Amasty's, falling back to the name and
     * then to the source id, so an import never writes an empty key into a
     * unique column.
     *
     * @param string $urlKey
     * @param string $name
     * @param int $srcCategoryId
     * @return string
     */
    public static function normalizeUrlKey(string $urlKey, string $name, int $srcCategoryId): string // phpcs:ignore Magento2.Functions.StaticFunction
    {
        foreach ([$urlKey, $name] as $candidate) {
            $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower(trim($candidate))), '-');
            if ($slug !== '') {
                return $slug;
            }
        }

        return 'category-' . $srcCategoryId;
    }

    /**
     * One Amasty category: structure from _categories, text from _categories_store.
     *
     * Prefers the store-0 (default) row. An install that only ever wrote
     * per-store rows has none, so it falls back to the lowest store's row
     * rather than importing a nameless category.
     *
     * sort_order is selected only when the column is there: it is on every
     * Amasty release we know of, but the source is read without Amasty's code,
     * and a missing column would fail the whole import for one cosmetic field.
     *
     * @param int $srcCategoryId
     * @return array<string, mixed>|null
     */
    private function fetchSourceCategory(int $srcCategoryId): ?array
    {
        $connection = $this->resource->getConnection();
        $categoriesTable = $this->resource->getTableName(self::SRC_CATEGORIES);
        $storeTable = $this->resource->getTableName(self::SRC_CATEGORIES_STORE);

        $structureColumns = array_values(array_intersect(
            ['category_id', 'parent_id', 'sort_order'],
            array_keys($connection->describeTable($categoriesTable))
        ));
        $row = $connection->fetchRow(
            $connection->select()
                ->from($categoriesTable, $structureColumns)
                ->where('category_id = ?', $srcCategoryId)
                ->limit(1)
        );
        if ($row === false) {
            return null;
        }

        $textColumns = array_values(array_intersect(
            self::TEXT_COLUMNS,
            array_keys($connection->describeTable($storeTable))
        ));
        $text = $connection->fetchRow(
            $connection->select()
                ->from($storeTable, $textColumns)
                ->where('category_id = ?', $srcCategoryId)
                ->order(new \Zend_Db_Expr('store_id = ' . self::DEFAULT_STORE . ' DESC'))
                ->order('store_id ASC')
                ->limit(1)
        );

        return $row + ($text ?: []);
    }

    /**
     * Find by amasty id
     *
     * @param int $srcCategoryId
     * @return int|null
     */
    private function findByAmastyId(int $srcCategoryId): ?int
    {
        $connection = $this->resource->getConnection();
        $id = (int) $connection->fetchOne(
            $connection->select()
                ->from($this->resource->getTableName(self::TARGET), ['category_id'])
                ->where('amasty_category_id = ?', $srcCategoryId)
                ->limit(1)
        );

        return $id > 0 ? $id : null;
    }

    /**
     * Find by url key
     *
     * @param string $urlKey
     * @return array{category_id:int, amasty_category_id:?int}|null
     */
    private function findByUrlKey(string $urlKey): ?array
    {
        $connection = $this->resource->getConnection();
        $row = $connection->fetchRow(
            $connection->select()
                ->from($this->resource->getTableName(self::TARGET), ['category_id', 'amasty_category_id'])
                ->where('url_key = ?', $urlKey)
                ->limit(1)
        );
        if (!$row) {
            return null;
        }

        return [
            'category_id' => (int) $row['category_id'],
            'amasty_category_id' => $row['amasty_category_id'] !== null ? (int) $row['amasty_category_id'] : null,
        ];
    }

    /**
     * Record which Amasty category an existing row stands for.
     *
     * @param int $categoryId
     * @param int $srcCategoryId
     * @return void
     */
    private function claim(int $categoryId, int $srcCategoryId): void
    {
        $this->resource->getConnection()->update(
            $this->resource->getTableName(self::TARGET),
            ['amasty_category_id' => $srcCategoryId],
            ['category_id = ?' => $categoryId]
        );
    }

    /**
     * Null if blank
     *
     * @param mixed $value
     * @return string|null
     */
    private static function nullIfBlank(mixed $value): ?string // phpcs:ignore Magento2.Functions.StaticFunction
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
