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
     * @return int|null
     */
    public function getPostId(): ?int;

    /**
     * @param int $postId
     * @return $this
     */
    public function setPostId(int $postId): self;

    /**
     * @return string|null
     */
    public function getTitle(): ?string;

    /**
     * @param string $title
     * @return $this
     */
    public function setTitle(string $title): self;

    /**
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
     * @param string|null $content
     * @return $this
     */
    public function setContent(?string $content): self;

    /**
     * @return string|null
     */
    public function getUrlKey(): ?string;

    /**
     * @param string $urlKey
     * @return $this
     */
    public function setUrlKey(string $urlKey): self;

    /**
     * @return string|null
     */
    public function getMetaTitle(): ?string;

    /**
     * @param string|null $metaTitle
     * @return $this
     */
    public function setMetaTitle(?string $metaTitle): self;

    /**
     * @return string|null
     */
    public function getMetaDescription(): ?string;

    /**
     * @param string|null $metaDescription
     * @return $this
     */
    public function setMetaDescription(?string $metaDescription): self;

    /**
     * @return string|null
     */
    public function getFeaturedImage(): ?string;

    /**
     * @param string|null $featuredImage
     * @return $this
     */
    public function setFeaturedImage(?string $featuredImage): self;

    /**
     * @return int
     */
    public function getStatus(): int;

    /**
     * @param int $status
     * @return $this
     */
    public function setStatus(int $status): self;

    /**
     * @return bool
     */
    public function getCommentsEnabled(): bool;

    /**
     * @param bool $commentsEnabled
     * @return $this
     */
    public function setCommentsEnabled(bool $commentsEnabled): self;

    /**
     * @return string|null
     */
    public function getAuthor(): ?string;

    /**
     * @param string|null $author
     * @return $this
     */
    public function setAuthor(?string $author): self;

    /**
     * @return int
     */
    public function getStoreId(): int;

    /**
     * @param int $storeId
     * @return $this
     */
    public function setStoreId(int $storeId): self;

    /**
     * @return string|null
     */
    public function getRequestdeskPostId(): ?string;

    /**
     * @param string|null $requestdeskPostId
     * @return $this
     */
    public function setRequestdeskPostId(?string $requestdeskPostId): self;

    /**
     * @return string|null
     */
    public function getRequestdeskSyncStatus(): ?string;

    /**
     * @param string|null $syncStatus
     * @return $this
     */
    public function setRequestdeskSyncStatus(?string $syncStatus): self;

    /**
     * @return string|null
     */
    public function getRequestdeskLastSync(): ?string;

    /**
     * @param string|null $lastSync
     * @return $this
     */
    public function setRequestdeskLastSync(?string $lastSync): self;

    /**
     * @return string|null
     */
    public function getCreatedAt(): ?string;

    /**
     * @return string|null
     */
    public function getUpdatedAt(): ?string;
}
