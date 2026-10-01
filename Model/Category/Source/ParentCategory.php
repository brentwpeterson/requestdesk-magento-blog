<?php
/**
 * RequestDesk Blog - Parent Category Source
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Model\Category\Source;

use Magento\Framework\Data\OptionSourceInterface;
use RequestDesk\Blog\Model\Source\Categories;

/**
 * Parent picker on the category form: "no parent" plus the category tree.
 *
 * The category itself and its descendants are still offered; Save refuses them
 * with a message, which is clearer than an option that is quietly missing.
 */
class ParentCategory implements OptionSourceInterface
{
    /**
     * @param Categories $categories
     */
    public function __construct(
        private readonly Categories $categories
    ) {
    }

    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        return array_merge(
            [['value' => 0, 'label' => __('-- None (top level) --')]],
            $this->categories->toOptionArray()
        );
    }
}
