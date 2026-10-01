<?php
/**
 * RequestDesk Blog - Category Delete Controller
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Controller\Adminhtml\Category;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Exception\LocalizedException;
use RequestDesk\Blog\Model\CategoryFactory;
use RequestDesk\Blog\Model\ResourceModel\Category as CategoryResource;

class Delete extends Action implements HttpPostActionInterface
{
    /**
     * Authorization level
     */
    public const ADMIN_RESOURCE = 'RequestDesk_Blog::categories';

    /**
     * @param Context $context
     * @param CategoryFactory $categoryFactory
     * @param CategoryResource $categoryResource
     */
    public function __construct(
        Context $context,
        private readonly CategoryFactory $categoryFactory,
        private readonly CategoryResource $categoryResource
    ) {
        parent::__construct($context);
    }

    /**
     * Delete a category. Post links cascade via FK; subcategories move up a level.
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $categoryId = (int) $this->getRequest()->getParam('category_id');

        if (!$categoryId) {
            $this->messageManager->addErrorMessage(__('Category ID is required.'));
            return $resultRedirect->setPath('*/*/');
        }

        try {
            $category = $this->categoryFactory->create();
            $this->categoryResource->load($category, $categoryId);
            if (!$category->getId()) {
                $this->messageManager->addErrorMessage(__('This category no longer exists.'));
                return $resultRedirect->setPath('*/*/');
            }

            $this->categoryResource->delete($category);
            $this->messageManager->addSuccessMessage(__('The category has been deleted.'));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('An error occurred while deleting the category.'));
        }

        return $resultRedirect->setPath('*/*/');
    }
}
