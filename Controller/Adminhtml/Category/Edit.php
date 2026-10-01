<?php
/**
 * RequestDesk Blog - Category Edit/New Controller
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Controller\Adminhtml\Category;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\PageFactory;
use RequestDesk\Blog\Model\CategoryFactory;
use RequestDesk\Blog\Model\ResourceModel\Category as CategoryResource;

class Edit extends Action implements HttpGetActionInterface
{
    /**
     * Authorization level
     */
    public const ADMIN_RESOURCE = 'RequestDesk_Blog::categories';

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param CategoryFactory $categoryFactory
     * @param CategoryResource $categoryResource
     */
    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory,
        private readonly CategoryFactory $categoryFactory,
        private readonly CategoryResource $categoryResource
    ) {
        parent::__construct($context);
    }

    /**
     * Edit or create a category
     *
     * @return \Magento\Framework\View\Result\Page|\Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        $categoryId = (int) $this->getRequest()->getParam('category_id');

        if ($categoryId) {
            $category = $this->categoryFactory->create();
            $this->categoryResource->load($category, $categoryId);
            if (!$category->getId()) {
                $this->messageManager->addErrorMessage(__('This category no longer exists.'));
                return $this->resultRedirectFactory->create()->setPath('*/*/');
            }
        }

        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('RequestDesk_Blog::categories_menu');
        $resultPage->getConfig()->getTitle()->prepend(
            $categoryId ? __('Edit Category') : __('New Category')
        );

        return $resultPage;
    }
}
