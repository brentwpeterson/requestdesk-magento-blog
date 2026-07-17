<?php
/**
 * RequestDesk Blog - Comment Model
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Model;

use Magento\Framework\Model\AbstractModel;
use RequestDesk\Blog\Model\ResourceModel\Comment as CommentResource;

/**
 * A blog comment.
 */
class Comment extends AbstractModel
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_SPAM = 'spam';

    /**
     * @inheritdoc
     */
    protected function _construct(): void
    {
        $this->_init(CommentResource::class);
    }

    public function getPostId(): int
    {
        return (int) $this->getData('post_id');
    }

    public function getAuthorName(): string
    {
        return (string) $this->getData('author_name');
    }

    public function getAuthorEmail(): string
    {
        return (string) $this->getData('author_email');
    }

    public function getContent(): string
    {
        return (string) $this->getData('content');
    }

    public function getStatus(): string
    {
        return (string) $this->getData('status');
    }
}
