<?php
/**
 * RequestDesk Blog - Author Profile Form Data Provider
 *
 * The form is keyed by admin_user_id. Editing an existing profile loads its
 * public fields; a new profile starts empty and the admin user is chosen in
 * the form.
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Model\Author;

use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;
use RequestDesk\Blog\Model\ResourceModel\AuthorProfile\CollectionFactory;

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

        foreach ($this->collection->getItems() as $profile) {
            $id = (int)$profile->getData('admin_user_id');
            $this->loadedData[$id] = $profile->getData();
        }

        $data = $this->dataPersistor->get('requestdesk_blog_author_profile');
        if (!empty($data)) {
            $id = isset($data['admin_user_id']) ? (int)$data['admin_user_id'] : 0;
            $this->loadedData[$id] = $data;
            $this->dataPersistor->clear('requestdesk_blog_author_profile');
        }

        return $this->loadedData ?? [];
    }
}
