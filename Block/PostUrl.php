<?php
/**
 * RequestDesk Blog - Post URL Resolver
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Block;

use Magento\Framework\UrlInterface;
use RequestDesk\Blog\Api\Data\PostInterface;

/**
 * Builds the public address of a post.
 *
 * Prefers the pretty form /blog/<url-key>, which Controller\Router resolves. The
 * id form /blog/post/view/id/N stays the fallback for a post with no url_key, so
 * a row that predates the column - or one imported without a slug - is still
 * reachable instead of linking nowhere.
 *
 * Both forms are served by the same action, so nothing here needs url_rewrite
 * rows or a reindex to stay correct.
 */
class PostUrl
{
    /**
     * @param PostInterface $post
     * @param UrlInterface $urlBuilder
     * @return string
     */
    public static function resolve(PostInterface $post, UrlInterface $urlBuilder): string
    {
        $urlKey = trim((string) $post->getUrlKey());

        if ($urlKey === '') {
            return $urlBuilder->getUrl('blog/post/view', ['id' => $post->getPostId()]);
        }

        // _direct emits the path verbatim under the store base URL. Passing
        // "blog/<key>" as a route path instead would have the URL builder read
        // the key as a controller name and rewrite it.
        return $urlBuilder->getUrl('', ['_direct' => 'blog/' . $urlKey]);
    }
}
