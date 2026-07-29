<?php
/**
 * RequestDesk Blog - Author Form Data Provider
 *
 * The form is keyed by author_id. Authors are independent records: the admin
 * user link is optional, so a new author starts empty with no account attached.
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Model\Author;

use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;
use RequestDesk\Blog\Block\ImageUrl;
use RequestDesk\Blog\Model\ResourceModel\Author\CollectionFactory;

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

        foreach ($this->collection->getItems() as $author) {
            $id = (int)$author->getData('author_id');
            $values = $author->getData();
            $values['avatar'] = $this->avatarForUploader((string)($values['avatar'] ?? ''));
            $this->loadedData[$id] = $values;
        }

        $data = $this->dataPersistor->get('requestdesk_blog_author');
        if (!empty($data)) {
            $id = isset($data['author_id']) ? (int)$data['author_id'] : 0;
            $this->loadedData[$id] = $data;
            $this->dataPersistor->clear('requestdesk_blog_author');
        }

        return $this->loadedData ?? [];
    }

    /**
     * The image uploader expects a list of files carrying a url, not a bare path.
     *
     * @param string $path
     * @return array<int, array{name:string, url:string}>
     */
    private function avatarForUploader(string $path): array
    {
        if (trim($path) === '') {
            return [];
        }

        return [[
            'name' => basename($path),
            'url' => ImageUrl::resolve($path, $this->storeManager),
        ]];
    }
}
