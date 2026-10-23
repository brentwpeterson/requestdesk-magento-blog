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

namespace RequestDesk\Blog\Model\Post;

use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;
use RequestDesk\Blog\Api\QaLinkResolverInterface;
use RequestDesk\Blog\Block\ImageUrl;
use RequestDesk\Blog\Model\PostCategoryResolver;
use RequestDesk\Blog\Model\ResourceModel\Post\CollectionFactory;
use RequestDesk\Blog\Model\TagResolver;

class DataProvider extends AbstractDataProvider
{
    /**
     * @var array
     */
    protected $loadedData;

    /**
     * @var DataPersistorInterface
     */
    protected DataPersistorInterface $dataPersistor;

    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param CollectionFactory $collectionFactory
     * @param DataPersistorInterface $dataPersistor
     * @param PostCategoryResolver $categoryResolver
     * @param TagResolver $tagResolver
     * @param QaLinkResolverInterface $qaLinkResolver
     * @param StoreManagerInterface $storeManager
     * @param array $meta
     * @param array $data
     */
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        DataPersistorInterface $dataPersistor,
        private readonly PostCategoryResolver $categoryResolver,
        private readonly TagResolver $tagResolver,
        private readonly QaLinkResolverInterface $qaLinkResolver,
        private readonly StoreManagerInterface $storeManager,
        array $meta = [],
        array $data = []
    ) {
        $this->collection = $collectionFactory->create();
        $this->dataPersistor = $dataPersistor;
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    /**
     * Get data
     *
     * @return array
     */
    public function getData(): array
    {
        if (isset($this->loadedData)) {
            return $this->loadedData;
        }

        foreach ($this->collection->getItems() as $post) {
            $postId = (int) $post->getId();
            $this->loadedData[$postId] = $post->getData();
            // Status as a STRING, matching the select's option values the same
            // way the comments toggle below needs its "0"/"1".
            $this->loadedData[$postId]['status'] = (string) (int) $post->getData('status');
            // The date field reads its value as UTC and shows it in the store
            // time zone; created_at is stored in UTC, so it passes straight through.
            $this->loadedData[$postId]['published_at'] = (string) $post->getData('created_at');
            // Without the cast a saved 0 fails the toggle's `value === map` check
            // (0 !== "0") and renders as Allow Comment = on.
            $this->loadedData[$postId]['comments_enabled'] = (string) (int) $post->getData('comments_enabled');
            $this->loadedData[$postId]['store_id'] = (string) (int) $post->getData('store_id');
            // NULL (store default) as '', the value of the select's "Use store default".
            $this->loadedData[$postId]['meta_robots'] = (string) $post->getData('meta_robots');
            // Ids as STRINGS: the form renders every option value as a string
            // ("11") and the multiselect matches strictly, so int ids left the
            // saved categories, tags and Q&A pairs unselected on edit.
            $this->loadedData[$postId]['category_ids'] =
                array_map('strval', $this->categoryResolver->getCategoryIdsForPost($postId));
            $this->loadedData[$postId]['tag_ids'] =
                array_map('strval', $this->tagResolver->getTagIdsForPost($postId));
            $this->loadedData[$postId]['qa_ids'] = array_map('strval', $this->qaLinkResolver->getQaIdsFor(
                QaLinkResolverInterface::ENTITY_BLOG_POST,
                $postId
            ));
            $this->loadedData[$postId]['featured_image'] =
                $this->imageForUploader((string) $post->getData('featured_image'));
        }

        $data = $this->dataPersistor->get('requestdesk_blog_post');
        if (!empty($data)) {
            $post = $this->collection->getNewEmptyItem();
            $post->setData($data);
            $this->loadedData[$post->getId()] = $post->getData();
            $this->dataPersistor->clear('requestdesk_blog_post');
        }

        return $this->loadedData ?? [];
    }

    /**
     * The image uploader expects a list of files carrying a url, not a bare path.
     *
     * @param string $path
     * @return array<int, array{name:string, url:string}>
     */
    private function imageForUploader(string $path): array
    {
        if (trim($path) === '') {
            return [];
        }

        return [[
            'name' => basename($path), // phpcs:ignore Magento2.Functions.DiscouragedFunction
            'url' => ImageUrl::resolve($path, $this->storeManager),
        ]];
    }
}
