<?php
/**
 * Copyright (c) 2025 Content Basis LLC
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available at https://opensource.org/licenses/OSL-3.0
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 * @author    Content Basis LLC
 * @copyright Copyright (c) 2025 Content Basis LLC
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License 3.0
 */
declare(strict_types=1);

namespace RequestDesk\Blog\Controller\Adminhtml\Post;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Ui\Component\MassAction\Filter;
use RequestDesk\Blog\Api\Data\PostInterface;
use RequestDesk\Blog\Model\BlogCache;
use RequestDesk\Blog\Model\ResourceModel\Post\CollectionFactory;

/**
 * Activate (publish) or inactivate (disable) the selected posts.
 *
 * Activate means "live now": a Scheduled post dated in the future is published
 * with its date brought back to now, so the blog never shows a future date.
 */
class MassStatus extends Action implements HttpPostActionInterface
{
    /**
     * @inheritdoc
     */
    public const ADMIN_RESOURCE = 'RequestDesk_Blog::manage';

    /**
     * @param Context $context
     * @param Filter $filter
     * @param CollectionFactory $collectionFactory
     * @param ResourceConnection $resource
     * @param BlogCache $blogCache
     */
    public function __construct(
        Context $context,
        private readonly Filter $filter,
        private readonly CollectionFactory $collectionFactory,
        private readonly ResourceConnection $resource,
        private readonly BlogCache $blogCache
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
        $activate = (int) $this->getRequest()->getParam('status') === PostInterface::STATUS_PUBLISHED;

        try {
            $ids = array_map(
                'intval',
                $this->filter->getCollection($this->collectionFactory->create())->getAllIds()
            );
            if ($ids !== []) {
                $connection = $this->resource->getConnection();
                $table = $this->resource->getTableName('requestdesk_blog_post');
                $now = gmdate('Y-m-d H:i:s');

                if ($activate) {
                    $connection->update($table, ['created_at' => $now], [
                        'post_id IN (?)' => $ids,
                        'created_at > ?' => $now,
                    ]);
                }
                $connection->update(
                    $table,
                    [
                        'status' => $activate ? PostInterface::STATUS_PUBLISHED : PostInterface::STATUS_DRAFT,
                        'updated_at' => $now,
                    ],
                    ['post_id IN (?)' => $ids]
                );
                $this->blogCache->clean();
            }
            $this->messageManager->addSuccessMessage(
                $activate
                    ? __('%1 post(s) activated.', count($ids))
                    : __('%1 post(s) inactivated.', count($ids))
            );
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $redirect->setPath('*/*/index');
    }
}
