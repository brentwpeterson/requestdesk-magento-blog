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

namespace RequestDesk\Blog\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Store\Model\System\Store as SystemStore;

/**
 * Store views grouped by website, with an empty first option.
 *
 * The empty option means "the website's default store view". Magento's own
 * store source has no empty option, so a select built on it always saves the
 * first store view in the list, which is the wrong one for every website but one.
 */
class ImportStore implements OptionSourceInterface
{
    /**
     * @param SystemStore $systemStore
     */
    public function __construct(
        private readonly SystemStore $systemStore
    ) {
    }

    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        $options = $this->systemStore->getStoreValuesForForm(false, false);
        array_unshift($options, ['value' => '', 'label' => __('Website default store view')]);
        return $options;
    }
}
