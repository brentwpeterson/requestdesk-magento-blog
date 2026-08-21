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

class PostManagement implements PostManagementInterface
{
    private const PRODUCT_LINK_TABLE = 'requestdesk_blog_product';

    /**
     * @param PostRepositoryInterface $postRepository
     * @param PostFactory $postFactory
     * @param ResourceConnection $resourceConnection
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly PostRepositoryInterface $postRepository,
        private readonly PostFactory $postFactory,
        private readonly ResourceConnection $resourceConnection,
        private readonly LoggerInterface $logger,
        private readonly PostCategoryResolver $postCategoryResolver
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
            } catch (NoSuchEntityException $e) {
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

        // Save post
        $savedPost = $this->postRepository->save($post);
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
    public function linkProducts(int $postId, array $productIds): bool
    {
        $connection = $this->resourceConnection->getConnection();
        $tableName = $this->resourceConnection->getTableName(self::PRODUCT_LINK_TABLE);

        // Remove existing links
        $connection->delete($tableName, ['post_id = ?' => $postId]);

        if (empty($productIds)) {
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
            } catch (NoSuchEntityException $e) {
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
