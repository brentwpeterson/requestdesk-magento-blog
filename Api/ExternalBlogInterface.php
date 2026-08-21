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

interface ExternalBlogInterface
{
    /**
     * Test API connection
     *
     * @return mixed[]
     */
    public function testConnection(): array;

    /**
     * Create or update a blog post from RequestDesk
     *
     * This is the main endpoint RequestDesk calls to push blog content.
     * Mirrors the Shopify /api/external/create-blog pattern.
     *
     * @param string $title Post title
     * @param string $content Post HTML content
     * @param string|null $slug URL slug (auto-generated if not provided)
     * @param string|null $summary Post excerpt/summary
     * @param string|null $author Author name
     * @param string|null $seoTitle SEO meta title
     * @param string|null $seoDescription SEO meta description
     * @param string|null $featuredImage Featured image URL
     * @param string[]|null $tags Post tags
     * @param bool $published Whether to publish immediately (default: false = draft)
     * @param string|null $requestdeskPostId RequestDesk post ID for sync tracking
     * @param int[]|null $categoryIds Native Magento category IDs to file the post under
     * @param string|null $publishedAt Original publish date (any strtotime-parsable string).
     *                                 Preserves the date on a migrated or syndicated post
     *                                 instead of stamping it with the time it arrived.
     * @return mixed[]
     */
    public function createPost(
        string $title,
        string $content,
        ?string $slug = null,
        ?string $summary = null,
        ?string $author = null,
        ?string $seoTitle = null,
        ?string $seoDescription = null,
        ?string $featuredImage = null,
        ?array $tags = null,
        bool $published = false,
        ?string $requestdeskPostId = null,
        ?array $categoryIds = null,
        ?string $publishedAt = null
    ): array;

    /**
     * Update an existing blog post
     *
     * @param string $postId Magento post ID or RequestDesk post ID
     * @param string|null $title Post title
     * @param string|null $content Post HTML content
     * @param string|null $slug URL slug
     * @param string|null $summary Post excerpt/summary
     * @param string|null $author Author name
     * @param string|null $seoTitle SEO meta title
     * @param string|null $seoDescription SEO meta description
     * @param string|null $featuredImage Featured image URL
     * @param string[]|null $tags Post tags
     * @param bool|null $published Whether post is published
     * @return mixed[]
     */
    public function updatePost(
        string $postId,
        ?string $title = null,
        ?string $content = null,
        ?string $slug = null,
        ?string $summary = null,
        ?string $author = null,
        ?string $seoTitle = null,
        ?string $seoDescription = null,
        ?string $featuredImage = null,
        ?array $tags = null,
        ?bool $published = null,
        ?array $categoryIds = null,
        ?string $publishedAt = null
    ): array;

    /**
     * Delete a blog post
     *
     * @param string $postId Magento post ID or RequestDesk post ID
     * @return mixed[]
     */
    public function deletePost(string $postId): array;

    /**
     * Get a blog post by ID
     *
     * @param string $postId Magento post ID or RequestDesk post ID
     * @return mixed[]
     */
    public function getPost(string $postId): array;

    /**
     * List all blog posts
     *
     * @param int $page Page number
     * @param int $perPage Posts per page
     * @param string|null $status Filter by status (draft, published)
     * @return mixed[]
     */
    public function listPosts(int $page = 1, int $perPage = 20, ?string $status = null): array;
}
