<?php
/**
 * RequestDesk Blog - Menu Gate
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\ScopeInterface;
use RequestDesk\Blog\Block\BlogUrl;

/**
 * Decides whether the blog's entry belongs in the storefront menu, and
 * recognises which menu entry that is.
 *
 * 1.10.3 gave Enable Blog real effect on the storefront: the pages 404, the
 * widget renders nothing, the sitemap drops the blog. The menu was left alone
 * and the README said so. A store that links the blog from its menu - which is
 * what the Amasty migration's dedicated parent category is for - was therefore
 * left advertising a page that no longer answers.
 *
 * Which entry is the blog's is decided by where it points, not by what it is.
 * The menu is a tree of catalog categories, but the entry could equally be a
 * CMS page, and a merchant is free to rename the category to anything. The one
 * thing that makes an entry the blog's entry is that following it lands inside
 * the blog's address space, so that is what gets matched. It also means the
 * match follows Blog URL Prefix: move the blog to /news and the entry pointing
 * at /news is the one that disappears.
 *
 * An entry the store wrote into its own theme by hand is still not covered.
 * Nothing in the menu tree describes it, so nothing here can find it, and the
 * README says as much rather than implying a guarantee this cannot give.
 */
class MenuGate
{
    /**
     * Magento's own suffix for category URLs, appended to the last segment of
     * a category's address. A node pointing at the blog therefore arrives as
     * "blog.html" on a default store rather than "blog".
     */
    private const XML_PATH_CATEGORY_URL_SUFFIX = 'catalog/seo/category_url_suffix';

    /**
     * @param Config $config
     * @param ScopeConfigInterface $scopeConfig
     * @param UrlInterface $urlBuilder
     */
    public function __construct(
        private readonly Config $config,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly UrlInterface $urlBuilder
    ) {
    }

    /**
     * Whether the blog's menu entry may be rendered.
     *
     * Both settings have to agree. Enable Blog off means the pages are gone,
     * so a link to them is a dead link. Include Blog in Menu off means the
     * merchant wants the blog reachable but not advertised.
     *
     * @param int|string|null $store
     * @return bool
     */
    public function showsBlogEntry($store = null): bool
    {
        return $this->config->isBlogEnabled($store) && $this->config->isIncludedInMenu($store);
    }

    /**
     * Whether a menu entry's URL lands inside the blog.
     *
     * Compared as paths rather than whole URLs: the two are built by the same
     * URL builder, so both carry the store code or neither does, but they
     * differ in trailing slash and in the category URL suffix.
     *
     * @param string $url
     * @param int|string|null $store
     * @return bool
     */
    public function isBlogUrl(string $url, $store = null): bool
    {
        $target = $this->blogPath($store);
        if ($target === '') {
            return false;
        }

        $candidate = $this->normalizePath($url);

        return $candidate === $target || str_starts_with($candidate, $target . '/');
    }

    /**
     * Path of the blog listing, normalized the same way as a candidate.
     *
     * @param int|string|null $store
     * @return string
     */
    private function blogPath($store = null): string
    {
        return $this->normalizePath(
            BlogUrl::resolve($this->config->getUrlPrefix($store), '', [], $this->urlBuilder)
        );
    }

    /**
     * Path of a URL, without the leading or trailing slash, without a query or
     * fragment, and without the category URL suffix on its last segment.
     *
     * @param string $url
     * @return string
     */
    private function normalizePath(string $url): string
    {
        $path = trim((string) (parse_url($url, PHP_URL_PATH) ?: ''), '/');

        $suffix = (string) $this->scopeConfig->getValue(
            self::XML_PATH_CATEGORY_URL_SUFFIX,
            ScopeInterface::SCOPE_STORE
        );

        if ($suffix !== '' && $path !== '' && str_ends_with($path, $suffix)) {
            $path = substr($path, 0, -strlen($suffix));
        }

        return $path;
    }
}
