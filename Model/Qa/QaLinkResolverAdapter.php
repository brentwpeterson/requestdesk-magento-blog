<?php
/**
 * Copyright (c) 2025 Content Basis LLC
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available at https://opensource.org/licenses/OSL-3.0
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 * @author    Content Basis LLC
 * @copyright Copyright (c) 2025 Content Basis LLC
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License 3.0
 */
declare(strict_types=1);

namespace RequestDesk\Blog\Model\Qa;

use RequestDesk\Blog\Api\QaLinkResolverInterface;

/**
 * Delegates Q&A links to RequestDesk_Qa when it is installed, and behaves as a
 * no-op otherwise. See {@see QaBridge} for why the delegate is resolved at
 * runtime rather than injected.
 */
class QaLinkResolverAdapter implements QaLinkResolverInterface
{
    private const DELEGATE = \RequestDesk\Qa\Model\QaLinkResolver::class;

    /**
     * @param QaBridge $bridge
     */
    public function __construct(
        private readonly QaBridge $bridge
    ) {
    }

    /**
     * @inheritdoc
     */
    public function getPairsFor(string $entityType, int $entityId): array
    {
        $delegate = $this->bridge->get(self::DELEGATE);
        return $delegate === null ? [] : $delegate->getPairsFor($entityType, $entityId);
    }

    /**
     * @inheritdoc
     */
    public function getQaIdsFor(string $entityType, int $entityId): array
    {
        $delegate = $this->bridge->get(self::DELEGATE);
        return $delegate === null ? [] : $delegate->getQaIdsFor($entityType, $entityId);
    }

    /**
     * @inheritdoc
     */
    public function syncForEntity(string $entityType, int $entityId, array $qaIds): void
    {
        $delegate = $this->bridge->get(self::DELEGATE);
        if ($delegate !== null) {
            $delegate->syncForEntity($entityType, $entityId, $qaIds);
        }
    }
}
