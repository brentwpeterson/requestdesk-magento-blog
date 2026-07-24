<?php
/**
 * RequestDesk Blog - No-op Q&A link resolver (free-tier default)
 *
 * Bound by default so the blog runs standalone. The paid RequestDesk Q&A
 * integration overrides RequestDesk\Blog\Api\QaLinkResolverInterface with a
 * real, Q&A-backed implementation.
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Model\Qa;

use RequestDesk\Blog\Api\QaLinkResolverInterface;

class NullQaLinkResolver implements QaLinkResolverInterface
{
    /**
     * @inheritdoc
     */
    public function getPairsFor(string $entityType, int $entityId): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getQaIdsFor(string $entityType, int $entityId): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function syncForEntity(string $entityType, int $entityId, array $qaIds): void
    {
        // No Q&A module installed — nothing to link.
    }
}
