<?php
/**
 * RequestDesk Blog - Tag Edit/New Controller
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Controller\Adminhtml\Tag;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use RequestDesk\Blog\Model\ResourceModel\Tag as TagResource;
use RequestDesk\Blog\Model\TagFactory;

class Edit extends Action
{
    /**
     * Authorization level
     */
    const ADMIN_RESOURCE = 'RequestDesk_Blog::tags';

    /**
     * @var PageFactory
     */
    protected PageFactory $resultPageFactory;

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param TagFactory $tagFactory
     * @param TagResource $tagResource
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        private readonly TagFactory $tagFactory,
        private readonly TagResource $tagResource
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
    }

    /**
     * Edit or create a tag
     *
     * @return \Magento\Framework\View\Result\Page|\Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        $tagId = (int)$this->getRequest()->getParam('tag_id');

        if ($tagId) {
            $tag = $this->tagFactory->create();
            $this->tagResource->load($tag, $tagId);
            if (!$tag->getId()) {
                $this->messageManager->addErrorMessage(__('This tag no longer exists.'));
                $resultRedirect = $this->resultRedirectFactory->create();
                return $resultRedirect->setPath('*/*/');
            }
        }

        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('RequestDesk_Blog::tags_menu');
        $resultPage->getConfig()->getTitle()->prepend(
            $tagId ? __('Edit Tag') : __('New Tag')
        );

        return $resultPage;
    }
}
