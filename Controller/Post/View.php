<?php
/**
 * RequestDesk Blog - Frontend Post View
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Controller\Post;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Forward;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use RequestDesk\Blog\Api\Data\PostInterface;
use RequestDesk\Blog\Api\PostRepositoryInterface;
use RequestDesk\Blog\Model\ListingPageRange;
use RequestDesk\Blog\Model\StorefrontGate;
use RequestDesk\Blog\Model\StoreScope;

/**
 * Renders a single published post at /blog/post/view/id/{id}.
 * Missing, non-existent, or unpublished posts 404.
 */
class View implements HttpGetActionInterface
{
    /**
     * @param PageFactory $pageFactory
     * @param ForwardFactory $forwardFactory
     * @param RequestInterface $request
     * @param StorefrontGate $storefrontGate
     * @param PostRepositoryInterface $postRepository
     * @param ListingPageRange $listingPageRange
     * @param StoreScope $storeScope
     */
    public function __construct(
        private readonly PageFactory $pageFactory,
        private readonly ForwardFactory $forwardFactory,
        private readonly RequestInterface $request,
        private readonly StorefrontGate $storefrontGate,
        private readonly PostRepositoryInterface $postRepository,
        private readonly ListingPageRange $listingPageRange,
        private readonly StoreScope $storeScope
    ) {
    }

    /**
     * @inheritdoc
     *
     * @return Page|Forward
     */
    public function execute()
    {
        if (!$this->storefrontGate->allows($this->request)) {
            return $this->forwardFactory->create()->forward('noroute');
        }

        $id = (int) $this->request->getParam('id');
        if ($id <= 0) {
            return $this->notFound();
        }

        try {
            $post = $this->postRepository->getById($id);
        } catch (\Throwable $e) {
            return $this->notFound();
        }

        // A post on another store view is not this store's post, by id or by key.
        if (!$this->storeScope->isVisible((int) $post->getStoreId())) {
            return $this->notFound();
        }

        // Hidden opens by URL too; Disabled and not-yet-due Scheduled posts 404.
        if (!in_array((int) $post->getStatus(), PostInterface::STATUSES_OPEN_BY_URL, true)) {
            return $this->notFound();
        }

        $page = $this->pageFactory->create();
        // A Hidden post is shared by link only, so keep it out of search engines too.
        // Otherwise the post's own Meta Robots, if it has one.
        if ((int) $post->getStatus() === PostInterface::STATUS_HIDDEN) {
            $page->getConfig()->setRobots('NOINDEX,FOLLOW');
        } elseif (trim((string) $post->getMetaRobots()) !== '') {
            $page->getConfig()->setRobots(strtoupper((string) $post->getMetaRobots()));
        }
        $page->getConfig()->getTitle()->set($post->getMetaTitle() ?: $post->getTitle());
        if ($post->getMetaDescription()) {
            $page->getConfig()->setDescription((string) $post->getMetaDescription());
        }
        return $page;
    }

    /**
     * Not found
     *
     * @return Forward
     */
    private function notFound(): Forward
    {
        // Straight to the store's 404 page. forward('noroute') re-runs the
        // routers, and this URL matches ours (and Amasty's) again.
        return $this->listingPageRange->notFound();
    }
}
