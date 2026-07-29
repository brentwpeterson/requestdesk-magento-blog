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
