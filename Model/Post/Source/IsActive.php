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

namespace RequestDesk\Blog\Model\Post\Source;

use Magento\Framework\Data\OptionSourceInterface;
use RequestDesk\Blog\Api\Data\PostInterface;

/**
 * Post status options for the form, the grid column and its filter.
 *
 * The class name predates Hidden and Scheduled; kept so existing references hold.
 */
class IsActive implements OptionSourceInterface
{
    /**
     * Status values
     */
    public const STATUS_ENABLED = 1;
    public const STATUS_DISABLED = 0;

    /**
     * Get options
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        $options = [];
        foreach ($this->toArray() as $value => $label) {
            $options[] = ['value' => $value, 'label' => $label];
        }
        return $options;
    }

    /**
     * Get options as key-value pairs
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            PostInterface::STATUS_PUBLISHED => __('Published'),
            PostInterface::STATUS_HIDDEN => __('Hidden'),
            PostInterface::STATUS_SCHEDULED => __('Scheduled'),
            PostInterface::STATUS_DRAFT => __('Disabled'),
        ];
    }
}
