<?php
/**
 * RequestDesk Blog - Native Categories Source
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Model\Source;

use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Framework\Data\OptionSourceInterface;

/**
 * Native Magento categories, indented by depth, for assigning to a post.
 */
class Categories implements OptionSourceInterface
{
    /**
     * @param CollectionFactory $categoryCollectionFactory
     */
    public function __construct(
        private readonly CollectionFactory $categoryCollectionFactory
    ) {
    }

    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        $collection = $this->categoryCollectionFactory->create();
        $collection->addAttributeToSelect('name')
            ->addFieldToFilter('level', ['gt' => 1])
            ->addAttributeToSort('path', 'ASC');

        $options = [];
        foreach ($collection as $category) {
            $depth = max(0, (int) $category->getLevel() - 2);
            $options[] = [
                'value' => (int) $category->getId(),
                'label' => str_repeat('- ', $depth) . $category->getName(),
            ];
        }
        return $options;
    }
}
