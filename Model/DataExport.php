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

use RequestDesk\Blog\Api\DataExportInterface;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Cms\Model\ResourceModel\Page\CollectionFactory as CmsPageCollectionFactory;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Framework\Exception\AuthorizationException;
use Magento\Framework\Encryption\EncryptorInterface;
use Psr\Log\LoggerInterface;

class DataExport implements DataExportInterface
{
    private const XML_PATH_API_KEY = 'requestdesk_blog/api/api_key';

    /**
     * @var ProductCollectionFactory
     */
    private ProductCollectionFactory $productCollectionFactory;

    /**
     * @var CategoryCollectionFactory
     */
    private CategoryCollectionFactory $categoryCollectionFactory;

    /**
     * @var CmsPageCollectionFactory
     */
    private CmsPageCollectionFactory $cmsPageCollectionFactory;

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * @var Request
     */
    private Request $request;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var EncryptorInterface
     */
    private EncryptorInterface $encryptor;
    private ApiKeyValidator $apiKeyValidator;

    /**
     * @param ProductCollectionFactory $productCollectionFactory
     * @param CategoryCollectionFactory $categoryCollectionFactory
     * @param CmsPageCollectionFactory $cmsPageCollectionFactory
     * @param StoreManagerInterface $storeManager
     * @param ScopeConfigInterface $scopeConfig
     * @param Request $request
     * @param LoggerInterface $logger
     * @param EncryptorInterface $encryptor
     */
    public function __construct(
        ProductCollectionFactory $productCollectionFactory,
        CategoryCollectionFactory $categoryCollectionFactory,
        CmsPageCollectionFactory $cmsPageCollectionFactory,
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig,
        Request $request,
        LoggerInterface $logger,
        EncryptorInterface $encryptor,
        ApiKeyValidator $apiKeyValidator
    ) {
        $this->productCollectionFactory = $productCollectionFactory;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->cmsPageCollectionFactory = $cmsPageCollectionFactory;
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
        $this->request = $request;
        $this->logger = $logger;
        $this->encryptor = $encryptor;
        $this->apiKeyValidator = $apiKeyValidator;
    }

