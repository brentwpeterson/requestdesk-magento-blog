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
     * @param PostRepositoryInterface $postRepository
     */
    public function __construct(
        private readonly PageFactory $pageFactory,
        private readonly ForwardFactory $forwardFactory,
        private readonly RequestInterface $request,
        private readonly PostRepositoryInterface $postRepository
    ) {
    }

    /**
     * @inheritdoc
     *
     * @return Page|Forward
     */
    public function execute()
    {
        $id = (int) $this->request->getParam('id');
        if ($id <= 0) {
            return $this->notFound();
        }

        try {
            $post = $this->postRepository->getById($id);
        } catch (\Throwable $e) {
            return $this->notFound();
        }

        if ((int) $post->getStatus() !== PostInterface::STATUS_PUBLISHED) {
            return $this->notFound();
        }

        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set($post->getMetaTitle() ?: $post->getTitle());
        if ($post->getMetaDescription()) {
            $page->getConfig()->setDescription((string) $post->getMetaDescription());
        }
        return $page;
    }

    /**
     * @return Forward
     */
    private function notFound(): Forward
    {
        return $this->forwardFactory->create()->forward('noroute');
    }
}
