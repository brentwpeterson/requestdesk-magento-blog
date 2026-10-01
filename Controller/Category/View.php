<?php
/**
 * RequestDesk Blog - Frontend Category Filter
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength.TooLong

namespace RequestDesk\Blog\Controller\Category;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Forward;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use RequestDesk\Blog\Model\CategoryFactory;
use RequestDesk\Blog\Model\ResourceModel\Category as CategoryResource;
use RequestDesk\Blog\Model\StorefrontGate;

/**
 * Lists blog posts in a blog category at /blog/category/view/id/{category_id}.
 *
 * A disabled category 404s, the same as one that does not exist.
 */
class View implements HttpGetActionInterface
{
    /**
     * @param PageFactory $pageFactory
     * @param ForwardFactory $forwardFactory
     * @param RequestInterface $request
     * @param StorefrontGate $storefrontGate
     * @param CategoryFactory $categoryFactory
     * @param CategoryResource $categoryResource
     */
    public function __construct(
        private readonly PageFactory $pageFactory,
        private readonly ForwardFactory $forwardFactory,
        private readonly RequestInterface $request,
        private readonly StorefrontGate $storefrontGate,
        private readonly CategoryFactory $categoryFactory,
        private readonly CategoryResource $categoryResource
    ) {
    }

    /**
     * Execute the action
     *
     * @return Page|Forward
     */
    public function execute()
    {
        if (!$this->storefrontGate->allows($this->request)) {
            return $this->forwardFactory->create()->forward('noroute');
        }

        $id = (int) $this->request->getParam('id');
        $category = $this->categoryFactory->create();
        if ($id > 0) {
            $this->categoryResource->load($category, $id);
        }
        if (!$category->getId() || !$category->isEnabled()) {
            return $this->forwardFactory->create()->forward('noroute');
        }

        $page = $this->pageFactory->create();
        $pageConfig = $page->getConfig();
        $pageConfig->getTitle()->set($category->getMetaTitle() !== '' ? $category->getMetaTitle() : $category->getName());
        if ($category->getMetaDescription() !== '') {
            $pageConfig->setDescription($category->getMetaDescription());
        }
        if ($category->getMetaTags() !== '') {
            $pageConfig->setKeywords($category->getMetaTags());
        }
        if ($category->getMetaRobots() !== '') {
            $pageConfig->setRobots(strtoupper($category->getMetaRobots()));
        }
        return $page;
    }
}
