<?php
/**
 * RequestDesk Blog - Admin Mass Delete Categories
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Controller\Adminhtml\Category;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Ui\Component\MassAction\Filter;
use RequestDesk\Blog\Model\ResourceModel\Category as CategoryResource;
use RequestDesk\Blog\Model\ResourceModel\Category\CollectionFactory;

/**
 * Delete the selected categories.
 */
class MassDelete extends Action implements HttpPostActionInterface
{
    /**
     * @inheritdoc
     */
    public const ADMIN_RESOURCE = 'RequestDesk_Blog::categories';

    /**
     * @param Context $context
     * @param Filter $filter
     * @param CollectionFactory $collectionFactory
     * @param CategoryResource $categoryResource
     */
    public function __construct(
        Context $context,
        private readonly Filter $filter,
        private readonly CollectionFactory $collectionFactory,
        private readonly CategoryResource $categoryResource
    ) {
        parent::__construct($context);
    }

    /**
     * Execute the action
     *
     * @return Redirect
     */
    public function execute(): Redirect
    {
        $redirect = $this->resultRedirectFactory->create();
        try {
            $collection = $this->filter->getCollection($this->collectionFactory->create());
            $count = 0;
            // One at a time through the resource model, so each delete re-parents
            // its children; a bulk DELETE would skip that.
            foreach ($collection as $category) {
                // Reload: an earlier delete in this loop may have moved it.
                $this->categoryResource->load($category, $category->getId());
                $this->categoryResource->delete($category);
                $count++;
            }
            $this->messageManager->addSuccessMessage(__('%1 category(ies) deleted.', $count));
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }
        return $redirect->setPath('*/*/index');
    }
}
