<?php
/**
 * RequestDesk Blog - Tag Form Data Provider
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Model\Tag;

use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;
use RequestDesk\Blog\Model\ResourceModel\Tag\CollectionFactory;

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
     * @param array $meta
     * @param array $data
     */
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        DataPersistorInterface $dataPersistor,
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

        foreach ($this->collection->getItems() as $tag) {
            $this->loadedData[(int)$tag->getId()] = $tag->getData();
        }

        $data = $this->dataPersistor->get('requestdesk_blog_tag');
        if (!empty($data)) {
            $tag = $this->collection->getNewEmptyItem();
            $tag->setData($data);
            $this->loadedData[$tag->getId()] = $tag->getData();
            $this->dataPersistor->clear('requestdesk_blog_tag');
        }

        return $this->loadedData ?? [];
    }
}
