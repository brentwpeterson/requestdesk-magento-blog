<?php
/**
 * RequestDesk Blog - Sitemap config reader
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Model\Sitemap;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Sitemap\Model\ItemProvider\ConfigReaderInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Priority, change frequency and the on/off switch for blog URLs in the XML
 * sitemap. Lives in Magento's own sitemap section (Stores > Configuration >
 * Catalog > XML Sitemap > Blog Options) beside the category, product and CMS
 * page options, since that is where a merchant tunes the sitemap.
 */
class BlogConfigReader implements ConfigReaderInterface
{
    public const XML_PATH_ENABLED = 'sitemap/requestdesk_blog/enabled';
    public const XML_PATH_PRIORITY = 'sitemap/requestdesk_blog/priority';
    public const XML_PATH_CHANGE_FREQUENCY = 'sitemap/requestdesk_blog/changefreq';

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * @param int $storeId
     * @return bool
     */
    public function isEnabled($storeId): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @inheritdoc
     */
    public function getPriority($storeId)
    {
        return (string) $this->scopeConfig->getValue(self::XML_PATH_PRIORITY, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @inheritdoc
     */
    public function getChangeFrequency($storeId)
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_CHANGE_FREQUENCY,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
}
