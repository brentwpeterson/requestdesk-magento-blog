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
use Magento\Framework\Controller\Result\Redirect;
use Magento\Ui\Component\MassAction\Filter;
use RequestDesk\Blog\Api\PostRepositoryInterface;
use RequestDesk\Blog\Model\ResourceModel\Post\CollectionFactory;

/**
 * Delete the selected posts.
 */
class MassDelete extends Action implements HttpPostActionInterface
{
    /**
     * @inheritdoc
     */
    public const ADMIN_RESOURCE = 'RequestDesk_Blog::manage';

    /**
     * @param Context $context
     * @param Filter $filter
     * @param CollectionFactory $collectionFactory
     * @param PostRepositoryInterface $postRepository
     */
    public function __construct(
        Context $context,
        private readonly Filter $filter,
        private readonly CollectionFactory $collectionFactory,
        private readonly PostRepositoryInterface $postRepository
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
            $count = 0;
            // One at a time through the repository, like the single Delete: that
            // path also clears the post's Q&A links and purges the page cache.
            foreach ($this->filter->getCollection($this->collectionFactory->create()) as $post) {
                $this->postRepository->deleteById((int) $post->getId());
                $count++;
            }
            $this->messageManager->addSuccessMessage(__('%1 post(s) deleted.', $count));
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }
        return $redirect->setPath('*/*/index');
    }
}
