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

namespace RequestDesk\Blog\Model;

use Magento\Store\Model\StoreManagerInterface;

/**
 * Which posts the current store view may show.
 *
 * A post belongs to one store view, or to store 0, which means all of them.
 * Every storefront query that lists or opens posts filters on these ids. Before
 * 1.15.0 only the sitemap did, so with two websites on one install (Evrig and
 * CommerceKing) each blog listed, searched and opened the other's posts.
 * Categories and tags carry no store of their own: they show on a store view
 * through the posts it can see.
 */
class StoreScope
{
    public const ALL_STORES = 0;

    /**
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * Store ids a post may carry to show on the current store view.
     *
     * @return int[]
     */
    public function getVisibleStoreIds(): array
    {
        $storeId = (int) $this->storeManager->getStore()->getId();

        return $storeId === self::ALL_STORES ? [self::ALL_STORES] : [self::ALL_STORES, $storeId];
    }

    /**
     * Whether a post on $postStoreId shows on the current store view.
     *
     * @param int $postStoreId
     * @return bool
     */
    public function isVisible(int $postStoreId): bool
    {
        return in_array($postStoreId, $this->getVisibleStoreIds(), true);
    }
}
