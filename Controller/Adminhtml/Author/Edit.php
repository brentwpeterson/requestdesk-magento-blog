<?php
/**
 * RequestDesk Blog - Author Profile Edit/New Controller
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Controller\Adminhtml\Author;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use RequestDesk\Blog\Model\AuthorProfileFactory;
use RequestDesk\Blog\Model\ResourceModel\AuthorProfile as AuthorProfileResource;

class Edit extends Action
{
    /**
     * Authorization level
     */
    const ADMIN_RESOURCE = 'RequestDesk_Blog::authors';

    /**
     * @var PageFactory
     */
    protected PageFactory $resultPageFactory;

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param AuthorProfileFactory $profileFactory
     * @param AuthorProfileResource $profileResource
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        private readonly AuthorProfileFactory $profileFactory,
        private readonly AuthorProfileResource $profileResource
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
    }

    /**
     * Edit or create an author profile (keyed by admin_user_id)
     *
     * @return \Magento\Framework\View\Result\Page|\Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        $userId = (int)$this->getRequest()->getParam('admin_user_id');

        if ($userId) {
            $profile = $this->profileFactory->create();
            $this->profileResource->load($profile, $userId);
            if (!$profile->getData('admin_user_id')) {
                // No profile row yet for this user — that's fine, the form
                // opens pre-set to that admin user so one can be created.
                $profile->setData('admin_user_id', $userId);
            }
        }

        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('RequestDesk_Blog::authors_menu');
        $resultPage->getConfig()->getTitle()->prepend(
            $userId ? __('Edit Author Profile') : __('New Author Profile')
        );

        return $resultPage;
    }
}
