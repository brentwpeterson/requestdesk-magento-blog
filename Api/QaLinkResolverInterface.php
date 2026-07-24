<?php
/**
 * RequestDesk Blog - Q&A link resolver contract (optional integration seam)
 *
 * The free blog defines this contract and binds a no-op default. The paid
 * RequestDesk Q&A integration overrides the binding to attach shared Q&A pairs
 * to blog posts. Signatures and the entity-type value mirror
 * RequestDesk\Qa\Model\QaLinkResolver so the paid adapter is a straight pass-through.
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Api;

interface QaLinkResolverInterface
{
    /**
     * Entity type for a blog post in the shared Q&A link table.
     * Value must match RequestDesk\Qa\Model\QaLinkResolver::ENTITY_BLOG_POST.
     */
    public const ENTITY_BLOG_POST = 'blog_post';

    /**
     * Q&A pairs attached to an entity, as [{question, answer}, ...].
     *
     * @param string $entityType
     * @param int $entityId
     * @return array<int, array{question:string, answer:string}>
     */
    public function getPairsFor(string $entityType, int $entityId): array;

    /**
     * IDs of the Q&A pairs attached to an entity.
     *
     * @param string $entityType
     * @param int $entityId
     * @return int[]
     */
    public function getQaIdsFor(string $entityType, int $entityId): array;

    /**
     * Replace an entity's Q&A links with the given set of pair IDs.
     *
     * @param string $entityType
     * @param int $entityId
     * @param int[] $qaIds
     * @return void
     */
    public function syncForEntity(string $entityType, int $entityId, array $qaIds): void;
}
