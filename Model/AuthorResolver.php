<?php
/**
 * RequestDesk Blog - Author Resolver (native admin_user + public profile)
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
 * Resolves a post's author by reusing the NATIVE Magento admin user, extended
 * with the public profile (bio/avatar) in requestdesk_blog_author_profile.
 * Falls back to the legacy free-text author name when no admin user is linked.
 */
class AuthorResolver
{
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
     * Resolve an author by native admin_user id.
     *
     * @param int $userId
     * @return array{id:int, name:string, bio:string, avatar:string, page_url:string, link:string}|null
     */
    public function getAuthor(int $userId): ?array
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from(['u' => $this->resource->getTableName('admin_user')], ['firstname', 'lastname', 'username'])
            ->joinLeft(
                ['p' => $this->resource->getTableName('requestdesk_blog_author_profile')],
                'p.admin_user_id = u.user_id',
                ['display_name', 'bio', 'avatar', 'url']
            )
            ->where('u.user_id = ?', $userId)
            ->limit(1);

        $row = $connection->fetchRow($select);
        if (!$row) {
            return null;
        }

        $name = trim((string) ($row['display_name'] ?? ''));
        if ($name === '') {
            $name = trim(($row['firstname'] ?? '') . ' ' . ($row['lastname'] ?? ''));
        }
        if ($name === '') {
            $name = (string) ($row['username'] ?? '');
        }

        return [
            'id' => $userId,
            'name' => $name,
            'bio' => (string) ($row['bio'] ?? ''),
            'avatar' => ImageUrl::resolve($row['avatar'] ?? null, $this->storeManager),
            'page_url' => $this->urlBuilder->getUrl('blog/author/view', ['id' => $userId]),
            'link' => (string) ($row['url'] ?? ''),
        ];
    }

    /**
     * Published post ids by this author, newest first.
     *
     * @param int $userId
     * @return int[]
     */
    public function getPostIdsByAuthor(int $userId): array
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName('requestdesk_blog_post'), ['post_id'])
            ->where('author_id = ?', $userId)
            ->where('status = ?', PostInterface::STATUS_PUBLISHED)
            ->order('created_at DESC');
        return array_map('intval', $connection->fetchCol($select));
    }
}
