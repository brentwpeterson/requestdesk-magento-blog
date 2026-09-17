<?php
/**
 * RequestDesk Blog - Storefront Gate
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Model;

use Magento\Framework\App\RequestInterface;

/**
 * Decides whether a storefront request may reach a blog controller.
 *
 * Two settings feed it, and both used to be ignored.
 *
 * Enable Blog. With it set to No the blog kept answering on every URL, so the
 * switch in the admin did nothing a merchant could see.
 *
 * Blog URL Prefix. The controllers are registered under the front name "blog"
 * in etc/frontend/routes.xml, and Magento's standard router matches that front
 * name before Controller\Router runs. A store that moves the blog to /news
 * therefore still has every page answering on /blog as well, the same content
 * at two addresses. The gate closes the /blog copy by requiring the request's
 * first path segment to be the configured prefix. Controller\Router leaves the
 * path info untouched when it forwards /news/... onto the blog's controllers,
 * so those requests still carry "news" and pass.
 *
 * A request the gate refuses gets the standard 404, the same answer as a URL
 * that never existed.
 */
class StorefrontGate
{
    /**
     * @param Config $config
     */
    public function __construct(
        private readonly Config $config
    ) {
    }

    /**
     * @param RequestInterface $request
     * @return bool
     */
    public function allows(RequestInterface $request): bool
    {
        if (!$this->config->isBlogEnabled()) {
            return false;
        }

        $prefix = $this->config->getUrlPrefix();

        // On the default prefix there is no second address to close, and the
        // path is left unchecked so a store that points its home page (Default
        // Web URL) at blog/index/index keeps working the way it does today.
        if ($prefix === Config::ROUTE_FRONT_NAME) {
            return true;
        }

        return self::firstSegment($request) === $prefix;
    }

    /**
     * First segment of the request path, store code already stripped by Magento.
     *
     * @param RequestInterface $request
     * @return string
     */
    public static function firstSegment(RequestInterface $request): string
    {
        $path = method_exists($request, 'getPathInfo') ? (string) $request->getPathInfo() : '';

        return explode('/', trim($path, '/'))[0];
    }
}
