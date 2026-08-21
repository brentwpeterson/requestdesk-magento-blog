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

use RequestDesk\Blog\Api\ExternalBlogInterface;
use RequestDesk\Blog\Api\PostRepositoryInterface;
use RequestDesk\Blog\Api\Data\PostInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Framework\Exception\AuthorizationException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Api\SearchCriteriaBuilderFactory;
use Psr\Log\LoggerInterface;

class ExternalBlog implements ExternalBlogInterface
{
    private const XML_PATH_API_KEY = 'requestdesk_blog/api/api_key';

    /**
     * @param PostRepositoryInterface $postRepository
     * @param PostFactory $postFactory
     * @param StoreManagerInterface $storeManager
     * @param ScopeConfigInterface $scopeConfig
     * @param Request $request
     * @param EncryptorInterface $encryptor
     * @param SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly PostRepositoryInterface $postRepository,
        private readonly PostFactory $postFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly Request $request,
        private readonly EncryptorInterface $encryptor,
        private readonly SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory,
        private readonly LoggerInterface $logger,
        private readonly ApiKeyValidator $apiKeyValidator,
        private readonly TagResolver $tagResolver
    ) {
    }

    /**
     * @throws AuthorizationException
     */
    private function validateApiKey(): void
    {
        $this->apiKeyValidator->validate('External Blog');
    }

    /**
     * Generate URL key from title
     *
     * @param string $title
     * @return string
     */
    private function generateUrlKey(string $title): string
    {
        $urlKey = strtolower($title);
        $urlKey = preg_replace('/[^a-z0-9]+/', '-', $urlKey);
        $urlKey = trim($urlKey, '-');

        if (strlen($urlKey) > 100) {
            $urlKey = substr($urlKey, 0, 100);
            $urlKey = rtrim($urlKey, '-');
        }

        return $urlKey;
    }

    /**
     * Format post for API response
     *
     * @param PostInterface $post
     * @return array
     */
    private function formatPostResponse(PostInterface $post): array
    {
        $store = $this->storeManager->getStore();

        return [
            'id' => $post->getPostId(),
            'title' => $post->getTitle(),
            'content' => $post->getContent(),
            'slug' => $post->getUrlKey(),
            'author' => $post->getAuthor(),
            'seo_title' => $post->getMetaTitle(),
            'seo_description' => $post->getMetaDescription(),
            'featured_image' => $post->getFeaturedImage(),
            'status' => $post->getStatus() == PostInterface::STATUS_PUBLISHED ? 'published' : 'draft',
            'requestdesk_post_id' => $post->getRequestdeskPostId(),
            'sync_status' => $post->getRequestdeskSyncStatus(),
            'last_sync' => $post->getRequestdeskLastSync(),
            'created_at' => $post->getCreatedAt(),
            'updated_at' => $post->getUpdatedAt(),
            'url' => $store->getBaseUrl() . 'blog/post/' . $post->getUrlKey()
        ];
    }

    /**
     * @inheritDoc
     */
    public function testConnection(): array
    {
        $this->validateApiKey();

        $store = $this->storeManager->getStore();

        return [
            'success' => true,
            'message' => 'External Blog API connection successful',
            'store' => [
                'url' => $store->getBaseUrl(),
                'name' => $store->getName(),
                'code' => $store->getCode()
            ],
            'endpoints' => [
                'create' => 'POST /V1/requestdesk/external/blog/posts',
                'update' => 'PUT /V1/requestdesk/external/blog/posts/:postId',
                'delete' => 'DELETE /V1/requestdesk/external/blog/posts/:postId',
                'get' => 'GET /V1/requestdesk/external/blog/posts/:postId',
                'list' => 'GET /V1/requestdesk/external/blog/posts'
            ]
        ];
    }

