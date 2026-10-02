<?php
/**
 * RequestDesk Blog - Blog search across posts, authors, categories and tags
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength.TooLong

namespace RequestDesk\Blog\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Select;
use Magento\Framework\UrlInterface;
use RequestDesk\Blog\Api\Data\PostInterface;
use RequestDesk\Blog\Block\ArchiveUrl;
use RequestDesk\Blog\Block\PostUrl;

/**
 * Search the way Amasty Blog does it, in four groups:
 *  - posts:      published posts whose title or body has every word;
 *  - authors:    published posts by an author whose name has every word;
 *  - categories: published posts in an enabled category whose name has every word;
 *  - tags:       published posts with a tag whose name has every word.
 *
 * The results page shows one tab per group with its post count; the live
 * search dropdown shows the matching posts, authors, categories and tags
 * themselves. An author, category or tag with no published post is left out of
 * both, since its archive would be empty.
 */
class BlogSearch
{
    public const TYPE_POSTS = 'posts';
    public const TYPE_AUTHORS = 'authors';
    public const TYPE_CATEGORIES = 'categories';
    public const TYPE_TAGS = 'tags';

    /** Tab order, the same as Amasty's. */
    public const TYPES = [self::TYPE_POSTS, self::TYPE_AUTHORS, self::TYPE_CATEGORIES, self::TYPE_TAGS];

    private const POST_TABLE = 'requestdesk_blog_post';

    /**
     * Archive entities: table, id column, link table (null = post.author_id),
     * archive URL type, and whether a status column must be enabled.
     */
    private const ENTITIES = [
        self::TYPE_AUTHORS => ['requestdesk_blog_author', 'author_id', null, ArchiveUrl::TYPE_AUTHOR, false],
        self::TYPE_CATEGORIES => [
            'requestdesk_blog_category',
            'category_id',
            'requestdesk_blog_category_post',
            ArchiveUrl::TYPE_CATEGORY,
            true,
        ],
        self::TYPE_TAGS => ['requestdesk_blog_tag', 'tag_id', 'requestdesk_blog_post_tag', ArchiveUrl::TYPE_TAG, false],
    ];

    /**
     * @param ResourceConnection $resource
     * @param PostSearch $postSearch
     * @param UrlInterface $urlBuilder
     * @param Config $config
     */
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly PostSearch $postSearch,
        private readonly UrlInterface $urlBuilder,
        private readonly Config $config
    ) {
    }

    /**
     * Ids of the published posts behind one group's tab.
     *
     * @param string $type one of self::TYPES
     * @param string $query already normalised
     * @return int[]
     */
    public function getPostIds(string $type, string $query): array
    {
        if ($type === self::TYPE_POSTS) {
            return $this->postSearch->findPostIds($query);
        }

        $words = PostSearch::words($query);
        if ($words === [] || !isset(self::ENTITIES[$type])) {
            return [];
        }

        $connection = $this->resource->getConnection();

        return array_map('intval', $connection->fetchCol(
            $this->entityPostSelect($type, $words)->columns(['post_id' => 'p.post_id'])->distinct(true)
        ));
    }

    /**
     * Post count for every tab.
     *
     * @param string $query already normalised
     * @return array<string, int> type => count, in tab order
     */
    public function getCounts(string $query): array
    {
        $counts = [];
        foreach (self::TYPES as $type) {
            $counts[$type] = count($this->getPostIds($type, $query));
        }

        return $counts;
    }

    /**
     * What the live search dropdown lists: up to $limit matches per group.
     *
     * Each match is a title and URL. Empty groups are left out.
     *
     * @param string $query already normalised
     * @param int $limit per group
     * @return array<string, array<int, array{title:string, url:string}>>
     */
    public function suggest(string $query, int $limit): array
    {
        $limit = max(1, $limit);
        $result = [];

        $posts = $this->suggestPosts($query, $limit);
        if ($posts !== []) {
            $result[self::TYPE_POSTS] = $posts;
        }

        $words = PostSearch::words($query);
        foreach (array_keys(self::ENTITIES) as $type) {
            $items = $words === [] ? [] : $this->suggestEntities($type, $words, $limit);
            if ($items !== []) {
                $result[$type] = $items;
            }
        }

        return $result;
    }

    /**
     * Newest matching posts first.
     *
     * @param string $query
     * @param int $limit
     * @return array<int, array{title:string, url:string}>
     */
    private function suggestPosts(string $query, int $limit): array
    {
        $postIds = $this->postSearch->findPostIds($query);
        if ($postIds === []) {
            return [];
        }

        $connection = $this->resource->getConnection();
        $rows = $connection->fetchAll(
            $connection->select()
                ->from($this->resource->getTableName(self::POST_TABLE), ['post_id', 'title', 'url_key'])
                ->where('post_id IN (?)', $postIds)
                ->order(['created_at DESC', 'post_id DESC'])
                ->limit($limit)
        );

        $prefix = $this->config->getUrlPrefix();

        return array_map(
            fn (array $row): array => [
                'title' => (string) $row['title'],
                'url' => PostUrl::fromKey((int) $row['post_id'], $row['url_key'], $this->urlBuilder, $prefix),
            ],
            $rows
        );
    }

    /**
     * Matching authors, categories or tags that have a published post, A to Z.
     *
     * @param string $type
     * @param string[] $words
     * @param int $limit
     * @return array<int, array{title:string, url:string}>
     */
    private function suggestEntities(string $type, array $words, int $limit): array
    {
        [, $idColumn, , $archiveType] = self::ENTITIES[$type];

        $select = $this->entityPostSelect($type, $words)
            ->columns(['id' => 'e.' . $idColumn, 'name' => 'e.name', 'url_key' => 'e.url_key'])
            ->group('e.' . $idColumn)
            ->order('e.name ASC')
            ->limit($limit);

        $prefix = $this->config->getUrlPrefix();

        return array_map(
            fn (array $row): array => [
                'title' => (string) $row['name'],
                'url' => ArchiveUrl::resolve($archiveType, (int) $row['id'], $row['url_key'], $this->urlBuilder, $prefix),
            ],
            $this->resource->getConnection()->fetchAll($select)
        );
    }

    /**
     * Published posts joined to the $type entities whose name has every word.
     *
     * No columns selected; callers add what they need.
     *
     * @param string $type
     * @param string[] $words
     * @return Select
     */
    private function entityPostSelect(string $type, array $words): Select
    {
        [$table, $idColumn, $linkTable, , $enabledOnly] = self::ENTITIES[$type];
        $connection = $this->resource->getConnection();

        $select = $connection->select()
            ->from(['p' => $this->resource->getTableName(self::POST_TABLE)], [])
            ->where('p.status = ?', PostInterface::STATUS_PUBLISHED);

        if ($linkTable === null) {
            $select->join(['e' => $this->resource->getTableName($table)], 'e.' . $idColumn . ' = p.author_id', []);
        } else {
            $select
                ->join(['l' => $this->resource->getTableName($linkTable)], 'l.post_id = p.post_id', [])
                ->join(['e' => $this->resource->getTableName($table)], 'e.' . $idColumn . ' = l.' . $idColumn, []);
        }

        if ($enabledOnly) {
            $select->where('e.status = ?', 1);
        }
        foreach (PostSearch::likeAll($connection, 'e.name', $words) as $condition) {
            $select->where($condition);
        }

        return $select;
    }
}
