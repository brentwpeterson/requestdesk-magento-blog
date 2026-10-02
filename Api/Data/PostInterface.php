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

namespace RequestDesk\Blog\Api\Data;

interface PostInterface
{
    public const POST_ID = 'post_id';
    public const TITLE = 'title';
    public const CONTENT = 'content';
    public const SHORT_DESCRIPTION = 'short_description';
    public const URL_KEY = 'url_key';
    public const META_TITLE = 'meta_title';
    public const META_DESCRIPTION = 'meta_description';
    public const FEATURED_IMAGE = 'featured_image';
    public const STATUS = 'status';
    public const COMMENTS_ENABLED = 'comments_enabled';
    public const AUTHOR = 'author';
    public const AUTHOR_ID = 'author_id';
    public const STORE_ID = 'store_id';
    public const REQUESTDESK_POST_ID = 'requestdesk_post_id';
    public const REQUESTDESK_SYNC_STATUS = 'requestdesk_sync_status';
    public const REQUESTDESK_LAST_SYNC = 'requestdesk_last_sync';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    public const STATUS_DRAFT = 0;
    public const STATUS_PUBLISHED = 1;

    public const SYNC_STATUS_PENDING = 'pending';
    public const SYNC_STATUS_SYNCED = 'synced';
    public const SYNC_STATUS_FAILED = 'failed';

    /**
     * Get post id
     *
     * @return int|null
     */
    public function getPostId(): ?int;

    /**
     * Set post id
     *
     * @param int $postId
     * @return $this
     */
    public function setPostId(int $postId): self;

    /**
     * Get title
     *
     * @return string|null
     */
    public function getTitle(): ?string;

    /**
     * Set title
     *
     * @param string $title
     * @return $this
     */
    public function setTitle(string $title): self;

    /**
     * Get content
     *
     * @return string|null
     */
    public function getContent(): ?string;

    /**
     * Get the author's native admin_user id, or null.
     *
     * @return int|null
     */
    public function getAuthorId(): ?int;

    /**
     * Set the author's native admin_user id.
     *
     * @param int|null $authorId
     * @return self
     */
    public function setAuthorId(?int $authorId): self;

    /**
     * Set content
     *
     * @param string|null $content
     * @return $this
     */
    public function setContent(?string $content): self;

    /**
     * Authored teaser for the listing cards. Null when it was never written.
     *
     * Kept nullable rather than defaulting to an excerpt of the content, so a
     * caller can tell "nobody has written one" from "someone wrote an empty
     * one" - the migration backfill depends on that difference.
     *
     * @return string|null
     */
    public function getShortDescription(): ?string;

    /**
     * Set short description
     *
     * @param string|null $shortDescription
     * @return $this
     */
    public function setShortDescription(?string $shortDescription): self;

    /**
     * Get url key
     *
     * @return string|null
     */
    public function getUrlKey(): ?string;

    /**
     * Set url key
     *
     * @param string $urlKey
     * @return $this
     */
    public function setUrlKey(string $urlKey): self;

    /**
     * Get meta title
     *
     * @return string|null
     */
    public function getMetaTitle(): ?string;

    /**
     * Set meta title
     *
     * @param string|null $metaTitle
     * @return $this
     */
    public function setMetaTitle(?string $metaTitle): self;

    /**
     * Get meta description
     *
     * @return string|null
     */
    public function getMetaDescription(): ?string;

    /**
     * Set meta description
     *
     * @param string|null $metaDescription
     * @return $this
     */
    public function setMetaDescription(?string $metaDescription): self;

    /**
     * Get featured image
     *
     * @return string|null
     */
    public function getFeaturedImage(): ?string;

    /**
     * Set featured image
     *
     * @param string|null $featuredImage
     * @return $this
     */
    public function setFeaturedImage(?string $featuredImage): self;

    /**
     * Get status
     *
     * @return int
     */
    public function getStatus(): int;

    /**
     * Set status
     *
     * @param int $status
     * @return $this
     */
    public function setStatus(int $status): self;

    /**
     * Get comments enabled
     *
     * @return bool
     */
    public function getCommentsEnabled(): bool;

    /**
     * Set comments enabled
     *
     * @param bool $commentsEnabled
     * @return $this
     */
    public function setCommentsEnabled(bool $commentsEnabled): self;

    /**
     * Get author
     *
     * @return string|null
     */
    public function getAuthor(): ?string;

    /**
     * Set author
     *
     * @param string|null $author
     * @return $this
     */
    public function setAuthor(?string $author): self;

    /**
     * Get store id
     *
     * @return int
     */
    public function getStoreId(): int;

    /**
     * Set store id
     *
     * @param int $storeId
     * @return $this
     */
    public function setStoreId(int $storeId): self;

    /**
     * Get requestdesk post id
     *
     * @return string|null
     */
    public function getRequestdeskPostId(): ?string;

    /**
     * Set requestdesk post id
     *
     * @param string|null $requestdeskPostId
     * @return $this
     */
    public function setRequestdeskPostId(?string $requestdeskPostId): self;

    /**
     * Get requestdesk sync status
     *
     * @return string|null
     */
    public function getRequestdeskSyncStatus(): ?string;

    /**
     * Set requestdesk sync status
     *
     * @param string|null $syncStatus
     * @return $this
     */
    public function setRequestdeskSyncStatus(?string $syncStatus): self;

    /**
     * Get requestdesk last sync
     *
     * @return string|null
     */
    public function getRequestdeskLastSync(): ?string;

    /**
     * Set requestdesk last sync
     *
     * @param string|null $lastSync
     * @return $this
     */
    public function setRequestdeskLastSync(?string $lastSync): self;

    /**
     * Get created at
     *
     * @return string|null
     */
    public function getCreatedAt(): ?string;

    /**
     * Set the creation date explicitly.
     *
     * Normally the database default handles this. It is settable because an
     * imported or syndicated post has an original publish date worth keeping;
     * without it every migrated post is stamped with the moment it arrived.
     *
     * @param string|null $createdAt
     * @return $this
     */
    public function setCreatedAt(?string $createdAt): self;

    /**
     * Get updated at
     *
     * @return string|null
     */
    public function getUpdatedAt(): ?string;
}
