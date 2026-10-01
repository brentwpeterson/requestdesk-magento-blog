<?php
/**
 * RequestDesk Blog - Meta Robots Source
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Model\Category\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Robots directives for a category archive. Values match Amasty's, so a
 * migrated value is valid here as-is. Empty leaves the store default.
 */
class MetaRobots implements OptionSourceInterface
{
    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => '', 'label' => __('Use store default')],
            ['value' => 'index, follow', 'label' => __('INDEX, FOLLOW')],
            ['value' => 'noindex, follow', 'label' => __('NOINDEX, FOLLOW')],
            ['value' => 'index, nofollow', 'label' => __('INDEX, NOFOLLOW')],
            ['value' => 'noindex, nofollow', 'label' => __('NOINDEX, NOFOLLOW')],
        ];
    }
}
