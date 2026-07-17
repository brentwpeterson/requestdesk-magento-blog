<?php
/**
 * RequestDesk Blog - Tag Delete Controller
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Controller\Adminhtml\Tag;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Exception\LocalizedException;
use RequestDesk\Blog\Model\ResourceModel\Tag as TagResource;
use RequestDesk\Blog\Model\TagFactory;

class Delete extends Action
{
    /**
     * Authorization level
     */
    const ADMIN_RESOURCE = 'RequestDesk_Blog::tags';

    /**
     * @param Context $context
     * @param TagFactory $tagFactory
     * @param TagResource $tagResource
     */
    public function __construct(
        Context $context,
        private readonly TagFactory $tagFactory,
        private readonly TagResource $tagResource
    ) {
        parent::__construct($context);
    }

    /**
     * Delete a tag (post links cascade via FK)
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $tagId = (int)$this->getRequest()->getParam('tag_id');

        if (!$tagId) {
            $this->messageManager->addErrorMessage(__('Tag ID is required.'));
            return $resultRedirect->setPath('*/*/');
        }

        try {
            $tag = $this->tagFactory->create();
            $this->tagResource->load($tag, $tagId);
            if (!$tag->getId()) {
                $this->messageManager->addErrorMessage(__('This tag no longer exists.'));
                return $resultRedirect->setPath('*/*/');
            }

            $this->tagResource->delete($tag);
            $this->messageManager->addSuccessMessage(__('The tag has been deleted.'));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('An error occurred while deleting the tag.'));
        }

        return $resultRedirect->setPath('*/*/');
    }
}
