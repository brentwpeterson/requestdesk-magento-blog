<?php
/**
 * RequestDesk Blog - Tag Resolver
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\UrlInterface;

/**
 * Reads and attaches blog tags. Tags are a blog-owned taxonomy (Magento has no
 * native tag entity), linked to posts through requestdesk_blog_post_tag.
 */
class TagResolver
{
    private const TAG_TABLE = 'requestdesk_blog_tag';
    private const LINK_TABLE = 'requestdesk_blog_post_tag';

    /**
     * @param ResourceConnection $resource
     * @param UrlInterface $urlBuilder
     */
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly UrlInterface $urlBuilder
    ) {
    }

    /**
     * Tags on a post, as [id, name, url].
     *
     * @param int $postId
     * @return array<int, array{id:int, name:string, url:string}>
     */
    public function getTagsForPost(int $postId): array
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from(['l' => $this->resource->getTableName(self::LINK_TABLE)], [])
            ->join(
                ['t' => $this->resource->getTableName(self::TAG_TABLE)],
                't.tag_id = l.tag_id',
                ['tag_id', 'name']
            )
            ->where('l.post_id = ?', $postId)
            ->order('t.name ASC');

        $tags = [];
        foreach ($connection->fetchAll($select) as $row) {
            $tags[] = [
                'id' => (int) $row['tag_id'],
                'name' => (string) $row['name'],
                'url' => $this->urlBuilder->getUrl('blog/tag/view', ['id' => (int) $row['tag_id']]),
            ];
        }
        return $tags;
    }

    /**
     * Tag names on a post (for schema keywords).
     *
     * @param int $postId
     * @return string[]
     */
    public function getTagNamesForPost(int $postId): array
    {
        return array_column($this->getTagsForPost($postId), 'name');
    }

    /**
     * A single tag by id, as [id, name, url], or null.
     *
     * @param int $tagId
     * @return array{id:int, name:string, url:string}|null
     */
    public function getTag(int $tagId): ?array
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName(self::TAG_TABLE), ['tag_id', 'name'])
            ->where('tag_id = ?', $tagId)
            ->limit(1);
        $row = $connection->fetchRow($select);
        if (!$row) {
            return null;
        }
        return [
            'id' => (int) $row['tag_id'],
            'name' => (string) $row['name'],
            'url' => $this->urlBuilder->getUrl('blog/tag/view', ['id' => (int) $row['tag_id']]),
        ];
    }

    /**
     * Published post ids carrying a tag.
     *
     * @param int $tagId
     * @return int[]
     */
    public function getPostIdsByTag(int $tagId): array
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from(['l' => $this->resource->getTableName(self::LINK_TABLE)], ['post_id'])
            ->join(
                ['p' => $this->resource->getTableName('requestdesk_blog_post')],
                'p.post_id = l.post_id',
                []
            )
            ->where('l.tag_id = ?', $tagId)
            ->where('p.status = ?', 1)
            ->order('p.created_at DESC');
        return array_map('intval', $connection->fetchCol($select));
    }

    /**
     * Attach a tag to a post (idempotent).
     *
     * @param int $postId
     * @param int $tagId
     * @return void
     */
    public function attach(int $postId, int $tagId): void
    {
        $connection = $this->resource->getConnection();
        $connection->insertOnDuplicate(
            $this->resource->getTableName(self::LINK_TABLE),
            ['post_id' => $postId, 'tag_id' => $tagId],
            ['post_id']
        );
    }

    /**
     * Tag ids on a post.
     *
     * @param int $postId
     * @return int[]
     */
    public function getTagIdsForPost(int $postId): array
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName(self::LINK_TABLE), ['tag_id'])
            ->where('post_id = ?', $postId);
        return array_map('intval', $connection->fetchCol($select));
    }

    /**
     * Find a tag by name (case-insensitive), or create it. Returns its id.
     * Used by the importer so incoming tag names become real tag entities.
     *
     * @param string $name
     * @return int
     */
    public function getOrCreateByName(string $name): int
    {
        $name = trim($name);
        if ($name === '') {
            return 0;
        }

        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName(self::TAG_TABLE);

        $existing = (int) $connection->fetchOne(
            $connection->select()
                ->from($table, ['tag_id'])
                ->where('LOWER(name) = ?', mb_strtolower($name))
                ->limit(1)
        );
        if ($existing) {
            return $existing;
        }

        $urlKey = $this->uniqueUrlKey($this->slugify($name));
        $connection->insert($table, ['name' => $name, 'url_key' => $urlKey]);
        return (int) $connection->lastInsertId();
    }

    /**
     * Normalize a string into a URL key.
     *
     * @param string $value
     * @return string
     */
    private function slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = (string) preg_replace('/[^a-z0-9]+/', '-', $value);
        $value = trim($value, '-');
        return $value !== '' ? $value : 'tag';
    }

    /**
     * Ensure the url_key is unique by suffixing -2, -3, ... if needed.
     *
     * @param string $base
     * @return string
     */
    private function uniqueUrlKey(string $base): string
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName(self::TAG_TABLE);
        $candidate = $base;
        $i = 2;
        while ((int) $connection->fetchOne(
            $connection->select()->from($table, ['tag_id'])->where('url_key = ?', $candidate)->limit(1)
        )) {
            $candidate = $base . '-' . $i++;
        }
        return $candidate;
    }

    /**
     * Replace a post's tag links with exactly the given set.
     *
     * @param int $postId
     * @param int[] $tagIds
     * @return void
     */
    public function syncForPost(int $postId, array $tagIds): void
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName(self::LINK_TABLE);
        $connection->delete($table, ['post_id = ?' => $postId]);

        $rows = [];
        foreach (array_unique(array_filter(array_map('intval', $tagIds))) as $tagId) {
            $rows[] = ['post_id' => $postId, 'tag_id' => $tagId];
        }
        if ($rows !== []) {
            $connection->insertMultiple($table, $rows);
        }
    }
}
