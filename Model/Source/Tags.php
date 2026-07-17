<?php
/**
 * RequestDesk Blog - Tags Source
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;
use RequestDesk\Blog\Model\ResourceModel\Tag\CollectionFactory;

/**
 * Existing blog tags, for assigning to a post.
 */
class Tags implements OptionSourceInterface
{
    /**
     * @param CollectionFactory $tagCollectionFactory
     */
    public function __construct(
        private readonly CollectionFactory $tagCollectionFactory
    ) {
    }

    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        $collection = $this->tagCollectionFactory->create();
        $collection->setOrder('name', 'ASC');

        $options = [];
        foreach ($collection as $tag) {
            $options[] = ['value' => (int) $tag->getId(), 'label' => $tag->getName()];
        }
        return $options;
    }
}
