<?php
/**
 * RequestDesk Blog - Frontend Index (post list)
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Controller\Index;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Forward;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use RequestDesk\Blog\Model\StorefrontGate;

/**
 * Renders the blog listing at /<prefix>, /blog unless the store sets another.
 */
class Index implements HttpGetActionInterface
{
    /**
     * @param PageFactory $pageFactory
     * @param ForwardFactory $forwardFactory
     * @param RequestInterface $request
     * @param StorefrontGate $storefrontGate
     */
    public function __construct(
        private readonly PageFactory $pageFactory,
        private readonly ForwardFactory $forwardFactory,
        private readonly RequestInterface $request,
        private readonly StorefrontGate $storefrontGate
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

        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set(__('Blog'));
        return $page;
    }
}
