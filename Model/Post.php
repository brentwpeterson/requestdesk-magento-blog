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

use Magento\Framework\Model\AbstractModel;
use RequestDesk\Blog\Api\Data\PostInterface;
use RequestDesk\Blog\Model\ResourceModel\Post as PostResource;

class Post extends AbstractModel implements PostInterface
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'requestdesk_blog_post';

    /**
     * @inheritdoc
     */
    protected function _construct(): void
    {
        $this->_init(PostResource::class);
    }

    /**
     * @inheritdoc
     */
    public function getPostId(): ?int
    {
        $id = $this->getData(self::POST_ID);
        return $id !== null ? (int) $id : null;
    }

    /**
     * @inheritdoc
     */
    public function setPostId(int $postId): PostInterface
    {
        return $this->setData(self::POST_ID, $postId);
    }

    /**
     * @inheritdoc
     */
    public function getTitle(): ?string
    {
        return $this->getData(self::TITLE);
    }

    /**
     * @inheritdoc
     */
    public function setTitle(string $title): PostInterface
    {
        return $this->setData(self::TITLE, $title);
    }

    /**
     * @inheritdoc
     */
    public function getAuthorId(): ?int
    {
        $value = $this->getData(self::AUTHOR_ID);
        return $value === null ? null : (int) $value;
    }

    public function setAuthorId(?int $authorId): PostInterface
    {
        return $this->setData(self::AUTHOR_ID, $authorId);
    }

    /**
     * The "Active" toggle maps to the status column (1 = published, 0 = draft).
     *
     * @return int
     */
    public function getIsActive(): int
    {
        return (int) $this->getData(self::STATUS);
    }

    /**
     * @param int|bool $isActive
     * @return PostInterface
     */
    public function setIsActive($isActive): PostInterface
    {
        return $this->setData(self::STATUS, (int) $isActive);
    }

    public function getContent(): ?string
    {
        return $this->getData(self::CONTENT);
    }

    /**
     * @inheritdoc
     */
    public function setContent(?string $content): PostInterface
    {
        return $this->setData(self::CONTENT, $content);
    }

    /**
     * @inheritdoc
     */
    public function getUrlKey(): ?string
    {
        return $this->getData(self::URL_KEY);
    }

    /**
     * @inheritdoc
     */
    public function setUrlKey(string $urlKey): PostInterface
    {
        return $this->setData(self::URL_KEY, $urlKey);
    }

    /**
     * @inheritdoc
     */
    public function getMetaTitle(): ?string
    {
        return $this->getData(self::META_TITLE);
    }

    /**
     * @inheritdoc
     */
    public function setMetaTitle(?string $metaTitle): PostInterface
    {
        return $this->setData(self::META_TITLE, $metaTitle);
    }

    /**
     * @inheritdoc
     */
    public function getMetaDescription(): ?string
    {
        return $this->getData(self::META_DESCRIPTION);
    }

    /**
     * @inheritdoc
     */
    public function setMetaDescription(?string $metaDescription): PostInterface
    {
        return $this->setData(self::META_DESCRIPTION, $metaDescription);
    }

    /**
     * @inheritdoc
     */
    public function getFeaturedImage(): ?string
    {
        return $this->getData(self::FEATURED_IMAGE);
    }

    /**
     * @inheritdoc
     */
    public function setFeaturedImage(?string $featuredImage): PostInterface
    {
        return $this->setData(self::FEATURED_IMAGE, $featuredImage);
    }

    /**
     * @inheritdoc
     */
    public function getStatus(): int
    {
        return (int) $this->getData(self::STATUS);
    }

    /**
     * @inheritdoc
     */
    public function setStatus(int $status): PostInterface
    {
        return $this->setData(self::STATUS, $status);
    }

    /**
     * @inheritdoc
     *
     * Absent data reads as enabled. A post loaded from a row written before this
     * column existed, or built in memory without it, keeps the old behaviour of
     * comments being on rather than silently losing them.
     */
    public function getCommentsEnabled(): bool
    {
        $value = $this->getData(self::COMMENTS_ENABLED);

        return $value === null ? true : (bool) $value;
    }

    /**
     * @inheritdoc
     */
    public function setCommentsEnabled(bool $commentsEnabled): PostInterface
    {
        return $this->setData(self::COMMENTS_ENABLED, (int) $commentsEnabled);
    }

    /**
     * @inheritdoc
     */
    public function getAuthor(): ?string
    {
        return $this->getData(self::AUTHOR);
    }

    /**
     * @inheritdoc
     */
    public function setAuthor(?string $author): PostInterface
    {
        return $this->setData(self::AUTHOR, $author);
    }

    /**
     * @inheritdoc
     */
    public function getStoreId(): int
    {
        return (int) $this->getData(self::STORE_ID);
    }

    /**
     * @inheritdoc
     */
    public function setStoreId(int $storeId): PostInterface
    {
        return $this->setData(self::STORE_ID, $storeId);
    }

    /**
     * @inheritdoc
     */
    public function getRequestdeskPostId(): ?string
    {
        return $this->getData(self::REQUESTDESK_POST_ID);
    }

    /**
     * @inheritdoc
     */
    public function setRequestdeskPostId(?string $requestdeskPostId): PostInterface
    {
        return $this->setData(self::REQUESTDESK_POST_ID, $requestdeskPostId);
    }

    /**
     * @inheritdoc
     */
    public function getRequestdeskSyncStatus(): ?string
    {
        return $this->getData(self::REQUESTDESK_SYNC_STATUS);
    }

    /**
     * @inheritdoc
     */
    public function setRequestdeskSyncStatus(?string $syncStatus): PostInterface
    {
        return $this->setData(self::REQUESTDESK_SYNC_STATUS, $syncStatus);
    }

    /**
     * @inheritdoc
     */
    public function getRequestdeskLastSync(): ?string
    {
        return $this->getData(self::REQUESTDESK_LAST_SYNC);
    }

    /**
     * @inheritdoc
     */
    public function setRequestdeskLastSync(?string $lastSync): PostInterface
    {
        return $this->setData(self::REQUESTDESK_LAST_SYNC, $lastSync);
    }

    /**
     * @inheritdoc
     */
    public function getCreatedAt(): ?string
    {
        return $this->getData(self::CREATED_AT);
    }

    /**
     * @inheritdoc
     */
    public function setCreatedAt(?string $createdAt): PostInterface
    {
        return $this->setData(self::CREATED_AT, $createdAt);
    }

    /**
     * @inheritdoc
     */
    public function getUpdatedAt(): ?string
    {
        return $this->getData(self::UPDATED_AT);
    }
}
