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

namespace RequestDesk\Blog\Service;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Helper\Image as ImageHelper;
use Psr\Log\LoggerInterface;

class ProductExportService
{
    private const XML_PATH_API_KEY = 'requestdesk_blog/api/api_key';
    private const XML_PATH_ENDPOINT_URL = 'requestdesk_blog/api/endpoint_url';
    private const XML_PATH_COMPANY_ID = 'requestdesk_blog/api/company_id';

    /**
     * @var ProductCollectionFactory
     */
    private ProductCollectionFactory $productCollectionFactory;

    /**
     * @var ProductRepositoryInterface
     */
    private ProductRepositoryInterface $productRepository;

    /**
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @var ImageHelper
     */
    private ImageHelper $imageHelper;

    /**
     * @var Curl
     */
    private Curl $curl;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var CategoryRepositoryInterface
     */
    private CategoryRepositoryInterface $categoryRepository;

    /**
     * @param ProductCollectionFactory $productCollectionFactory
     * @param ProductRepositoryInterface $productRepository
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     * @param ImageHelper $imageHelper
     * @param Curl $curl
     * @param LoggerInterface $logger
     * @param CategoryRepositoryInterface $categoryRepository
     */
    public function __construct(
        ProductCollectionFactory $productCollectionFactory,
        ProductRepositoryInterface $productRepository,
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        ImageHelper $imageHelper,
        Curl $curl,
        LoggerInterface $logger,
        CategoryRepositoryInterface $categoryRepository,
        private readonly EncryptorInterface $encryptor
    ) {
        $this->productCollectionFactory = $productCollectionFactory;
        $this->productRepository = $productRepository;
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->imageHelper = $imageHelper;
        $this->curl = $curl;
        $this->logger = $logger;
        $this->categoryRepository = $categoryRepository;
    }

