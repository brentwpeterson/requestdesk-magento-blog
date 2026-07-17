<?php
/**
 * RequestDesk Blog - Frontend Author View
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Controller\Author;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Forward;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use RequestDesk\Blog\Model\AuthorResolver;

/**
 * Renders an author page at /blog/author/view/id/{admin_user_id}.
 */
class View implements HttpGetActionInterface
{
    /**
     * @param PageFactory $pageFactory
     * @param ForwardFactory $forwardFactory
     * @param RequestInterface $request
     * @param AuthorResolver $authorResolver
     */
    public function __construct(
        private readonly PageFactory $pageFactory,
        private readonly ForwardFactory $forwardFactory,
        private readonly RequestInterface $request,
        private readonly AuthorResolver $authorResolver
    ) {
    }

    /**
     * @return Page|Forward
     */
    public function execute()
    {
        $id = (int) $this->request->getParam('id');
        $author = $id > 0 ? $this->authorResolver->getAuthor($id) : null;
        if ($author === null) {
            return $this->forwardFactory->create()->forward('noroute');
        }

        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set($author['name']);
        return $page;
    }
}
