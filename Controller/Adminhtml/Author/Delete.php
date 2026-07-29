<?php
/**
 * RequestDesk Blog - Author Delete Controller
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
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use RequestDesk\Blog\Model\AuthorFactory;
use RequestDesk\Blog\Model\ResourceModel\Author as AuthorResource;

class Delete extends Action
{
    /**
     * Authorization level
     */
    const ADMIN_RESOURCE = 'RequestDesk_Blog::authors';

    /**
     * @param Context $context
     * @param AuthorFactory $authorFactory
     * @param AuthorResource $authorResource
     * @param ResourceConnection $resource
     */
    public function __construct(
        Context $context,
        private readonly AuthorFactory $authorFactory,
        private readonly AuthorResource $authorResource,
        private readonly ResourceConnection $resource
    ) {
        parent::__construct($context);
    }

    /**
     * Delete a blog author by author_id
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $authorId = (int)$this->getRequest()->getParam('author_id');

        if (!$authorId) {
            $this->messageManager->addErrorMessage(__('Author ID is required.'));
            return $resultRedirect->setPath('*/*/');
        }

        try {
            $author = $this->authorFactory->create();
            $this->authorResource->load($author, $authorId);
            if (!$author->getData('author_id')) {
                $this->messageManager->addErrorMessage(__('This author no longer exists.'));
                return $resultRedirect->setPath('*/*/');
            }

            // Posts keep their legacy free-text byline; author_id is cleared so it
            // cannot point at a record that is gone.
            $this->clearAuthorFromPosts($authorId);
            $this->authorResource->delete($author);
            $this->messageManager->addSuccessMessage(__('The author has been deleted.'));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('An error occurred while deleting the author.'));
        }

        return $resultRedirect->setPath('*/*/');
    }

    /**
     * Detach an author from any posts crediting them.
     *
     * @param int $authorId
     * @return void
     */
    private function clearAuthorFromPosts(int $authorId): void
    {
        $connection = $this->resource->getConnection();
        $connection->update(
            $this->resource->getTableName('requestdesk_blog_post'),
            ['author_id' => null],
            ['author_id = ?' => $authorId]
        );
    }
}
