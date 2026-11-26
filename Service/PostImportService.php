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

// phpcs:disable Generic.Files.LineLength.TooLong

namespace RequestDesk\Blog\Service;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use RequestDesk\Blog\Api\PostRepositoryInterface;
use RequestDesk\Blog\Api\Data\PostInterface;
use RequestDesk\Blog\Model\PostCategoryResolver;
use RequestDesk\Blog\Model\PostFactory;
use RequestDesk\Blog\Model\Qa\ContentQaSync;
use RequestDesk\Blog\Model\TagResolver;

class PostImportService
{
    private const XML_PATH_API_KEY = 'requestdesk_blog/api/api_key';
    private const XML_PATH_ENDPOINT_URL = 'requestdesk_blog/api/endpoint_url';

    /**
     * RequestDesk moved this API. The posts router is mounted at /api/public-posts
     * and authenticates with an X-API-Key header. This module was still calling
     * /api/public/posts with an x-requestdesk-api-key header - both gone, which is
     * why every call came back 404 and the integration was silently dead.
     */
    private const PATH_POSTS = '/api/public-posts/';
    private const HEADER_API_KEY = 'X-API-Key';

    /**
     * Seconds to wait for a connection, and for the whole request. Short enough
     * that an unreachable endpoint reports back long before PHP's own limit.
     */
    private const CONNECT_TIMEOUT = 5;
    private const REQUEST_TIMEOUT = 30;

    /**
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * @var EncryptorInterface
     */
    private EncryptorInterface $encryptor;

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @var Curl
     */
    private Curl $curl;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var PostRepositoryInterface
     */
    private PostRepositoryInterface $postRepository;