    /**
     * Export all visible products to RequestDesk
     *
     * @param int|null $limit Optional limit for testing
     * @return array Result with success status and details
     */
    public function exportProducts(?int $limit = null): array
    {
        try {
            $this->logger->info('RequestDesk: Starting product export');

            // Validate API configuration
            $apiKey = $this->getApiKey();
            $endpointUrl = $this->getEndpointUrl();

            if (empty($apiKey) || empty($endpointUrl)) {
                return [
                    'success' => false,
                    'error' => 'RequestDesk API not configured. Please set API Key and Endpoint URL in Store > Configuration > RequestDesk > Blog.'
                ];
            }

            // Get products
            $products = $this->getProductsForExport($limit);
            $totalProducts = count($products);

            if ($totalProducts === 0) {
                return [
                    'success' => true,
                    'message' => 'No products to export',
                    'total_products' => 0,
                    'total_synced' => 0
                ];
            }

            $this->logger->info("RequestDesk: Found {$totalProducts} products to export");

            // Transform products to RequestDesk format
            $documents = [];
            foreach ($products as $product) {
                $documents[] = $this->transformProductToDocument($product);
            }

            // Send to RequestDesk
            $result = $this->sendToRequestDesk($documents);

            return $result;

        } catch (\Exception $e) {
            $this->logger->error('RequestDesk: Product export failed - ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Export a single product by ID
     *
     * @param int $productId
     * @return array
     */
    public function exportSingleProduct(int $productId): array
    {
        try {
            $product = $this->productRepository->getById($productId);
            $document = $this->transformProductToDocument($product);

            return $this->sendToRequestDesk([$document]);

        } catch (\Exception $e) {
            $this->logger->error("RequestDesk: Single product export failed for ID {$productId} - " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get products for export
     *
     * @param int|null $limit
     * @return array
     */
    private function getProductsForExport(?int $limit = null): array
    {
        $collection = $this->productCollectionFactory->create();

        $collection
            ->addAttributeToSelect('*')
            ->addAttributeToFilter('status', \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED)
            ->addAttributeToFilter('visibility', ['neq' => \Magento\Catalog\Model\Product\Visibility::VISIBILITY_NOT_VISIBLE]);

        if ($limit !== null) {
            $collection->setPageSize($limit);
        }

        return $collection->getItems();
    }

    /**
     * Transform a Magento product to RequestDesk document format
     *
     * @param \Magento\Catalog\Model\Product $product
     * @return array
     */
    private function transformProductToDocument($product): array
    {
        $storeUrl = $this->storeManager->getStore()->getBaseUrl();

        // Build content from product attributes
        $content = $this->buildProductContent($product);

        // Get product images
        $images = $this->getProductImages($product);

        // Get categories
        $categories = $this->getProductCategories($product);

        return [
            'source_id' => 'magento_product_' . $product->getId(),
            'source_type' => 'ecommerce_product',
            'title' => $product->getName(),
            'content' => $content,
            'url' => $product->getProductUrl(),
            'metadata' => [
                'sku' => $product->getSku(),
                'price' => number_format((float)$product->getPrice(), 2, '.', ''),
                'special_price' => $product->getSpecialPrice() ? number_format((float)$product->getSpecialPrice(), 2, '.', '') : null,
                'product_type' => $product->getTypeId(),
                'status' => $product->getStatus() == 1 ? 'enabled' : 'disabled',
                'visibility' => $product->getVisibility(),
                'categories' => $categories,
                'images' => $images,
                'thumbnail' => !empty($images) ? $images[0] : null,
                'weight' => $product->getWeight(),
                'created_at' => $product->getCreatedAt(),
                'updated_at' => $product->getUpdatedAt(),
                'store_url' => rtrim($storeUrl, '/'),
                'magento_id' => $product->getId()
            ]
        ];
    }

    /**
     * Build rich content from product attributes
     *
     * @param \Magento\Catalog\Model\Product $product
     * @return string
     */
    private function buildProductContent($product): string
    {
        $parts = [];

        // Product name as title
        $parts[] = "# " . $product->getName();

        // Short description
        $shortDesc = $product->getShortDescription();
        if (!empty($shortDesc)) {
            $parts[] = "\n## Overview\n" . strip_tags($shortDesc);
        }

        // Full description
        $description = $product->getDescription();
        if (!empty($description)) {
            $parts[] = "\n## Description\n" . strip_tags($description);
        }

        // Key product details
        $details = [];
        if ($product->getSku()) {
            $details[] = "SKU: " . $product->getSku();
        }
        if ($product->getPrice()) {
            $details[] = "Price: $" . number_format((float)$product->getPrice(), 2);
        }
        if ($product->getSpecialPrice()) {
            $details[] = "Sale Price: $" . number_format((float)$product->getSpecialPrice(), 2);
        }
        if ($product->getWeight()) {
            $details[] = "Weight: " . $product->getWeight();
        }

        if (!empty($details)) {
            $parts[] = "\n## Product Details\n" . implode("\n", $details);
        }

        // Meta keywords (often contain important product terms)
        $metaKeywords = $product->getMetaKeyword();
        if (!empty($metaKeywords)) {
            $parts[] = "\n## Keywords\n" . $metaKeywords;
        }

        return implode("\n", $parts);
    }

    /**
     * Get product images
     *
     * @param \Magento\Catalog\Model\Product $product
     * @return array
     */
    private function getProductImages($product): array
    {
        $images = [];

        try {
            $mediaGallery = $product->getMediaGalleryImages();
            if ($mediaGallery) {
                foreach ($mediaGallery as $image) {
                    $images[] = $image->getUrl();
                }
            }
        } catch (\Exception $e) {
            // Fallback to main image
            $mainImage = $product->getImage();
            if ($mainImage && $mainImage !== 'no_selection') {
                $baseUrl = $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);
                $images[] = $baseUrl . 'catalog/product' . $mainImage;
            }
        }

        return $images;
    }

    /**
     * Get product category names
     *
     * @param \Magento\Catalog\Model\Product $product
     * @return array
     */
    private function getProductCategories($product): array
    {
        $categories = [];

        try {
            $categoryIds = $product->getCategoryIds();
            if (!empty($categoryIds)) {
                foreach ($categoryIds as $categoryId) {
                    try {
                        $category = $this->categoryRepository->get($categoryId);
                        $categories[] = $category->getName();
                    } catch (\Exception $e) {
                        // Skip invalid categories
                    }
                }
            }
        } catch (\Exception $e) {
            $this->logger->warning('RequestDesk: Failed to get categories for product ' . $product->getId());
        }

        return $categories;
    }

    /**
     * Send documents to RequestDesk API
     *
     * @param array $documents
     * @return array
     */
    private function sendToRequestDesk(array $documents): array
    {
        $apiKey = $this->getApiKey();
        $endpointUrl = rtrim($this->getEndpointUrl(), '/');
        $storeUrl = $this->storeManager->getStore()->getBaseUrl();

        // Clean store URL for use as identifier
        $storeIdentifier = parse_url($storeUrl, PHP_URL_HOST) ?? 'magento-store';

        $payload = [
            'store_url' => $storeIdentifier,
            'documents' => $documents,
            'auto_create_collection' => true
        ];

        $syncUrl = $endpointUrl . '/api/public/magento/sync';

        // "{count($documents)}" is not interpolation - PHP renders the literal
        // "{count(" then casts the array, emitting an "Array to string conversion"
        // warning on every sync and logging a useless count.
        $this->logger->info(sprintf(
            'RequestDesk: Sending %d documents to %s',
            count($documents),
            $syncUrl
        ));

        try {
            $this->curl->setHeaders([
                'Content-Type' => 'application/json',
                'x-requestdesk-api-key' => $apiKey
            ]);

            $this->curl->post($syncUrl, json_encode($payload));

            $responseCode = $this->curl->getStatus();
            $responseBody = $this->curl->getBody();

            $this->logger->info("RequestDesk: Response code {$responseCode}");

            if ($responseCode >= 200 && $responseCode < 300) {
                $response = json_decode($responseBody, true);

                return [
                    'success' => true,
                    'message' => $response['message'] ?? 'Products synced successfully',
                    'total_products' => count($documents),
                    'total_synced' => $response['total_chunks_created'] ?? count($documents),
                    'collection_id' => $response['collection_id'] ?? null
                ];
            } else {
                $error = json_decode($responseBody, true);
                return [
                    'success' => false,
                    'error' => $error['detail'] ?? "HTTP {$responseCode}: {$responseBody}"
                ];
            }

        } catch (\Exception $e) {
            $this->logger->error('RequestDesk: API request failed - ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to connect to RequestDesk: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get API key from config
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

        // The field is obscure/encrypted in system.xml, so the stored value is
        // Magento ciphertext ("0:3:..."). This returned it raw and shipped that
        // straight to RequestDesk as the API key, which of course never matched
        // an agent - every product sync came back 401 while looking configured.
        // PostImportService has always decrypted; this is the same treatment.
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
     * Test API connection
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

            $testUrl = $endpointUrl . '/api/public/magento/test';

            $this->curl->setHeaders([
                'Content-Type' => 'application/json',
                'x-requestdesk-api-key' => $apiKey
            ]);

            $this->curl->post($testUrl, '{}');

            $responseCode = $this->curl->getStatus();
            $responseBody = $this->curl->getBody();

            if ($responseCode >= 200 && $responseCode < 300) {
                $response = json_decode($responseBody, true);
                return [
                    'success' => true,
                    'message' => $response['message'] ?? 'Connection successful',
                    'agent_name' => $response['agent_name'] ?? null
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
}
