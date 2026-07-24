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
use RequestDesk\Blog\Api\QaLinkResolverInterface;

/**
 * The shared Q&A link table is polymorphic (entity_type + entity_id), so it
 * cannot carry a foreign key to a post and won't cascade-delete. This plugin
 * clears a post's Q&A links whenever the post is deleted. With no Q&A module
 * installed the resolver is a no-op, so this cleanup is harmless on the free tier.
 */
class PostRepositoryQaCleanup
{
    /**
     * @param QaLinkResolverInterface $qaLinkResolver
     */
    public function __construct(
        private readonly QaLinkResolverInterface $qaLinkResolver
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
        $this->qaLinkResolver->syncForEntity(QaLinkResolverInterface::ENTITY_BLOG_POST, (int) $post->getPostId(), []);
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
        $this->qaLinkResolver->syncForEntity(QaLinkResolverInterface::ENTITY_BLOG_POST, $postId, []);
        return $result;
    }
}
