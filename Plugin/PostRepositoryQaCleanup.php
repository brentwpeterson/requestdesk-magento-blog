<?php
/**
 * RequestDesk Blog - Detach Q&A links on post delete
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Plugin;

use RequestDesk\Blog\Api\Data\PostInterface;
use RequestDesk\Blog\Api\PostRepositoryInterface;
use RequestDesk\Qa\Model\QaLinkResolver;

/**
 * The shared Q&A link table is polymorphic (entity_type + entity_id), so it
 * cannot carry a foreign key to a post and won't cascade-delete. This plugin
 * clears a post's Q&A links whenever the post is deleted.
 */
class PostRepositoryQaCleanup
{
    /**
     * @param QaLinkResolver $qaLinkResolver
     */
    public function __construct(
        private readonly QaLinkResolver $qaLinkResolver
    ) {
    }

    /**
     * @param PostRepositoryInterface $subject
     * @param bool $result
     * @param PostInterface $post
     * @return bool
     */
    public function afterDelete(PostRepositoryInterface $subject, bool $result, PostInterface $post): bool
    {
        $this->qaLinkResolver->syncForEntity(QaLinkResolver::ENTITY_BLOG_POST, (int) $post->getPostId(), []);
        return $result;
    }

    /**
     * @param PostRepositoryInterface $subject
     * @param bool $result
     * @param int $postId
     * @return bool
     */
    public function afterDeleteById(PostRepositoryInterface $subject, bool $result, int $postId): bool
    {
        $this->qaLinkResolver->syncForEntity(QaLinkResolver::ENTITY_BLOG_POST, $postId, []);
        return $result;
    }
}
