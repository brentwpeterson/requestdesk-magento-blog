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
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\Registry;
use RequestDesk\Blog\Api\PostRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;

class Edit extends Action
{
    /**
     * Authorization level
     */
    public const ADMIN_RESOURCE = 'RequestDesk_Blog::manage';

    /**
     * @var PageFactory
     */
    protected PageFactory $resultPageFactory;

    /**
     * @var PostRepositoryInterface
     */
    protected PostRepositoryInterface $postRepository;

    /**
     * @var Registry
     */
    protected Registry $coreRegistry;

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param PostRepositoryInterface $postRepository
     * @param Registry $coreRegistry
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        PostRepositoryInterface $postRepository,
        Registry $coreRegistry
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
        $this->postRepository = $postRepository;
        $this->coreRegistry = $coreRegistry;
    }

    /**
     * Edit blog post
     *
     * @return \Magento\Framework\View\Result\Page|\Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        $postId = (int)$this->getRequest()->getParam('post_id');

        if ($postId) {
            try {
                $post = $this->postRepository->getById($postId);
                $this->coreRegistry->register('requestdesk_blog_post', $post);
            } catch (NoSuchEntityException $e) {
                $this->messageManager->addErrorMessage(__('This post no longer exists.'));
                $resultRedirect = $this->resultRedirectFactory->create();
                return $resultRedirect->setPath('*/*/');
            }
        }

        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('RequestDesk_Blog::posts');
        $resultPage->getConfig()->getTitle()->prepend(
            $postId ? __('Edit Post') : __('New Post')
        );

        return $resultPage;
    }
}
