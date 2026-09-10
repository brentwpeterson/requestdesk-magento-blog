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

/**
 * Reads the blog's system configuration.
 */
class Config
{
    public const XML_PATH_POSTS_PER_PAGE = 'requestdesk_blog/general/posts_per_page';
    public const XML_PATH_ENABLE_PAGINATION = 'requestdesk_blog/general/enable_pagination';

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
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
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
}
