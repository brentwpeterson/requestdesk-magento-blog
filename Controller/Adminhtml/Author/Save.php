<?php
/**
 * RequestDesk Blog - Author Profile Save Controller
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
use RequestDesk\Blog\Model\AuthorProfileFactory;
use RequestDesk\Blog\Model\ResourceModel\AuthorProfile as AuthorProfileResource;

class Save extends Action
{
    /**
     * Authorization level
     */
    const ADMIN_RESOURCE = 'RequestDesk_Blog::authors';

    /**
     * @param Context $context
     * @param AuthorProfileFactory $profileFactory
     * @param AuthorProfileResource $profileResource
     * @param ResourceConnection $resource
     */
    public function __construct(
        Context $context,
        private readonly AuthorProfileFactory $profileFactory,
        private readonly AuthorProfileResource $profileResource,
        private readonly ResourceConnection $resource
    ) {
        parent::__construct($context);
    }

    /**
     * Upsert an author profile keyed by admin_user_id
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $data = $this->getRequest()->getPostValue();

        if (!$data) {
            return $resultRedirect->setPath('*/*/');
        }

        $userId = isset($data['admin_user_id']) ? (int)$data['admin_user_id'] : 0;

        try {
            if (!$userId) {
                throw new LocalizedException(__('Please choose an admin user for this profile.'));
            }
            if (!$this->adminUserExists($userId)) {
                throw new LocalizedException(__('The selected admin user does not exist.'));
            }

            // Upsert: load the current row if present, otherwise start fresh.
            $profile = $this->profileFactory->create();
            $this->profileResource->load($profile, $userId);

            $profile->setData('admin_user_id', $userId);
            $profile->setData('display_name', trim((string)($data['display_name'] ?? '')));
            $profile->setData('bio', (string)($data['bio'] ?? ''));
            $profile->setData('avatar', trim((string)($data['avatar'] ?? '')));
            $profile->setData('url', trim((string)($data['url'] ?? '')));

            // Non-auto-increment PK: after a fresh load the model has no id set,
            // so force it to persist as an insert-or-update on admin_user_id.
            if (!$profile->getOrigData('admin_user_id')) {
                $profile->isObjectNew(true);
            }

            $this->profileResource->save($profile);

            $this->messageManager->addSuccessMessage(__('The author profile has been saved.'));

            if ($this->getRequest()->getParam('back')) {
                return $resultRedirect->setPath('*/*/edit', ['admin_user_id' => $userId]);
            }

            return $resultRedirect->setPath('*/*/');
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('An error occurred while saving the author profile.'));
        }

        if ($userId) {
            return $resultRedirect->setPath('*/*/edit', ['admin_user_id' => $userId]);
        }
        return $resultRedirect->setPath('*/*/edit');
    }

    /**
     * Does a native admin user with this id exist?
     *
     * @param int $userId
     * @return bool
     */
    private function adminUserExists(int $userId): bool
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName('admin_user'), ['user_id'])
            ->where('user_id = ?', $userId)
            ->limit(1);
        return (bool)$connection->fetchOne($select);
    }
}
