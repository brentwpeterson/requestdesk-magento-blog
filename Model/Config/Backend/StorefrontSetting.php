<?php
/**
 * RequestDesk Blog - Storefront Setting Backend Model
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Model\Config\Backend;

use Magento\Framework\App\Cache\Type\Block as BlockCache;
use Magento\Framework\App\Config\Value;
use Magento\PageCache\Model\Cache\Type as FullPageCache;

/**
 * A setting that changes what the storefront serves: Enable Blog and Blog URL
 * Prefix.
 *
 * Magento invalidates only the configuration cache when a value changes. Full
 * page cache still holds the blog pages as they were, so switching the blog
 * off, or moving it to a new prefix, would keep serving the cached pages at the
 * old addresses until someone thought to flush it. Marking both caches invalid
 * puts the refresh notice in front of the admin who made the change.
 */
class StorefrontSetting extends Value
{
    /**
     * Mark the page and block caches invalid when the value changes.
     *
     * @return $this
     */
    public function afterSave()
    {
        if ($this->isValueChanged()) {
            $this->cacheTypeList->invalidate([
                FullPageCache::TYPE_IDENTIFIER,
                BlockCache::TYPE_IDENTIFIER,
            ]);
        }

        return parent::afterSave();
    }
}
