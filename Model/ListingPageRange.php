<?php
/**
 * RequestDesk Blog - Listing Page Range
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Model;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Router\NoRouteHandler;
use Magento\Framework\Controller\Result\Forward;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\View\Element\BlockFactory;
use RequestDesk\Blog\Block\PostList;

/**
 * Tells a listing controller that ?p= is past the listing's last page, so it 404s.
 *
 * The check runs on a standalone instance of the page's listing block rather
 * than on the layout's own: building the layout inside the controller would
 * leave the blog's blocks in the shared layout, and the noroute page that a
 * 404 forwards to would render them. The listing blocks take everything they
 * filter by from the request, so the standalone one counts the same posts.
 *
 * notFound() forwards straight to the store's 404 action. A plain
 * forward('noroute') re-runs the routers on the original path, and while
 * Amasty_Blog is still installed its router claims /blog/...?p= again and
 * 301s the URL to itself, a redirect loop.
 */
class ListingPageRange
{
    /**
     * @param BlockFactory $blockFactory
     * @param RequestInterface $request
     * @param ForwardFactory $forwardFactory
     * @param NoRouteHandler $noRouteHandler
     */
    public function __construct(
        private readonly BlockFactory $blockFactory,
        private readonly RequestInterface $request,
        private readonly ForwardFactory $forwardFactory,
        private readonly NoRouteHandler $noRouteHandler
    ) {
    }

    /**
     * Whether the requested page is past the last page of the given listing.
     *
     * Page 1 always exists, even for an empty listing, so it skips the count
     * query; only a deliberate ?p=2 or higher pays for the extra lookup.
     *
     * @param string $listingBlockClass PostList or one of its subclasses
     * @return bool
     */
    public function isOutOfRange(string $listingBlockClass = PostList::class): bool
    {
        if ((int) $this->request->getParam(PostList::PAGE_VAR_NAME, 1) <= 1) {
            return false;
        }

        $listing = $this->blockFactory->createBlock($listingBlockClass);

        return $listing instanceof PostList && $listing->isPageOutOfRange();
    }

    /**
     * Forward to the store's 404 page (web/default/no_route, cms/noroute/index by default).
     *
     * @return Forward
     */
    public function notFound(): Forward
    {
        $this->noRouteHandler->process($this->request);

        return $this->forwardFactory->create()
            ->setModule($this->request->getModuleName())
            ->setController($this->request->getControllerName())
            ->forward($this->request->getActionName());
    }
}
