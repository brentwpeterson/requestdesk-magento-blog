<?php
/**
 * RequestDesk Blog - Blog URL Prefix Backend Model
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Model\Config\Backend;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Route\ConfigInterface as RouteConfigInterface;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use RequestDesk\Blog\Model\Config;

/**
 * Validates Blog URL Prefix before it is saved.
 *
 * Two values would break the storefront, so both are refused here with a
 * message rather than discovered later as a blog that 404s.
 *
 * A prefix that is not one plain path segment. Controller\Router matches the
 * first segment of the path, so "news/blog" or "my blog" can never be routed.
 *
 * A prefix another module already owns as its front name, such as "checkout"
 * or "customer". Magento's standard router runs first and hands the whole path
 * to that module, so the blog would never be reached, and on a module like
 * checkout the blog would be the smaller of the two problems.
 *
 * The value is stored normalized (trimmed of slashes, lowercase), so "/News/"
 * saves as "news". Empty is allowed and means /blog.
 */
class UrlPrefix extends StorefrontSetting
{
    /**
     * @param Context $context
     * @param Registry $registry
     * @param ScopeConfigInterface $config
     * @param TypeListInterface $cacheTypeList
     * @param RouteConfigInterface $routeConfig
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        private readonly RouteConfigInterface $routeConfig,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /**
     * Normalize the prefix, and refuse one the storefront could not serve.
     *
     * @return $this
     * @throws LocalizedException
     */
    public function beforeSave()
    {
        $prefix = Config::normalizeUrlPrefix((string) $this->getValue());

        if ($prefix !== '') {
            if (!preg_match(Config::URL_PREFIX_PATTERN, $prefix)) {
                throw new LocalizedException(__(
                    'Blog URL Prefix "%1" can only use lowercase letters, numbers, hyphens and underscores, '
                    . 'with no slashes or spaces, for example "news".',
                    $prefix
                ));
            }

            $owners = array_diff(
                $this->routeConfig->getModulesByFrontName($prefix, 'frontend'),
                ['RequestDesk_Blog']
            );
            if ($owners !== []) {
                throw new LocalizedException(__(
                    'Blog URL Prefix "%1" is already the storefront address of %2, so the blog could not be '
                    . 'reached there. Choose a different prefix.',
                    $prefix,
                    implode(', ', $owners)
                ));
            }
        }

        $this->setValue($prefix);

        return parent::beforeSave();
    }
}
