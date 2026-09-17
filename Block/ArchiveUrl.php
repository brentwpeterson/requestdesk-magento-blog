<?php
/**
 * RequestDesk Blog - Archive URL Resolver
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Block;

use Magento\Framework\UrlInterface;

/**
 * Builds the public address of a category, tag or author archive.
 *
 * The sibling of Block\PostUrl, and it exists for the same reason. Posts moved
 * to /blog/<url-key> when Controller\Router landed; the three archives did not,
 * so every category, tag and author link on the site still read
 * /blog/category/view/id/77 - the primary key in a public URL, and nothing in it
 * a search engine can read.
 *
 * Four separate call sites built those URLs by hand (PostCategoryResolver,
 * TagResolver twice, AuthorResolver), which is how they drifted from the post
 * rule in the first place. One resolver, one rule.
 *
 * The id form stays the fallback for a record with no url_key, so nothing links
 * nowhere, and Controller\Router keeps serving it. Both forms reach the same
 * action, so no url_rewrite rows and no reindex.
 */
class ArchiveUrl
{
    /** Path segment under the blog prefix for each archive type. */
    public const TYPE_CATEGORY = 'category';
    public const TYPE_TAG = 'tag';
    public const TYPE_AUTHOR = 'author';

    /**
     * @param string $type one of the TYPE_* constants
     * @param int $id
     * @param string|null $urlKey
     * @param UrlInterface $urlBuilder
     * @param string $prefix from Model\Config::getUrlPrefix(), required for the reason given on PostUrl::resolve()
     * @return string
     */
    public static function resolve(
        string $type,
        int $id,
        ?string $urlKey,
        UrlInterface $urlBuilder,
        string $prefix
    ): string {
        $urlKey = trim((string) $urlKey);

        if ($urlKey === '') {
            return BlogUrl::resolve($prefix, $type . '/view/id/' . $id, [], $urlBuilder);
        }

        // _direct emits the path verbatim under the store base URL. Passing
        // "<prefix>/category/<key>" as a route path instead would have the URL
        // builder read the segments as controller and action names.
        return $urlBuilder->getUrl('', ['_direct' => $prefix . '/' . $type . '/' . $urlKey]);
    }
}
