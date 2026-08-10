<?php
/**
 * RequestDesk Blog - Author Resolver
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use RequestDesk\Blog\Api\Data\PostInterface;
use RequestDesk\Blog\Block\ImageUrl;

/**
 * Resolves a post's author from requestdesk_blog_author. An author is a
 * first-class record: it may optionally be linked to a Magento admin account,
 * but does not need one. Falls back to the legacy free-text author name on posts
 * that were never assigned an author record.
 */
class AuthorResolver
{
    private const AUTHOR_TABLE = 'requestdesk_blog_author';

    /**
     * @param ResourceConnection $resource
     * @param UrlInterface $urlBuilder
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly UrlInterface $urlBuilder,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * Resolve the author for a post.
     *
     * @param PostInterface $post
     * @return array{id:?int, name:string, bio:string, avatar:string, page_url:string, link:string}|null
     */
    public function getAuthorForPost(PostInterface $post): ?array
    {
        $authorId = $post->getAuthorId();
        if ($authorId) {
            $author = $this->getAuthor((int) $authorId);
            if ($author !== null) {
                return $author;
            }
        }

        $name = trim((string) $post->getAuthor());
        if ($name === '') {
            return null;
        }
        return ['id' => null, 'name' => $name, 'bio' => '', 'avatar' => '', 'page_url' => '', 'link' => ''];
    }

    /**
     * Resolve an author by blog author id.
     *
     * @param int $authorId
     * @return array{id:int, name:string, bio:string, avatar:string, page_url:string, link:string}|null
     */
    public function getAuthor(int $authorId): ?array
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from(
                $this->resource->getTableName(self::AUTHOR_TABLE),
                ['author_id', 'name', 'bio', 'avatar', 'url']
            )
            ->where('author_id = ?', $authorId)
            ->limit(1);

        $row = $connection->fetchRow($select);
        if (!$row) {
            return null;
        }

        return [
            'id' => (int) $row['author_id'],
            'name' => (string) $row['name'],
            'bio' => (string) ($row['bio'] ?? ''),
            'avatar' => ImageUrl::resolve($row['avatar'] ?? null, $this->storeManager),
            'page_url' => $this->urlBuilder->getUrl('blog/author/view', ['id' => (int) $row['author_id']]),
            'link' => (string) ($row['url'] ?? ''),
        ];
    }

    /**
     * Reuse the author with this name, or create one, and return its author_id.
     *
     * The counterpart to TagResolver::getOrCreateByName, and the only supported
     * way to turn an imported byline into a real author record. Importers must
     * go through here rather than writing requestdesk_blog_post.author_id
     * themselves: that column is a foreign key onto requestdesk_blog_author, so
     * putting any other id in it (an admin_user.user_id, say) either violates the
     * constraint outright or silently attaches the post to whichever author
     * happens to hold that id.
     *
     * @param string $name Display name; blank returns 0
     * @param string|null $bio Optional bio, only used when creating
     * @param string|null $avatar Optional avatar path, only used when creating
     * @return int author_id, or 0 when the name is blank
     */
    public function getOrCreateByName(string $name, ?string $bio = null, ?string $avatar = null): int
    {
        $name = trim($name);
        if ($name === '') {
            return 0;
        }

        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName(self::AUTHOR_TABLE);

        $existing = (int) $connection->fetchOne(
            $connection->select()
                ->from($table, ['author_id'])
                ->where('LOWER(name) = ?', mb_strtolower($name))
                ->limit(1)
        );
        if ($existing) {
            return $existing;
        }

        $connection->insert($table, [
            'name' => $name,
            'url_key' => $this->uniqueUrlKey($this->slugify($name)),
            'bio' => $bio !== null && trim($bio) !== '' ? $bio : null,
            'avatar' => $avatar !== null && trim($avatar) !== '' ? $avatar : null,
            'admin_user_id' => $this->matchUnlinkedAdminUser($name),
        ]);

        return (int) $connection->lastInsertId($table);
    }

    /**
     * Find the admin account that matches this name, if it is not already linked.
     *
     * The link is optional and the column is UNIQUE, so a second author with the
     * same name as an already-linked admin gets null rather than a duplicate-key
     * failure. Blog authors do not need Magento accounts.
     *
     * @param string $name
     * @return int|null
     */
    private function matchUnlinkedAdminUser(string $name): ?int
    {
        $connection = $this->resource->getConnection();
        $fullName = new \Zend_Db_Expr("TRIM(CONCAT(COALESCE(firstname,''),' ',COALESCE(lastname,'')))");

        $userId = (int) $connection->fetchOne(
            $connection->select()
                ->from($this->resource->getTableName('admin_user'), ['user_id'])
                ->where('LOWER(' . $fullName . ') = ?', mb_strtolower($name))
                ->orWhere('LOWER(username) = ?', mb_strtolower($name))
                ->limit(1)
        );
        if (!$userId) {
            return null;
        }

        $alreadyLinked = (int) $connection->fetchOne(
            $connection->select()
                ->from($this->resource->getTableName(self::AUTHOR_TABLE), ['author_id'])
                ->where('admin_user_id = ?', $userId)
                ->limit(1)
        );

        return $alreadyLinked ? null : $userId;
    }

    /**
     * Normalize a display name into a URL key.
     *
     * @param string $value
     * @return string
     */
    private function slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = (string) preg_replace('/[^a-z0-9]+/', '-', $value);
        $value = trim($value, '-');
        return $value !== '' ? $value : 'author';
    }

    /**
     * Suffix the slug until it clears the unique url_key constraint.
     *
     * @param string $base
     * @return string
     */
    private function uniqueUrlKey(string $base): string
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName(self::AUTHOR_TABLE);
        $candidate = $base;
        $i = 2;
        while ((int) $connection->fetchOne(
            $connection->select()->from($table, ['author_id'])->where('url_key = ?', $candidate)->limit(1)
        )) {
            $candidate = $base . '-' . $i++;
        }
        return $candidate;
    }

    /**
     * Published post ids by this author, newest first.
     *
     * @param int $authorId
     * @return int[]
     */
    public function getPostIdsByAuthor(int $authorId): array
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName('requestdesk_blog_post'), ['post_id'])
            ->where('author_id = ?', $authorId)
            ->where('status = ?', PostInterface::STATUS_PUBLISHED)
            ->order('created_at DESC');
        return array_map('intval', $connection->fetchCol($select));
    }
}
