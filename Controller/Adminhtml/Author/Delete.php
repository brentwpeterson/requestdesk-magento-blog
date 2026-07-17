<?php
/**
 * RequestDesk Blog - Author Profile Delete Controller
 *
 * Removes the public profile only. The underlying native admin_user is left
 * intact — deleting an admin account is Magento's own concern.
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Controller\Adminhtml\Author;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Exception\LocalizedException;
use RequestDesk\Blog\Model\AuthorProfileFactory;
use RequestDesk\Blog\Model\ResourceModel\AuthorProfile as AuthorProfileResource;

class Delete extends Action
{
    /**
     * Authorization level
     */
    const ADMIN_RESOURCE = 'RequestDesk_Blog::authors';

    /**
     * @param Context $context
     * @param AuthorProfileFactory $profileFactory
     * @param AuthorProfileResource $profileResource
     */
    public function __construct(
        Context $context,
        private readonly AuthorProfileFactory $profileFactory,
        private readonly AuthorProfileResource $profileResource
    ) {
        parent::__construct($context);
    }

    /**
     * Delete an author profile by admin_user_id
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $userId = (int)$this->getRequest()->getParam('admin_user_id');

        if (!$userId) {
            $this->messageManager->addErrorMessage(__('Author profile ID is required.'));
            return $resultRedirect->setPath('*/*/');
        }

        try {
            $profile = $this->profileFactory->create();
            $this->profileResource->load($profile, $userId);
            if (!$profile->getData('admin_user_id')) {
                $this->messageManager->addErrorMessage(__('This author profile no longer exists.'));
                return $resultRedirect->setPath('*/*/');
            }

            $this->profileResource->delete($profile);
            $this->messageManager->addSuccessMessage(__('The author profile has been deleted.'));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('An error occurred while deleting the author profile.'));
        }

        return $resultRedirect->setPath('*/*/');
    }
}
