<?php
/**
 * RequestDesk Blog - Admin Comments Grid
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Controller\Adminhtml\Comment;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

/**
 * Renders the comment moderation grid.
 */
class Index extends Action
{
    /**
     * @inheritdoc
     */
    public const ADMIN_RESOURCE = 'RequestDesk_Blog::comments';

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     */
    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
    }

    /**
     * @return Page
     */
    public function execute(): Page
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('RequestDesk_Blog::comments_menu');
        $resultPage->getConfig()->getTitle()->prepend(__('Blog Comments'));
        return $resultPage;
    }
}
