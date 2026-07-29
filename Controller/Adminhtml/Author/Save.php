<?php
/**
 * RequestDesk Blog - Author Save Controller
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Controller\Adminhtml\Author;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Catalog\Model\ImageUploader;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;
use RequestDesk\Blog\Model\AuthorFactory;
use RequestDesk\Blog\Model\ResourceModel\Author as AuthorResource;

class Save extends Action
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
     * @param ImageUploader $imageUploader
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        private readonly AuthorFactory $authorFactory,
        private readonly AuthorResource $authorResource,
        private readonly ResourceConnection $resource,
        private readonly ImageUploader $imageUploader,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    /**
     * Create or update a blog author.
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

        $authorId = isset($data['author_id']) ? (int)$data['author_id'] : 0;

        try {
            $name = trim((string)($data['name'] ?? ''));
            if ($name === '') {
                throw new LocalizedException(__('Please enter a name for this author.'));
            }

            // Optional link to a Magento account — an author does not need one.
            $adminUserId = !empty($data['admin_user_id']) ? (int)$data['admin_user_id'] : null;
            if ($adminUserId !== null) {
                if (!$this->adminUserExists($adminUserId)) {
                    throw new LocalizedException(__('The selected admin user does not exist.'));
                }
                if ($this->adminUserTaken($adminUserId, $authorId)) {
                    throw new LocalizedException(
                        __('That admin user is already linked to another blog author.')
                    );
                }
            }

            $author = $this->authorFactory->create();
            if ($authorId) {
                $this->authorResource->load($author, $authorId);
                if (!$author->getData('author_id')) {
                    throw new LocalizedException(__('This author no longer exists.'));
                }
            }

            $author->setData('name', $name);
            $author->setData('admin_user_id', $adminUserId);
            $author->setData('bio', (string)($data['bio'] ?? ''));
            $author->setData('avatar', $this->resolveAvatar($data));
            $author->setData('url', trim((string)($data['url'] ?? '')));
            $author->setData('url_key', $this->uniqueUrlKey(
                trim((string)($data['url_key'] ?? '')) ?: $name,
                $authorId
            ));

            $this->authorResource->save($author);
            $savedId = (int)$author->getData('author_id');

            $this->messageManager->addSuccessMessage(__('The author has been saved.'));

            if ($this->getRequest()->getParam('back')) {
                return $resultRedirect->setPath('*/*/edit', ['author_id' => $savedId]);
            }

            return $resultRedirect->setPath('*/*/');
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->logger->error('RequestDesk Blog: failed to save author', ['exception' => $e]);
            $this->messageManager->addErrorMessage(__('An error occurred while saving the author.'));
        }

        if ($authorId) {
            return $resultRedirect->setPath('*/*/edit', ['author_id' => $authorId]);
        }
        return $resultRedirect->setPath('*/*/edit');
    }

    /**
     * Resolve the avatar path to store.
     *
     * The image uploader posts a list of files. A newly uploaded one still sits
     * in the tmp folder and carries tmp_name, so it gets moved into place; a
     * retained one only echoes its stored name back. A bare string is a legacy
     * path typed into the old text field, which stays valid.
     *
     * @param array $data
     * @return string
     */
    private function resolveAvatar(array $data): string
    {
        $avatar = $data['avatar'] ?? '';

        if (!is_array($avatar)) {
            return trim((string)$avatar);
        }

        $file = reset($avatar);
        if (!is_array($file) || empty($file['name'])) {
            return '';
        }

        if (!empty($file['tmp_name'])) {
            return $this->imageUploader->moveFileFromTmp((string)$file['name']);
        }

        return (string)$file['name'];
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

    /**
     * Is this admin user already linked to a different author?
     *
     * @param int $userId
     * @param int $authorId
     * @return bool
     */
    private function adminUserTaken(int $userId, int $authorId): bool
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName('requestdesk_blog_author'), ['author_id'])
            ->where('admin_user_id = ?', $userId)
            ->limit(1);
        $existing = (int)$connection->fetchOne($select);
        return $existing !== 0 && $existing !== $authorId;
    }

    /**
     * Slugify and de-duplicate the author's URL key.
     *
     * @param string $value
     * @param int $authorId
     * @return string
     */
    private function uniqueUrlKey(string $value, int $authorId): string
    {
        $base = strtolower(trim($value));
        $base = (string)preg_replace('/[^a-z0-9]+/', '-', $base);
        $base = trim($base, '-') ?: 'author';

        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('requestdesk_blog_author');
        $candidate = $base;
        $i = 2;
        while (true) {
            $owner = (int)$connection->fetchOne(
                $connection->select()->from($table, ['author_id'])->where('url_key = ?', $candidate)->limit(1)
            );
            if ($owner === 0 || $owner === $authorId) {
                return $candidate;
            }
            $candidate = $base . '-' . $i++;
        }
    }
}
