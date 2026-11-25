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

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;
use RequestDesk\Blog\Api\Data\PostInterface;
use RequestDesk\Blog\Api\PostManagementInterface;
use RequestDesk\Blog\Api\PostRepositoryInterface;
use RequestDesk\Blog\Model\Qa\ContentQaSync;

class PostManagement implements PostManagementInterface
{
    private const PRODUCT_LINK_TABLE = 'requestdesk_blog_product';

    private const VALID_STATUSES = [
        PostInterface::STATUS_DRAFT,
        PostInterface::STATUS_PUBLISHED,
        PostInterface::STATUS_HIDDEN,
        PostInterface::STATUS_SCHEDULED,
    ];

    /**
     * @param PostRepositoryInterface $postRepository
     * @param PostFactory $postFactory
     * @param ResourceConnection $resourceConnection
     * @param LoggerInterface $logger
     * @param PostCategoryResolver $postCategoryResolver
     * @param BlogCache $blogCache
     * @param ContentQaSync $contentQaSync
     */
    public function __construct(
        private readonly PostRepositoryInterface $postRepository,
        private readonly PostFactory $postFactory,
        private readonly ResourceConnection $resourceConnection,
        private readonly LoggerInterface $logger,
        private readonly PostCategoryResolver $postCategoryResolver,
        private readonly BlogCache $blogCache,
        private readonly ContentQaSync $contentQaSync
    ) {
    }

    /**
     * @inheritdoc
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
    ): PostInterface {
        $this->logger->info('RequestDesk Blog: createOrUpdate called', [
            'title' => $title,
            'url_key' => $urlKey,
            'requestdesk_post_id' => $requestdeskPostId
        ]);

        // Try to find existing post by RequestDesk ID
        $post = null;
        if ($requestdeskPostId) {
            try {
                $post = $this->postRepository->getByRequestdeskPostId($requestdeskPostId);
                $this->logger->info('RequestDesk Blog: Found existing post', ['post_id' => $post->getPostId()]);
            } catch (NoSuchEntityException $e) { // phpcs:ignore Magento2.CodeAnalysis.EmptyBlock
                // Post doesn't exist, will create new
            }
        }

        // Create new post if not found
        if (!$post) {
            $post = $this->postFactory->create();
        }

        // Set post data
        $post->setTitle($title)
            ->setContent($content)
            ->setUrlKey($urlKey)
            ->setMetaTitle($metaTitle)
            ->setMetaDescription($metaDescription)
            ->setFeaturedImage($featuredImage)
            ->setStatus($status)
            ->setAuthor($author)
            ->setStoreId($storeId)
            ->setRequestdeskPostId($requestdeskPostId)
            ->setRequestdeskSyncStatus(PostInterface::SYNC_STATUS_SYNCED)
            ->setRequestdeskLastSync(date('Y-m-d H:i:s'));

        // The body's Q&A section moves into the Q&A library (see ContentQaSync).
        $qa = $this->contentQaSync->prepare($post);

        // Save post
        $savedPost = $this->postRepository->save($post);
        $this->contentQaSync->finish((int) $savedPost->getPostId(), $qa);
        $this->logger->info('RequestDesk Blog: Post saved', ['post_id' => $savedPost->getPostId()]);

        // Link products if provided
        if ($productIds !== null && !empty($productIds)) {
            $this->linkProducts((int) $savedPost->getPostId(), $productIds);
        }

        // Categories were accepted and dropped here behind a TODO, so a caller
        // that supplied them got a post with none and no error saying why.
        // syncForPost replaces rather than appends: the caller's list is the
        // post's categories, which is what an idempotent publish needs.
        if ($categoryIds !== null) {
            $this->postCategoryResolver->syncForPost(
                (int) $savedPost->getPostId(),
                array_values(array_filter(array_map('intval', $categoryIds)))
            );
        }

        return $savedPost;
    }

    /**
     * @inheritdoc
     */
    public function updatePost(
        int $postId,
        ?string $title = null,
        ?string $content = null,
        ?string $urlKey = null,
        ?string $metaTitle = null,
        ?string $metaDescription = null,
        ?string $featuredImage = null,
        ?string $featuredImageAlt = null,
        ?int $status = null,
        ?string $author = null,
        ?int $storeId = null,
        ?array $productIds = null,
        ?array $categoryIds = null
    ): PostInterface {
        // getById throws NoSuchEntityException for an unknown id, which the
        // REST layer returns as a 404. Nothing is created here, ever.
        $post = $this->postRepository->getById($postId);

        if ($status !== null && !in_array($status, self::VALID_STATUSES, true)) {
            throw new LocalizedException(
                __('Invalid status: %1. Valid values are: %2', $status, implode(', ', self::VALID_STATUSES))
            );
        }

        // Only the fields the caller sent change. Unlike createOrUpdate, which
        // replaces the whole post, this is a partial update, so it is safe on
        // posts that never came from RequestDesk (no requestdesk_post_id).
        $fields = [
            'title' => $title,
            'content' => $content,
            'url_key' => $urlKey,
            'meta_title' => $metaTitle,
            'meta_description' => $metaDescription,
            'featured_image' => $featuredImage,
            'featured_image_alt' => $featuredImageAlt,
            'status' => $status,
            'author' => $author,
            'store_id' => $storeId,
        ];
        $changed = array_keys(array_filter($fields, static fn ($value) => $value !== null));
        if ($changed === [] && $productIds === null && $categoryIds === null) {
            throw new LocalizedException(__('Nothing to update: send at least one field.'));
        }

        if ($title !== null) {
            $post->setTitle($title);
        }
        if ($content !== null) {
            $post->setContent($content);
        }
        if ($urlKey !== null) {
            $post->setUrlKey($urlKey);
        }
        if ($metaTitle !== null) {
            $post->setMetaTitle($metaTitle);
        }
        if ($metaDescription !== null) {
            $post->setMetaDescription($metaDescription);
        }
        if ($featuredImage !== null) {
            $post->setFeaturedImage($featuredImage);
        }
        if ($featuredImageAlt !== null) {
            $post->setFeaturedImageAlt($featuredImageAlt);
        }
        if ($status !== null) {
            $post->setStatus($status);
        }
        if ($author !== null) {
            $post->setAuthor($author);
        }
        if ($storeId !== null) {
            $post->setStoreId($storeId);
        }

        // A new body may carry a Q&A section; it moves to the library as on create.
        $qa = $content !== null
            ? $this->contentQaSync->prepare($post)
            : ['status' => ContentQaSync::STATUS_NONE, 'qa_ids' => [], 'pairs' => 0];

        $savedPost = $this->postRepository->save($post);
        $savedId = (int) $savedPost->getPostId();
        $this->contentQaSync->finish($savedId, $qa);

        if ($productIds !== null) {
            $this->linkProducts($savedId, $productIds);
        }
        if ($categoryIds !== null) {
            $this->postCategoryResolver->syncForPost(
                $savedId,
                array_values(array_filter(array_map('intval', $categoryIds)))
            );
        }

        $this->logger->info('RequestDesk Blog: Post updated by id', [
            'post_id' => $savedId,
            'fields' => $changed,
            'requestdesk_post_id' => $savedPost->getRequestdeskPostId(),
        ]);

        return $savedPost;
    }

