<?php
/**
 * RequestDesk Blog - Frontend Category Filter
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Controller\Category;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Forward;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

/**
 * Lists blog posts in a native category at /blog/category/view/id/{category_id}.
 */
class View implements HttpGetActionInterface
{
    /**
     * @param PageFactory $pageFactory
     * @param ForwardFactory $forwardFactory
     * @param RequestInterface $request
     * @param CategoryRepositoryInterface $categoryRepository
     */
    public function __construct(
        private readonly PageFactory $pageFactory,
        private readonly ForwardFactory $forwardFactory,
        private readonly RequestInterface $request,
        private readonly CategoryRepositoryInterface $categoryRepository
    ) {
    }

    /**
     * @return Page|Forward
     */
    public function execute()
    {
        $id = (int) $this->request->getParam('id');
        try {
            $category = $id > 0 ? $this->categoryRepository->get($id) : null;
        } catch (\Throwable $e) {
            $category = null;
        }
        if ($category === null) {
            return $this->forwardFactory->create()->forward('noroute');
        }

        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set(__('Blog: %1', $category->getName()));
        return $page;
    }
}
