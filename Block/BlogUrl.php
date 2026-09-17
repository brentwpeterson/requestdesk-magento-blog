<?php
/**
 * RequestDesk Blog - Blog Path URL Builder
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Block;

use Magento\Framework\UrlInterface;

/**
 * Builds a storefront URL under the blog's configured prefix.
 *
 * The blog's controllers are registered under the front name "blog", so
 * getUrl('blog/...') always writes /blog no matter what the store has set as
 * Blog URL Prefix. Every blog address therefore goes through _direct with the
 * prefix in front, and Controller\Router maps the prefix back onto the blog's
 * controllers on the way in.
 *
 * The trailing slash matches what getUrl() wrote for a route path, so with the
 * default prefix the index, pager and id-form URLs come out character for
 * character as they did before the prefix was honored.
 */
class BlogUrl
{
    /**
     * @param string $prefix from Model\Config::getUrlPrefix()
     * @param string $path under the prefix, e.g. "category/view/id/77"; empty for the index
     * @param array<string, mixed> $query null values are dropped
     * @param UrlInterface $urlBuilder
     * @return string
     */
    public static function resolve(
        string $prefix,
        string $path,
        array $query,
        UrlInterface $urlBuilder
    ): string {
        $path = trim($path, '/');
        $direct = $prefix . '/' . ($path !== '' ? $path . '/' : '');

        $url = $urlBuilder->getUrl('', ['_direct' => $direct]);

        $query = array_filter($query, static fn ($value) => $value !== null);
        if ($query !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }

        return $url;
    }
}
