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

namespace RequestDesk\Blog\Api;

use Magento\Framework\Exception\LocalizedException;
use RequestDesk\Blog\Api\Data\PostInterface;

interface PostManagementInterface
{
    /**
     * Create or update a blog post from RequestDesk
     *
     * This is the main endpoint RequestDesk calls to push blog content.
     *
     * @param string $title Post title
     * @param string $content Post HTML content
     * @param string $urlKey URL key/slug
     * @param string|null $metaTitle SEO meta title
     * @param string|null $metaDescription SEO meta description
     * @param string|null $featuredImage Featured image URL
     * @param int $status Status (0=draft, 1=published)
     * @param string|null $author Author name
     * @param int $storeId Store ID (0 for all stores)
     * @param string|null $requestdeskPostId RequestDesk post ID for sync
     * @param int[]|null $productIds Array of product IDs to link
     * @param int[]|null $categoryIds Blog category IDs (requestdesk_blog_category) to assign; unknown IDs are ignored
     * @return PostInterface
     * @throws LocalizedException
     */
    public function createOrUpdate(
        string $title,
        string $content,
        string $urlKey,
        ?string $metaTitle = null,
        ?string $metaDescription = null,
        ?string $featuredImage = null,
        int $status = 0,
        ?string $author = null,
        int $storeId = 0,
        ?string $requestdeskPostId = null,
        ?array $productIds = null,
        ?array $categoryIds = null
    ): PostInterface;

    /**
     * Link products to a blog post
     *
     * @param int $postId Blog post ID
     * @param int[] $productIds Array of product entity IDs
     * @return bool
     * @throws LocalizedException
     */
    public function linkProducts(int $postId, array $productIds): bool;

    /**
     * Get products linked to a blog post
     *
     * @param int $postId Blog post ID
     * @return int[] Array of product entity IDs
     */
    public function getLinkedProducts(int $postId): array;

    /**
     * Get blog posts linked to a product
     *
     * @param int $productId Product entity ID
     * @return PostInterface[]
     */
    public function getPostsByProduct(int $productId): array;

    /**
     * Update sync status for a post
     *
     * @param int $postId Blog post ID
     * @param string $status Sync status (synced/pending/failed)
     * @return bool
     * @throws LocalizedException
     */
    public function updateSyncStatus(int $postId, string $status): bool;
}
