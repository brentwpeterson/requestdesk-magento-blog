<?php
/**
 * RequestDesk Blog - Blog Categories Source
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;
use RequestDesk\Blog\Model\Category;
use RequestDesk\Blog\Model\CategoryTree;

/**
 * Blog categories, indented by depth, for assigning to a post and for the
 * posts widget. Disabled ones are listed and marked, so a post filed under one
 * does not silently lose it on its next save.
 */
class Categories implements OptionSourceInterface
{
    /**
     * @param CategoryTree $categoryTree
     */
    public function __construct(
        private readonly CategoryTree $categoryTree
    ) {
    }

    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        $options = [];
        foreach ($this->categoryTree->getFlatTree() as $category) {
            $label = str_repeat('- ', $category['depth']) . $category['name'];
            if ($category['status'] !== Category::STATUS_ENABLED) {
                $label .= ' ' . __('(disabled)');
            }
            $options[] = ['value' => $category['id'], 'label' => $label];
        }
        return $options;
    }
}
