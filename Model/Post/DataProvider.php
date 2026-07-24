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
use Magento\Ui\DataProvider\AbstractDataProvider;
use RequestDesk\Blog\Api\QaLinkResolverInterface;
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
            // Provide is_active as a STRING so it strict-equals the checkbox
            // valueMap ("0"/"1"). An int here makes the toggle's `value === map`
            // comparison fail (1 !== "1") and a published post renders as "No".
            $this->loadedData[$postId]['is_active'] = (string) (int) $post->getData('status');
            $this->loadedData[$postId]['category_ids'] = $this->categoryResolver->getCategoryIdsForPost($postId);
            $this->loadedData[$postId]['tag_ids'] = $this->tagResolver->getTagIdsForPost($postId);
            $this->loadedData[$postId]['qa_ids'] =
                $this->qaLinkResolver->getQaIdsFor(QaLinkResolverInterface::ENTITY_BLOG_POST, $postId);
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
}
