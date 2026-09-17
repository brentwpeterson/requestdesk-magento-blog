<?php
/**
 * RequestDesk Blog - Configuration accessor
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;

/**
 * Reads the blog's system configuration.
 */
class Config
{
    public const XML_PATH_POSTS_PER_PAGE = 'requestdesk_blog/general/posts_per_page';
    public const XML_PATH_ENABLE_PAGINATION = 'requestdesk_blog/general/enable_pagination';
    public const XML_PATH_ENABLED = 'requestdesk_blog/general/enabled';
    public const XML_PATH_URL_PREFIX = 'requestdesk_blog/seo/url_prefix';

    /**
     * The front name in etc/frontend/routes.xml. The configured prefix is what
     * visitors see; this is what the controllers are registered under, and the
     * two are the same unless a store sets a different prefix.
     */
    public const ROUTE_FRONT_NAME = 'blog';

    /**
     * What a prefix may look like: one path segment, lowercase letters, digits,
     * hyphens and underscores. Anything with a slash cannot be matched by
     * Controller\Router, which reads the first segment of the path.
     */
    public const URL_PREFIX_PATTERN = '/^[a-z0-9][a-z0-9_-]*$/';

    /**
     * Mirrors etc/config.xml, so a blank value still paginates instead of
     * falling back to "return everything".
     */
    public const DEFAULT_POSTS_PER_PAGE = 10;

    /**
     * Ceiling for a caller-supplied page size, so ?limit=99999 cannot pull the
     * whole table in one request.
     */
    public const MAX_POSTS_PER_PAGE = 100;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Configured page size, floored at 1.
     *
     * The field is plain text, so it can still arrive as '', '0' or a negative
     * number when written by config:set or a direct core_config_data insert
     * rather than the admin form.
     *
     * @param int|string|null $store
     * @return int
     */
    public function getPostsPerPage($store = null): int
    {
        $configured = (int) $this->scopeConfig->getValue(
            self::XML_PATH_POSTS_PER_PAGE,
            ScopeInterface::SCOPE_STORE,
            $store
        );

        return $configured > 0 ? $configured : self::DEFAULT_POSTS_PER_PAGE;
    }

    /**
     * Resolve a caller-supplied page size against the configured default.
     *
     * @param int|null $requested
     * @param int|string|null $store
     * @return int
     */
    public function resolvePageSize(?int $requested, $store = null): int
    {
        if ($requested === null || $requested < 1) {
            return $this->getPostsPerPage($store);
        }

        return min($requested, self::MAX_POSTS_PER_PAGE);
    }

    /**
     * Whether listing pages (blog, category, author, tag) show a pager.
     *
     * When off, every listing renders all of its posts on one page.
     *
     * isSetFlag() rather than isFlag(): isFlag() is newer than this codebase's
     * framework version, and the DI interceptor wrapping the config object
     * does not implement it.
     *
     * @param int|string|null $store
     * @return bool
     */
    public function isPaginationEnabled($store = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLE_PAGINATION,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Whether the blog is switched on for the storefront.
     *
     * Off means every blog page, the comment endpoint and the blog widgets
     * answer as if the module were not there, and the blog drops out of the
     * XML sitemap. The admin, the REST API and the RequestDesk import are not
     * affected, so posts can still be prepared while the storefront is dark.
     *
     * @param int|string|null $store
     * @return bool
     */
    public function isBlogEnabled($store = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * The first path segment of every blog address on the storefront.
     *
     * Empty means the route's own front name, so an unset field keeps /blog.
     * The admin form rejects a malformed value on save (see
     * Model\Config\Backend\UrlPrefix), but config:set and direct database
     * writes skip that check. A malformed value that reaches here is logged as
     * an error and the blog stays on /blog, because a prefix with a slash or a
     * space in it cannot be routed at all and would take every blog page down.
     *
     * @param int|string|null $store
     * @return string
     */
    public function getUrlPrefix($store = null): string
    {
        $configured = (string) $this->scopeConfig->getValue(
            self::XML_PATH_URL_PREFIX,
            ScopeInterface::SCOPE_STORE,
            $store
        );

        $prefix = self::normalizeUrlPrefix($configured);
        if ($prefix === '') {
            return self::ROUTE_FRONT_NAME;
        }

        if (!preg_match(self::URL_PREFIX_PATTERN, $prefix)) {
            $this->logger->error(sprintf(
                'RequestDesk Blog: %s is "%s", which is not a single URL segment. '
                . 'The blog is served on /%s until it is corrected.',
                self::XML_PATH_URL_PREFIX,
                $configured,
                self::ROUTE_FRONT_NAME
            ));
            return self::ROUTE_FRONT_NAME;
        }

        return $prefix;
    }

    /**
     * Trim surrounding whitespace and slashes and lowercase, so "/News/" and
     * "news" name the same prefix.
     *
     * @param string|null $value
     * @return string
     */
    public static function normalizeUrlPrefix(?string $value): string
    {
        return strtolower(trim(trim((string) $value), '/'));
    }
}
