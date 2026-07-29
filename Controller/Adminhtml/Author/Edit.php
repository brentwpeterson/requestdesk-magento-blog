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
use RequestDesk\Blog\Model\AuthorFactory;
use RequestDesk\Blog\Model\ResourceModel\Author as AuthorResource;

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
     * @param AuthorFactory $authorFactory
     * @param AuthorResource $authorResource
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        private readonly AuthorFactory $authorFactory,
        private readonly AuthorResource $authorResource
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
    }

    /**
     * Edit or create a blog author
     *
     * @return \Magento\Framework\View\Result\Page|\Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        $authorId = (int)$this->getRequest()->getParam('author_id');

        if ($authorId) {
            $author = $this->authorFactory->create();
            $this->authorResource->load($author, $authorId);
            if (!$author->getData('author_id')) {
                $this->messageManager->addErrorMessage(__('This author no longer exists.'));
                return $this->resultRedirectFactory->create()->setPath('*/*/');
            }
        }

        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('RequestDesk_Blog::authors_menu');
        $resultPage->getConfig()->getTitle()->prepend(
            $authorId ? __('Edit Author') : __('New Author')
        );

        return $resultPage;
    }
}
