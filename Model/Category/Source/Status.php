<?php
/**
 * RequestDesk Blog - Category Status Source
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Model\Category\Source;

use Magento\Framework\Data\OptionSourceInterface;
use RequestDesk\Blog\Model\Category;

/**
 * Enabled / Disabled, the same two values Amasty uses.
 */
class Status implements OptionSourceInterface
{
    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => Category::STATUS_ENABLED, 'label' => __('Enabled')],
            ['value' => Category::STATUS_DISABLED, 'label' => __('Disabled')],
        ];
    }
}