    /**
     * @inheritDoc
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
        ?string $requestdeskPostId = null
    ): array {
        $this->validateApiKey();

        $this->logger->info('RequestDesk External Blog: Creating post', [
            'title' => $title,
            'requestdesk_post_id' => $requestdeskPostId
        ]);

        try {
            // Check if post with this RequestDesk ID already exists
            if ($requestdeskPostId) {
                try {
                    $existingPost = $this->postRepository->getByRequestdeskPostId($requestdeskPostId);
                    // Post exists, update instead
                    return $this->updateExistingPost($existingPost, [
                        'title' => $title,
                        'content' => $content,
                        'slug' => $slug,
                        'summary' => $summary,
                        'author' => $author,
                        'seo_title' => $seoTitle,
                        'seo_description' => $seoDescription,
                        'featured_image' => $featuredImage,
                        'tags' => $tags,
                        'published' => $published
                    ]);
                } catch (NoSuchEntityException $e) {
                    // Post doesn't exist, continue with creation
                }
            }

            // Create new post
            $post = $this->postFactory->create();

            $urlKey = $slug ?: $this->generateUrlKey($title);

            $post->setTitle($title)
                ->setContent($content)
                ->setUrlKey($urlKey)
                ->setMetaTitle($seoTitle ?: $title)
                ->setMetaDescription($seoDescription ?: ($summary ?: substr(strip_tags($content), 0, 160)))
                ->setFeaturedImage($featuredImage)
                ->setAuthor($author ?: 'RequestDesk')
                ->setStatus($published ? PostInterface::STATUS_PUBLISHED : PostInterface::STATUS_DRAFT)
                ->setStoreId((int) $this->storeManager->getStore()->getId())
                ->setRequestdeskPostId($requestdeskPostId)
                ->setRequestdeskSyncStatus(PostInterface::SYNC_STATUS_SYNCED)
                ->setRequestdeskLastSync(date('Y-m-d H:i:s'));

            $savedPost = $this->postRepository->save($post);

            $this->syncTags((int) $savedPost->getPostId(), $tags);

            $this->logger->info('RequestDesk External Blog: Post created', [
                'post_id' => $savedPost->getPostId(),
                'requestdesk_post_id' => $requestdeskPostId
            ]);

            return [
                'success' => true,
                'message' => 'Post created successfully',
                'post' => $this->formatPostResponse($savedPost)
            ];

        } catch (\Exception $e) {
            $this->logger->error('RequestDesk External Blog: Create failed - ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Attach tags to a post, creating any that do not exist yet.
     *
     * The API has always accepted a tags array and, until now, thrown it away:
     * neither create nor update persisted it, so every post published through
     * RequestDesk arrived untagged with no error to explain why. Null means "not
     * supplied, leave alone"; an empty array means "clear them".
     *
     * @param int $postId
     * @param string[]|null $tags
     * @return void
     */
    private function syncTags(int $postId, ?array $tags): void
    {
        if ($tags === null) {
            return;
        }

        $tagIds = [];
        foreach ($tags as $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }
            $tagId = $this->tagResolver->getOrCreateByName($name);
            if ($tagId) {
                $tagIds[] = $tagId;
            }
        }