    /**
     * @throws AuthorizationException
     */
    private function validateApiKey(): void
    {
        $this->apiKeyValidator->validate('Data Export');
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
            'store_url' => $store->getBaseUrl(),
            'store_name' => $store->getName(),
            'store_code' => $store->getCode(),
            'message' => 'Connection successful'
        ];
    }

    /**
     * @inheritDoc
     */
    public function getProducts(int $pageSize = 100, int $currentPage = 1): array
    {
        $this->validateApiKey();

        try {
            $collection = $this->productCollectionFactory->create();
            $collection
                ->addAttributeToSelect('*')
                ->addAttributeToFilter('status', Status::STATUS_ENABLED)
                // Include ALL products for knowledge base - visibility is for storefront, not content
                ->setPageSize($pageSize)
                ->setCurPage($currentPage);

            $totalCount = $collection->getSize();
            $products = [];

            foreach ($collection as $product) {
                $products[] = $this->transformProduct($product);
            }

            $this->logger->info("RequestDesk: Exported {$collection->count()} products (page {$currentPage})");

            return [
                'success' => true,
                'products' => $products,
                'total_count' => $totalCount,
                'page_size' => $pageSize,
                'current_page' => $currentPage
            ];

        } catch (\Exception $e) {
            $this->logger->error('RequestDesk: Product export failed - ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'products' => []
            ];
        }
    }

    /**
     * @inheritDoc
     */
    public function getCategories(): array
    {
        $this->validateApiKey();

        try {
            $collection = $this->categoryCollectionFactory->create();
            $collection
                ->addAttributeToSelect('*')
                ->addAttributeToFilter('is_active', 1)
                ->addAttributeToFilter('level', ['gt' => 1]); // Skip root categories

            $categories = [];
            $storeUrl = $this->storeManager->getStore()->getBaseUrl();

            foreach ($collection as $category) {
                $categories[] = $this->transformCategory($category, $storeUrl);
            }

            $this->logger->info("RequestDesk: Exported " . count($categories) . " categories");

            return [
                'success' => true,
                'categories' => $categories,
                'total_count' => count($categories)
            ];

        } catch (\Exception $e) {
            $this->logger->error('RequestDesk: Category export failed - ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'categories' => []
            ];
        }
    }

    /**
     * @inheritDoc
     */
    public function getCmsPages(int $pageSize = 100, int $currentPage = 1): array
    {
        $this->validateApiKey();

        try {
            $collection = $this->cmsPageCollectionFactory->create();
            $collection
                ->addFieldToFilter('is_active', 1)
                ->setPageSize($pageSize)
                ->setCurPage($currentPage);

            $totalCount = $collection->getSize();
            $pages = [];
            $storeUrl = $this->storeManager->getStore()->getBaseUrl();

            foreach ($collection as $page) {
                $pages[] = $this->transformCmsPage($page, $storeUrl);
            }

            $this->logger->info("RequestDesk: Exported " . count($pages) . " CMS pages");

            return [
                'success' => true,
                'pages' => $pages,
                'total_count' => $totalCount,
                'page_size' => $pageSize,
                'current_page' => $currentPage
            ];

        } catch (\Exception $e) {
            $this->logger->error('RequestDesk: CMS page export failed - ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'pages' => []
            ];
        }
    }

    /**
     * Transform a Magento product to RequestDesk document format
     *
     * @param \Magento\Catalog\Model\Product $product
     * @return array
     */
    private function transformProduct($product): array
    {
        $storeUrl = $this->storeManager->getStore()->getBaseUrl();

        // Build content from product attributes
        $contentParts = ["Product: " . $product->getName()];

        if ($product->getSku()) {
            $contentParts[] = "SKU: " . $product->getSku();
        }
        if ($product->getPrice()) {
            $contentParts[] = "Price: $" . number_format((float)$product->getPrice(), 2);
        }
        if ($product->getShortDescription()) {
            $contentParts[] = "\n" . strip_tags($product->getShortDescription());
        }
        if ($product->getDescription()) {
            $contentParts[] = "\n" . strip_tags($product->getDescription());
        }

        $content = implode("\n", $contentParts);

        // Get product URL
        $urlKey = $product->getUrlKey() ?: $product->getSku();
        $productUrl = $urlKey ? rtrim($storeUrl, '/') . '/' . $urlKey . '.html' : '';

        // Get image URL
        $image = $product->getImage();
        $imageUrl = '';
        if ($image && $image !== 'no_selection') {
            $imageUrl = rtrim($storeUrl, '/') . '/media/catalog/product' . $image;
        }

        return [
            'source_id' => 'magento_product_' . $product->getId(),
            'source_type' => 'ecommerce_product',
            'title' => $product->getName(),
            'content' => $content,
            'url' => $productUrl,
            'featured_image' => $imageUrl,
            'sku' => $product->getSku(),
            'price' => (float)$product->getPrice(),
            'metadata' => [
                'magento_id' => $product->getId(),
                'sku' => $product->getSku(),
                'type_id' => $product->getTypeId(),
                'status' => $product->getStatus(),
                'visibility' => $product->getVisibility(),
                'created_at' => $product->getCreatedAt(),
                'updated_at' => $product->getUpdatedAt()
            ]
        ];
    }

    /**
     * Transform a Magento category to RequestDesk document format
     *
     * @param \Magento\Catalog\Model\Category $category
     * @param string $storeUrl
     * @return array
     */
    private function transformCategory($category, string $storeUrl): array
    {
        $name = $category->getName();
        $path = $category->getPath();

        $content = "Category: {$name}\nPath: {$path}";
        if ($category->getDescription()) {
            $content .= "\n" . strip_tags($category->getDescription());
        }

        $urlKey = $category->getUrlKey() ?: strtolower(str_replace(' ', '-', $name));
        $categoryUrl = rtrim($storeUrl, '/') . '/' . $urlKey . '.html';

        return [
            'source_id' => 'magento_category_' . $category->getId(),
            'source_type' => 'category',
            'title' => $name,
            'content' => $content,
            'url' => $categoryUrl,
            'metadata' => [
                'magento_id' => $category->getId(),
                'path' => $path,
                'level' => $category->getLevel(),
                'position' => $category->getPosition(),
                'is_active' => $category->getIsActive(),
                'product_count' => $category->getProductCount()
            ]
        ];
    }

    /**
     * Transform a Magento CMS page to RequestDesk document format
     *
     * @param \Magento\Cms\Model\Page $page
     * @param string $storeUrl
     * @return array
     */
    private function transformCmsPage($page, string $storeUrl): array
    {
        $identifier = $page->getIdentifier();
        $pageUrl = $identifier ? rtrim($storeUrl, '/') . '/' . $identifier : '';

        return [
            'source_id' => 'magento_cms_' . $page->getId(),
            'source_type' => 'cms_page',
            'title' => $page->getTitle(),
            'content' => $page->getContent(),
            'url' => $pageUrl,
            'metadata' => [
                'magento_id' => $page->getId(),
                'identifier' => $identifier,
                'is_active' => $page->getIsActive(),
                'creation_time' => $page->getCreationTime(),
                'update_time' => $page->getUpdateTime(),
                'meta_title' => $page->getMetaTitle(),
                'meta_keywords' => $page->getMetaKeywords(),
                'meta_description' => $page->getMetaDescription()
            ]
        ];
    }
}
