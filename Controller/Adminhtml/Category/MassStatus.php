<?php
/**
 * RequestDesk Blog - Admin Mass Enable/Disable Categories
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Controller\Adminhtml\Category;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Ui\Component\MassAction\Filter;
use RequestDesk\Blog\Model\Category;
use RequestDesk\Blog\Model\ResourceModel\Category\CollectionFactory;

/**
 * Enable or disable the selected categories; the grid passes status=0|1.
 */
class MassStatus extends Action implements HttpPostActionInterface
{
    /**
     * @inheritdoc
     */
    public const ADMIN_RESOURCE = 'RequestDesk_Blog::categories';

    /**
     * @param Context $context
     * @param Filter $filter
     * @param CollectionFactory $collectionFactory
     * @param ResourceConnection $resource
     */
    public function __construct(
        Context $context,
        private readonly Filter $filter,
        private readonly CollectionFactory $collectionFactory,
        private readonly ResourceConnection $resource
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
        $status = (int) $this->getRequest()->getParam('status') === Category::STATUS_ENABLED
            ? Category::STATUS_ENABLED
            : Category::STATUS_DISABLED;

        try {
            $ids = array_map(
                'intval',
                $this->filter->getCollection($this->collectionFactory->create())->getAllIds()
            );
            if ($ids !== []) {
                $this->resource->getConnection()->update(
                    $this->resource->getTableName('requestdesk_blog_category'),
                    ['status' => $status],
                    ['category_id IN (?)' => $ids]
                );
            }
            $this->messageManager->addSuccessMessage(
                $status === Category::STATUS_ENABLED
                    ? __('%1 category(ies) enabled.', count($ids))
                    : __('%1 category(ies) disabled.', count($ids))
            );
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }
        return $redirect->setPath('*/*/index');
    }
}