    /**
     * @inheritdoc
     */
    public function linkProducts(int $postId, array $productIds): bool
    {
        $connection = $this->resourceConnection->getConnection();
        $tableName = $this->resourceConnection->getTableName(self::PRODUCT_LINK_TABLE);

        // Remove existing links
        $connection->delete($tableName, ['post_id = ?' => $postId]);

        if (empty($productIds)) {
            $this->blogCache->clean();
            return true;
        }

        // Insert new links
        $data = [];
        $position = 0;
        foreach ($productIds as $productId) {
            $data[] = [
                'post_id' => $postId,
                'product_id' => (int) $productId,
                'position' => $position++
            ];
        }

        $connection->insertMultiple($tableName, $data);
        $this->blogCache->clean();

        $this->logger->info('RequestDesk Blog: Linked products to post', [
            'post_id' => $postId,
            'product_count' => count($productIds)
        ]);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function getLinkedProducts(int $postId): array
    {
        $connection = $this->resourceConnection->getConnection();
        $tableName = $this->resourceConnection->getTableName(self::PRODUCT_LINK_TABLE);

        $select = $connection->select()
            ->from($tableName, ['product_id'])
            ->where('post_id = ?', $postId)
            ->order('position ASC');

        return $connection->fetchCol($select);
    }

    /**
     * @inheritdoc
     */
    public function getPostsByProduct(int $productId): array
    {
        $connection = $this->resourceConnection->getConnection();
        $tableName = $this->resourceConnection->getTableName(self::PRODUCT_LINK_TABLE);

        $select = $connection->select()
            ->from($tableName, ['post_id'])
            ->where('product_id = ?', $productId)
            ->order('position ASC');

        $postIds = $connection->fetchCol($select);

        $posts = [];
        foreach ($postIds as $postId) {
            try {
                $posts[] = $this->postRepository->getById((int) $postId);
            } catch (NoSuchEntityException $e) { // phpcs:ignore Magento2.CodeAnalysis.EmptyBlock
                // Post was deleted, skip
            }
        }

        return $posts;
    }

    /**
     * @inheritdoc
     */
    public function updateSyncStatus(int $postId, string $status): bool
    {
        $validStatuses = [
            PostInterface::SYNC_STATUS_PENDING,
            PostInterface::SYNC_STATUS_SYNCED,
            PostInterface::SYNC_STATUS_FAILED
        ];

        if (!in_array($status, $validStatuses)) {
            throw new LocalizedException(
                __('Invalid sync status: %1. Valid values are: %2', $status, implode(', ', $validStatuses))
            );
        }

        $post = $this->postRepository->getById($postId);
        $post->setRequestdeskSyncStatus($status);

        if ($status === PostInterface::SYNC_STATUS_SYNCED) {
            $post->setRequestdeskLastSync(date('Y-m-d H:i:s'));
        }

        $this->postRepository->save($post);

        return true;
    }
}
