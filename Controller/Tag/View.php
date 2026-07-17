<?php
/**
 * RequestDesk Blog - Frontend Tag View
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Controller\Tag;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Forward;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use RequestDesk\Blog\Model\TagResolver;

/**
 * Renders a tag archive at /blog/tag/view/id/{tag_id}.
 */
class View implements HttpGetActionInterface
{
    /**
     * @param PageFactory $pageFactory
     * @param ForwardFactory $forwardFactory
     * @param RequestInterface $request
     * @param TagResolver $tagResolver
     */
    public function __construct(
        private readonly PageFactory $pageFactory,
        private readonly ForwardFactory $forwardFactory,
        private readonly RequestInterface $request,
        private readonly TagResolver $tagResolver
    ) {
    }

    /**
     * @return Page|Forward
     */
    public function execute()
    {
        $id = (int) $this->request->getParam('id');
        $tag = $id > 0 ? $this->tagResolver->getTag($id) : null;
        if ($tag === null) {
            return $this->forwardFactory->create()->forward('noroute');
        }

        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set(__('Tag: %1', $tag['name']));
        return $page;
    }
}