    /**
     * @var PostFactory
     */
    private PostFactory $postFactory;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param EncryptorInterface $encryptor
     * @param StoreManagerInterface $storeManager
     * @param Curl $curl
     * @param LoggerInterface $logger
     * @param PostRepositoryInterface $postRepository
     * @param PostFactory $postFactory
     * @param ResourceConnection $resource
     * @param TagResolver $tagResolver
     * @param ContentQaSync $contentQaSync
     * @param PostCategoryResolver $postCategoryResolver
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        EncryptorInterface $encryptor,
        StoreManagerInterface $storeManager,
        Curl $curl,
        LoggerInterface $logger,
        PostRepositoryInterface $postRepository,
        PostFactory $postFactory,
        private readonly ResourceConnection $resource,
        private readonly TagResolver $tagResolver,
        private readonly ContentQaSync $contentQaSync,
        private readonly PostCategoryResolver $postCategoryResolver
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->encryptor = $encryptor;
        $this->storeManager = $storeManager;
        $this->curl = $curl;
        $this->logger = $logger;
        $this->postRepository = $postRepository;
        $this->postFactory = $postFactory;
    }

    /**
     * Import posts from RequestDesk
     *
     * @param string|null $status Filter by status (draft, publish, pending)
     * @param string|null $syncStatus Filter by sync status (not_synced, synced, pending, failed)
     * @param int $page Page number
     * @param int $perPage Items per page
     * @return array Result with success status and details
     */
    public function importPosts(
        ?string $status = 'publish',
        ?string $syncStatus = null,
        int $page = 1,
        int $perPage = 20
    ): array {
        try {
            $this->logger->info('RequestDesk: Starting post import');

            // Validate API configuration
            $apiKey = $this->getApiKey();
            $endpointUrl = $this->getEndpointUrl();

            if (empty($apiKey) || empty($endpointUrl)) {
                return [
                    'success' => false,
                    'error' => 'RequestDesk API not configured. Please set API Key and Endpoint URL in Store > Configuration > RequestDesk > Blog.'
                ];
            }

            // Fetch posts from RequestDesk
            $postsData = $this->fetchPostsFromRequestDesk($status, $syncStatus, $page, $perPage);

            if (!$postsData['success']) {
                return $postsData;
            }

            $posts = $postsData['posts'] ?? [];
            $imported = 0;
            $updated = 0;
            $unchanged = 0;
            $failed = 0;
            $errors = [];
            $warnings = [];

            foreach ($posts as $listedPost) {
                $postData = $listedPost;
                try {
                    // RequestDesk ignores sync_status, so the list holds every
                    // published post on every run. A post already imported and
                    // not edited in RequestDesk since is left alone, so a manual
                    // edit in the Magento admin survives the hourly run.
                    if ($this->isUnchangedSinceLastImport($listedPost)) {
                        $unchanged++;
                        continue;
                    }

                    // The list call carries no body. Importing from it saved
                    // every post with an empty body, over the existing one on
                    // update. The single-post call has the content.
                    $postData = $this->fetchFullPost($listedPost);
                    $result = $this->importSinglePost($postData);
                    if ($result['qa_status'] === ContentQaSync::STATUS_QA_MODULE_MISSING) {
                        $warnings[] = "Post {$postData['id']}: has a Q&A section but no FAQ schema, "
                            . 'because RequestDesk_Qa is not installed (composer require requestdesk/magento-qa).';
                    }
                    if ($result['created']) {
                        $imported++;
                    } else {
                        $updated++;
                    }

                    // Report sync success back to RequestDesk
                    $this->reportSyncStatus(
                        $postData['id'],
                        $result['magento_post_id'],
                        'synced'
                    );

                } catch (\Exception $e) {
                    $failed++;
                    $errors[] = "Post {$postData['id']}: " . $e->getMessage();
                    $this->logger->error("RequestDesk: Failed to import post {$postData['id']} - " . $e->getMessage());

                    // Report sync failure back to RequestDesk
                    $this->reportSyncStatus(
                        $postData['id'],
                        null,
                        'failed',
                        $e->getMessage()
                    );
                }
            }

            $this->logger->info(
                "RequestDesk: Import complete - {$imported} created, {$updated} updated, "
                . "{$unchanged} unchanged, {$failed} failed"
            );

            $message = "Import complete: {$imported} created, {$updated} updated, "
                . "{$unchanged} unchanged, {$failed} failed";
            if ($warnings !== []) {
                $message .= '. ' . count($warnings) . ' post(s) imported without FAQ schema: RequestDesk_Qa is not installed.';
            }

            return [
                'success' => true,
                'message' => $message,
                'total_fetched' => count($posts),
                'imported' => $imported,
                'updated' => $updated,
                'unchanged' => $unchanged,
                'failed' => $failed,
                'has_more' => $postsData['has_more'] ?? false,
                'errors' => $errors,
                'warnings' => $warnings
            ];

        } catch (\Exception $e) {
            $this->logger->error('RequestDesk: Post import failed - ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Fetch posts from RequestDesk API
     *
     * @param string|null $status
     * @param string|null $syncStatus
     * @param int $page
     * @param int $perPage
     * @return array
     */
    private function fetchPostsFromRequestDesk(
        ?string $status,
        ?string $syncStatus,
        int $page,
        int $perPage
    ): array {
        $apiKey = $this->getApiKey();
        $endpointUrl = rtrim($this->getEndpointUrl(), '/');

        // Build query parameters
        $queryParams = [
            'page' => $page,
            'per_page' => $perPage,
            'platform' => 'magento'
        ];

        if ($status) {
            $queryParams['status'] = $status;
        }

        if ($syncStatus) {
            $queryParams['sync_status'] = $syncStatus;
        }

        $url = $endpointUrl . self::PATH_POSTS . '?' . http_build_query($queryParams);

        $this->logger->info("RequestDesk: Fetching posts from {$url}");

        try {
            $this->applyTimeouts();
            $this->curl->setHeaders([
                'Content-Type' => 'application/json',
                self::HEADER_API_KEY => $apiKey
            ]);

            $this->curl->get($url);

            $responseCode = $this->curl->getStatus();
            $responseBody = $this->curl->getBody();

            if ($responseCode >= 200 && $responseCode < 300) {
                $response = json_decode($responseBody, true);

                $this->logger->info("RequestDesk: Fetched {$response['total']} posts (page {$page})");

                return [
                    'success' => true,
                    'posts' => $response['posts'] ?? [],
                    'total' => $response['total'] ?? 0,
                    'has_more' => $response['has_more'] ?? false
                ];
            } else {
                $error = json_decode($responseBody, true);
                return [
                    'success' => false,
                    'error' => $error['detail'] ?? "HTTP {$responseCode}: {$responseBody}"
                ];
            }

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to fetch posts: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Import a single post into Magento
     *
     * @param array $postData Post data from RequestDesk API
     * @return array Result with created flag and magento_post_id
     */
    private function importSinglePost(array $postData): array
    {
        $requestdeskPostId = $postData['id'];
        $created = false;

        // Check if post already exists
        try {
            $post = $this->postRepository->getByRequestdeskPostId($requestdeskPostId);
            $this->logger->info("RequestDesk: Updating existing post for {$requestdeskPostId}");
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            // Create new post
            $post = $this->postFactory->create();
            $created = true;
            $this->logger->info("RequestDesk: Creating new post for {$requestdeskPostId}");
        }

        // A post is never saved without a body. An empty body here means the
        // payload is incomplete, and saving it would wipe the post on update.
        $content = (string) ($postData['content'] ?? '');
        if (trim($content) === '') {
            throw new \RuntimeException('RequestDesk sent no body for this post, so it was not imported.');
        }

        // Map RequestDesk fields to Magento fields
        $post->setTitle($postData['title'] ?? 'Untitled');
        $post->setContent($content);
        $post->setUrlKey($this->generateUrlKey($postData['slug'] ?? $postData['title']));
        $post->setMetaTitle($postData['seo_title'] ?? $postData['title']);
        $post->setMetaDescription($postData['seo_description'] ?? substr(strip_tags($postData['content'] ?? ''), 0, 160));
        $post->setFeaturedImage($postData['featured_image'] ?? null);
        $post->setFeaturedImageAlt($postData['featured_image_alt'] ?? null);

        // Author: keep the incoming name as the free-text byline (fallback),
        // and additionally resolve it to a native admin_user when one matches
        // so the imported post gets the author page + profile.
        // RequestDesk's public posts API sends the byline as author_name; the
        // old 'author' key never arrived, so every import was bylined
        // "RequestDesk" (2026-11-25, Evrig).
        $authorName = trim((string) ($postData['author_name'] ?? $postData['author'] ?? ''));
        if ($authorName === '') {
            $authorName = 'RequestDesk';
        }
        $post->setAuthor($authorName);
        $post->setAuthorId($this->resolveAuthorId($authorName));

        $post->setStoreId((int) $this->storeManager->getStore()->getId());

        // Set status based on RequestDesk status
        $status = $postData['status'] === 'publish'
            ? PostInterface::STATUS_PUBLISHED
            : PostInterface::STATUS_DRAFT;
        $post->setStatus($status);

        // Set RequestDesk tracking fields
        $post->setRequestdeskPostId($requestdeskPostId);
        $post->setRequestdeskSyncStatus(PostInterface::SYNC_STATUS_SYNCED);
        $post->setRequestdeskLastSync(gmdate('Y-m-d H:i:s'));

        // Q&A: the body's Q&A section moves into the Q&A library so the post
        // gets FAQPage schema and one visible FAQ block from the linked pairs.
        // Pairs are stored before the save, with the body still intact.
        $qa = $this->contentQaSync->prepare($post);

        // Save the post
        $savedPost = $this->postRepository->save($post);
        $savedId = (int) $savedPost->getPostId();

        $qa = $this->contentQaSync->finish($savedId, $qa);

        // Tags and categories: incoming names become real entities (created
        // when missing) and are linked to the post. RequestDesk sends each as
        // a {name, slug} object when the company has that taxonomy and as a
        // plain name otherwise; the import used to accept only plain names,
        // so tags were dropped and categories were never read at all.
        // Links change only when the post carries at least one, so a post
        // with none in RequestDesk keeps what was set in the Magento admin.
        $tags = $this->taxonomyTerms($postData['tags'] ?? null);
        if ($tags !== []) {
            $tagIds = [];
            foreach ($tags as $term) {
                $tagId = $this->tagResolver->getOrCreateByName($term['name'], $term['slug']);
                if ($tagId) {
                    $tagIds[] = $tagId;
                }
            }
            $this->tagResolver->syncForPost($savedId, $tagIds);
        }

        $categories = $this->taxonomyTerms($postData['categories'] ?? null);
        if ($categories !== []) {
            $categoryIds = [];
            foreach ($categories as $term) {
                $categoryIds[] = $this->postCategoryResolver->getOrCreateByName($term['name'], $term['slug']);
            }
            $this->postCategoryResolver->syncForPost($savedId, $categoryIds);
        }

        return [
            'created' => $created,
            'magento_post_id' => $savedPost->getPostId(),
            'qa_status' => $qa['status'],
            'qa_pairs' => $qa['pairs']
        ];
    }

    /**
     * The full post, body included, from RequestDesk's single-post call.
     *
     * @param array $listedPost A row from the list call, which has no body
     * @return array
     * @throws \RuntimeException When the post cannot be fetched or has no body
     */
    private function fetchFullPost(array $listedPost): array
    {
        $slug = trim((string) ($listedPost['slug'] ?? ''));
        if ($slug === '') {
            throw new \RuntimeException('RequestDesk listed this post with no slug, so its body cannot be fetched.');
        }

        $url = rtrim((string) $this->getEndpointUrl(), '/') . self::PATH_POSTS . rawurlencode($slug);

        $this->applyTimeouts();
        $this->curl->setHeaders([
            'Content-Type' => 'application/json',
            self::HEADER_API_KEY => (string) $this->getApiKey()
        ]);
        $this->curl->get($url);

        $status = (int) $this->curl->getStatus();
        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException("Fetching the post body failed with HTTP {$status}.");
        }

        $response = json_decode((string) $this->curl->getBody(), true);
        $post = is_array($response) ? ($response['post'] ?? null) : null;
        if (!is_array($post)) {
            throw new \RuntimeException('RequestDesk returned no post for slug "' . $slug . '".');
        }
        if ((string) ($post['id'] ?? '') !== (string) ($listedPost['id'] ?? '')) {
            throw new \RuntimeException('RequestDesk returned a different post for slug "' . $slug . '".');
        }

        return $post;
    }

    /**
     * Whether this post was imported before and RequestDesk has not changed
     * it since. A post whose stored body is empty is never unchanged, so the
     * next run repairs posts an earlier version saved without a body.
     *
     * @param array $listedPost
     * @return bool
     */
    private function isUnchangedSinceLastImport(array $listedPost): bool
    {
        $modifiedAt = strtotime((string) ($listedPost['modified_at'] ?? '') . ' UTC');
        if ($modifiedAt === false || empty($listedPost['modified_at'])) {
            return false;
        }

        try {
            $existing = $this->postRepository->getByRequestdeskPostId((string) $listedPost['id']);
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            return false;
        }

        if (trim((string) $existing->getContent()) === '') {
            return false;
        }

        $lastSync = strtotime((string) $existing->getRequestdeskLastSync() . ' UTC');

        return $lastSync !== false && $lastSync >= $modifiedAt;
    }

    /**
     * Names and slugs from a RequestDesk tags or categories array, which holds
     * {name, slug} objects or plain names. A slug that is not a clean URL key
     * is dropped, so the URL key is made from the name instead.
     *
     * @param mixed $items
     * @return array<int, array{name:string, slug:?string}>
     */
    private function taxonomyTerms($items): array
    {
        if (!is_array($items)) {
            return [];
        }

        $terms = [];
        foreach ($items as $item) {
            $name = is_array($item) ? ($item['name'] ?? '') : $item;
            $slug = is_array($item) ? ($item['slug'] ?? null) : null;
            if (!is_string($name) || trim($name) === '') {
                continue;
            }
            $slug = is_string($slug) && preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) ? $slug : null;
            $terms[] = ['name' => trim($name), 'slug' => $slug];
        }

        return $terms;
    }

    /**
     * Resolve an incoming author name to a native admin_user id by matching the
     * full name ("First Last") or username, case-insensitively. Returns null
     * when there is no match, in which case the free-text byline stands.
     *
     * @param string $name
     * @return int|null
     */
    private function resolveAuthorId(string $name): ?int
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('admin_user');
        $fullName = new \Zend_Db_Expr("TRIM(CONCAT(COALESCE(firstname,''),' ',COALESCE(lastname,'')))");
        $select = $connection->select()
            ->from($table, ['user_id'])
            ->where('LOWER(' . $fullName . ') = ?', mb_strtolower($name))
            ->orWhere('LOWER(username) = ?', mb_strtolower($name))
            ->limit(1);

        $userId = (int) $connection->fetchOne($select);
        return $userId ?: null;
    }

    /**
     * Generate URL key from title or slug
     *
     * @param string $input
     * @return string
     */
    private function generateUrlKey(string $input): string
    {
        // Convert to lowercase
        $urlKey = strtolower($input);

        // Replace non-alphanumeric characters with hyphens
        $urlKey = preg_replace('/[^a-z0-9]+/', '-', $urlKey);

        // Remove leading/trailing hyphens
        $urlKey = trim($urlKey, '-');

        // Limit length
        if (strlen($urlKey) > 100) {
            $urlKey = substr($urlKey, 0, 100);
            $urlKey = rtrim($urlKey, '-');
        }

        return $urlKey;
    }

    /**
     * Report sync status back to RequestDesk
     *
     * @param string $requestdeskPostId
     * @param int|null $magentoPostId
     * @param string $status
     * @param string|null $errorMessage
     * @return bool
     */
    private function reportSyncStatus(
        string $requestdeskPostId,
        ?int $magentoPostId,
        string $status,
        ?string $errorMessage = null
    ): bool {
        // RequestDesk retired /api/public/posts/{id}/sync-status along with the rest
        // of that route tree. Calling it now only ever 404s - and because this runs
        // inside the per-post try block, that failure would mark a post which
        // imported perfectly as failed. Reporting back is a nice-to-have; importing
        // is the job. Restore this when RequestDesk offers a replacement route.
        $this->logger->debug(sprintf(
            'RequestDesk: sync-status not reported for post %s (%s) - endpoint retired upstream',
            $requestdeskPostId,
            $status
        ));

        return false;
    }

    /**
     * Get posts related to a product using RAG semantic search
     *
     * This uses the RequestDesk RAG knowledge base to find posts
     * semantically related to the product. Products synced to
     * RequestDesk are automatically searchable.
     *
     * @param string $productQuery Product name, SKU, or description
     * @param int $maxResults Maximum number of posts to return
     * @return array
     */
    public function getRelatedPosts(string $productQuery, int $maxResults = 5): array
    {
        try {
            $this->logger->info("RequestDesk: Getting related posts for '{$productQuery}'");

            $apiKey = $this->getApiKey();
            $endpointUrl = rtrim($this->getEndpointUrl(), '/');

            if (empty($apiKey) || empty($endpointUrl)) {
                return [
                    'success' => false,
                    'error' => 'RequestDesk API not configured',
                    'posts' => []
                ];
            }

            // Build request URL with query parameters
            $url = $endpointUrl . '/api/public/posts/related?' . http_build_query([
                'query' => $productQuery,
                'max_results' => $maxResults
            ]);

            $this->applyTimeouts();
            $this->curl->setHeaders([
                'Content-Type' => 'application/json',
                self::HEADER_API_KEY => $apiKey
            ]);

            $this->curl->post($url, '{}');

            $responseCode = $this->curl->getStatus();
            $responseBody = $this->curl->getBody();

            if ($responseCode >= 200 && $responseCode < 300) {
                $response = json_decode($responseBody, true);

                $this->logger->info(
                    "RequestDesk: Found {$response['total']} related posts " .
                    "(confidence: {$response['confidence']})"
                );

                return [
                    'success' => true,
                    'posts' => $response['posts'] ?? [],
                    'total' => $response['total'] ?? 0,
                    'confidence' => $response['confidence'] ?? 0.0,
                    'query' => $productQuery
                ];
            } else {
                $error = json_decode($responseBody, true);
                return [
                    'success' => false,
                    'error' => $error['detail'] ?? "HTTP {$responseCode}",
                    'posts' => []
                ];
            }

        } catch (\Exception $e) {
            $this->logger->error("RequestDesk: Related posts error - " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'posts' => []
            ];
        }
    }

    /**
     * Test posts API connection
     *
     * @return array
     */
    public function testConnection(): array
    {
        try {
            $apiKey = $this->getApiKey();
            $endpointUrl = rtrim($this->getEndpointUrl(), '/');

            if (empty($apiKey)) {
                return [
                    'success' => false,
                    'error' => 'API Key not configured'
                ];
            }

            // There is no dedicated /test route any more. A cheap one-row list call
            // proves the same three things: host reachable, key valid, agent resolved.
            $testUrl = $endpointUrl . self::PATH_POSTS . '?per_page=1';

            $this->applyTimeouts();
            $this->curl->setHeaders([
                'Content-Type' => 'application/json',
                self::HEADER_API_KEY => $apiKey
            ]);

            $this->curl->get($testUrl);

            $responseCode = $this->curl->getStatus();
            $responseBody = $this->curl->getBody();

            if ($responseCode >= 200 && $responseCode < 300) {
                $response = json_decode($responseBody, true);
                return [
                    'success' => true,
                    'message' => $response['message'] ?? 'Posts API connection successful',
                    'agent_name' => $response['agent_name'] ?? null,
                    'posts_available' => $response['posts_available'] ?? 0
                ];
            } else {
                return [
                    'success' => false,
                    'error' => "Connection failed: HTTP {$responseCode}"
                ];
            }

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Connection test failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get API key from config (decrypted)
     *
     * @return string|null
     */
    private function getApiKey(): ?string
    {
        $encryptedKey = $this->scopeConfig->getValue(
            self::XML_PATH_API_KEY,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        if (empty($encryptedKey)) {
            return null;
        }

        return $this->encryptor->decrypt($encryptedKey);
    }

    /**
     * Get endpoint URL from config
     *
     * @return string|null
     */
    private function getEndpointUrl(): ?string
    {
        $url = $this->scopeConfig->getValue(
            self::XML_PATH_ENDPOINT_URL,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        // Default to production URL if not set
        if (empty($url)) {
            $url = 'https://app.requestdesk.ai';
        }

        return $url;
    }

    /**
     * Fail fast when RequestDesk is unreachable.
     *
     * Without these, an endpoint that never answers - a stopped container, a wrong
     * hostname, a firewall - leaves curl waiting until PHP hits max_execution_time
     * and dies. A fatal is not an \Exception, so it escapes the controllers'
     * try/catch, Magento renders an HTML error page, and the admin's AJAX gets HTML
     * where it expects JSON. The operator is then shown "Unexpected token '<'"
     * instead of "could not connect", which says nothing about what is wrong.
     *
     * @return void
     */
    private function applyTimeouts(): void
    {
        try {
            $this->curl->setOptions([
                CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
                CURLOPT_TIMEOUT => self::REQUEST_TIMEOUT,
            ]);
        } catch (\Throwable $e) {
            // Never let tuning stop the call from being attempted.
            $this->logger->warning('RequestDesk: could not apply curl timeouts - ' . $e->getMessage());
        }
    }
}
