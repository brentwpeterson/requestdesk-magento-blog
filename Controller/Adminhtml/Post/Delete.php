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
use RequestDesk\Blog\Api\PostRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\LocalizedException;

class Delete extends Action
{
    /**
     * Authorization level
     */
    public const ADMIN_RESOURCE = 'RequestDesk_Blog::manage';

    /**
     * @var PostRepositoryInterface
     */
    protected PostRepositoryInterface $postRepository;

    /**
     * @param Context $context
     * @param PostRepositoryInterface $postRepository
     */
    public function __construct(
        Context $context,
        PostRepositoryInterface $postRepository
    ) {
        parent::__construct($context);
        $this->postRepository = $postRepository;
    }

    /**
     * Delete blog post
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $postId = (int)$this->getRequest()->getParam('post_id');

        if (!$postId) {
            $this->messageManager->addErrorMessage(__('Post ID is required.'));
            return $resultRedirect->setPath('*/*/');
        }

        try {
            $this->postRepository->deleteById($postId);
            $this->messageManager->addSuccessMessage(__('The post has been deleted.'));
        } catch (NoSuchEntityException $e) {
            $this->messageManager->addErrorMessage(__('This post no longer exists.'));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('An error occurred while deleting the post.'));
        }

        return $resultRedirect->setPath('*/*/');
    }
}
