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

class SyncStatus implements OptionSourceInterface
{
    /**
     * Sync status values
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_SYNCED = 'synced';
    public const STATUS_FAILED = 'failed';
    public const STATUS_NOT_SYNCED = 'not_synced';

    /**
     * Get options
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::STATUS_SYNCED, 'label' => __('Synced')],
            ['value' => self::STATUS_PENDING, 'label' => __('Pending')],
            ['value' => self::STATUS_FAILED, 'label' => __('Failed')],
            ['value' => self::STATUS_NOT_SYNCED, 'label' => __('Not Synced')]
        ];
    }

    /**
     * Get options as key-value pairs
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            self::STATUS_SYNCED => __('Synced'),
            self::STATUS_PENDING => __('Pending'),
            self::STATUS_FAILED => __('Failed'),
            self::STATUS_NOT_SYNCED => __('Not Synced')
        ];
    }
}
