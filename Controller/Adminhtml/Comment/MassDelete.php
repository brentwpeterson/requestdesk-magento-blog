<?php
/**
 * RequestDesk Blog - Admin Mass Delete Comments
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Controller\Adminhtml\Comment;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Ui\Component\MassAction\Filter;
use RequestDesk\Blog\Model\CommentManager;
use RequestDesk\Blog\Model\ResourceModel\Comment\CollectionFactory;

/**
 * Delete the selected comments.
 */
class MassDelete extends Action implements HttpPostActionInterface
{
    /**
     * @inheritdoc
     */
    public const ADMIN_RESOURCE = 'RequestDesk_Blog::comments';

    /**
     * @param Context $context
     * @param Filter $filter
     * @param CollectionFactory $collectionFactory
     * @param CommentManager $commentManager
     */
    public function __construct(
        Context $context,
        private readonly Filter $filter,
        private readonly CollectionFactory $collectionFactory,
        private readonly CommentManager $commentManager
    ) {
        parent::__construct($context);
    }

    /**
     * @return Redirect
     */
    public function execute(): Redirect
    {
        $redirect = $this->resultRedirectFactory->create();
        try {
            $collection = $this->filter->getCollection($this->collectionFactory->create());
            $ids = array_map('intval', $collection->getAllIds());
            $count = $this->commentManager->remove($ids);
            $this->messageManager->addSuccessMessage(__('%1 comment(s) deleted.', $count));
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }
        return $redirect->setPath('*/*/index');
    }
}
