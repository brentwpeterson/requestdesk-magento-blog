<?php
/**
 * RequestDesk Blog - Adjacent Post Finder
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Model;

use Magento\Framework\App\ResourceConnection;
use RequestDesk\Blog\Api\Data\PostInterface;

/**
 * Finds the published posts either side of a post, for the previous / next
 * article links under a post.
 *
 * Order is the blog listing's order, newest first, so "previous" is the next
 * older post and "next" the next newer one - the same reading the Amasty blog
 * on evrig.com gives them.
 *
 * created_at alone is not a total order. Two posts can share a timestamp
 * (an import or migration can write several in the same second), and on a
 * tie a plain "created_at <" skips every post that shares it. post_id breaks the tie, so each
 * post links to exactly one neighbour on each side and walking the links
 * visits every post once.
 */
class AdjacentPostFinder
{
    private const TABLE = 'requestdesk_blog_post';

    /**
     * @param ResourceConnection $resource
     */
    public function __construct(
        private readonly ResourceConnection $resource
    ) {
    }

    /**
     * The next older published post, or null for the oldest.
     *
     * @param PostInterface $post
     * @return int|null post id
     */
    public function findPreviousId(PostInterface $post): ?int
    {
        return $this->find($post, '<', 'DESC');
    }

    /**
     * The next newer published post, or null for the newest.
     *
     * @param PostInterface $post
     * @return int|null post id
     */
    public function findNextId(PostInterface $post): ?int
    {
        return $this->find($post, '>', 'ASC');
    }

    /**
     * Find
     *
     * @param PostInterface $post
     * @param string $operator '<' or '>'
     * @param string $direction 'DESC' or 'ASC', matching the operator
     * @return int|null
     */
    private function find(PostInterface $post, string $operator, string $direction): ?int
    {
        $createdAt = (string) $post->getCreatedAt();
        $postId = (int) $post->getPostId();
        if ($createdAt === '' || $postId === 0) {
            return null;
        }

        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName(self::TABLE), ['post_id'])
            ->where('status = ?', PostInterface::STATUS_PUBLISHED)
            ->where(
                $connection->quoteInto('created_at ' . $operator . ' ?', $createdAt)
                . ' OR ('
                . $connection->quoteInto('created_at = ?', $createdAt)
                . ' AND '
                . $connection->quoteInto('post_id ' . $operator . ' ?', $postId)
                . ')'
            )
            ->order('created_at ' . $direction)
            ->order('post_id ' . $direction)
            ->limit(1);

        $id = (int) $connection->fetchOne($select);

        return $id > 0 ? $id : null;
    }
}