        $this->tagResolver->syncForPost($postId, $tagIds);
    }

    /**
     * Update existing post helper
     *
     * @param PostInterface $post
     * @param array $data
     * @return array
     */
    private function updateExistingPost(PostInterface $post, array $data): array
    {
        if (isset($data['title'])) {
            $post->setTitle($data['title']);
        }
        if (isset($data['content'])) {
            $post->setContent($data['content']);
        }
        if (isset($data['slug'])) {
            $post->setUrlKey($data['slug']);
        }
        if (isset($data['author'])) {
            $post->setAuthor($data['author']);
        }
        if (isset($data['seo_title'])) {
            $post->setMetaTitle($data['seo_title']);
        }
        if (isset($data['seo_description'])) {
            $post->setMetaDescription($data['seo_description']);
        }
        if (isset($data['featured_image'])) {
            $post->setFeaturedImage($data['featured_image']);
        }
        if (isset($data['published'])) {
            $post->setStatus($data['published'] ? PostInterface::STATUS_PUBLISHED : PostInterface::STATUS_DRAFT);
        }

        $post->setRequestdeskSyncStatus(PostInterface::SYNC_STATUS_SYNCED);
        $post->setRequestdeskLastSync(date('Y-m-d H:i:s'));

        $savedPost = $this->postRepository->save($post);

        $this->syncTags((int) $savedPost->getPostId(), $data['tags'] ?? null);

        $this->logger->info('RequestDesk External Blog: Post updated', [
            'post_id' => $savedPost->getPostId()
        ]);

        return [
            'success' => true,
            'message' => 'Post updated successfully',
            'post' => $this->formatPostResponse($savedPost)
        ];
    }

    /**
     * @inheritDoc
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
        ?bool $published = null
    ): array {
        $this->validateApiKey();

        $this->logger->info('RequestDesk External Blog: Updating post', ['post_id' => $postId]);

        try {
            // Try to find by Magento ID first, then by RequestDesk ID
            try {
                $post = $this->postRepository->getById((int) $postId);
            } catch (NoSuchEntityException $e) {
                $post = $this->postRepository->getByRequestdeskPostId($postId);
            }

            return $this->updateExistingPost($post, [
                'title' => $title,
                'content' => $content,
                'slug' => $slug,
                'summary' => $summary,
                'author' => $author,
                'seo_title' => $seoTitle,
                'seo_description' => $seoDescription,
                'featured_image' => $featuredImage,
                'tags' => $tags,
                'published' => $published
            ]);

        } catch (NoSuchEntityException $e) {
            return [
                'success' => false,
                'error' => 'Post not found: ' . $postId
            ];
        } catch (\Exception $e) {
            $this->logger->error('RequestDesk External Blog: Update failed - ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * @inheritDoc
     */
    public function deletePost(string $postId): array
    {
        $this->validateApiKey();

        $this->logger->info('RequestDesk External Blog: Deleting post', ['post_id' => $postId]);

        try {
            // Try to find by Magento ID first, then by RequestDesk ID
            try {
                $post = $this->postRepository->getById((int) $postId);
            } catch (NoSuchEntityException $e) {
                $post = $this->postRepository->getByRequestdeskPostId($postId);
            }

            $this->postRepository->delete($post);

            $this->logger->info('RequestDesk External Blog: Post deleted', ['post_id' => $postId]);

            return [
                'success' => true,
                'message' => 'Post deleted successfully'
            ];

        } catch (NoSuchEntityException $e) {
            return [
                'success' => false,
                'error' => 'Post not found: ' . $postId
            ];
        } catch (\Exception $e) {
            $this->logger->error('RequestDesk External Blog: Delete failed - ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * @inheritDoc
     */
    public function getPost(string $postId): array
    {
        $this->validateApiKey();

        try {
            // Try to find by Magento ID first, then by RequestDesk ID
            try {
                $post = $this->postRepository->getById((int) $postId);
            } catch (NoSuchEntityException $e) {
                $post = $this->postRepository->getByRequestdeskPostId($postId);
            }

            return [
                'success' => true,
                'post' => $this->formatPostResponse($post)
            ];

        } catch (NoSuchEntityException $e) {
            return [
                'success' => false,
                'error' => 'Post not found: ' . $postId
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * @inheritDoc
     */
    public function listPosts(int $page = 1, int $perPage = 20, ?string $status = null): array
    {
        $this->validateApiKey();

        try {
            $searchCriteriaBuilder = $this->searchCriteriaBuilderFactory->create();

            if ($status === 'published') {
                $searchCriteriaBuilder->addFilter('status', PostInterface::STATUS_PUBLISHED);
            } elseif ($status === 'draft') {
                $searchCriteriaBuilder->addFilter('status', PostInterface::STATUS_DRAFT);
            }

            $searchCriteriaBuilder->setPageSize($perPage);
            $searchCriteriaBuilder->setCurrentPage($page);

            $searchCriteria = $searchCriteriaBuilder->create();
            $result = $this->postRepository->getList($searchCriteria);

            $posts = [];
            foreach ($result->getItems() as $post) {
                $posts[] = $this->formatPostResponse($post);
            }

            return [
                'success' => true,
                'posts' => $posts,
                'total' => $result->getTotalCount(),
                'page' => $page,
                'per_page' => $perPage,
                'has_more' => ($page * $perPage) < $result->getTotalCount()
            ];

        } catch (\Exception $e) {
            $this->logger->error('RequestDesk External Blog: List failed - ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'posts' => []
            ];
        }
    }
}
