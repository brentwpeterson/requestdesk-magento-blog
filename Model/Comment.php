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

    /**
     * Get post id
     *
     * @return int
     */
    public function getPostId(): int
    {
        return (int) $this->getData('post_id');
    }

    /**
     * Get author name
     *
     * @return string
     */
    public function getAuthorName(): string
    {
        return (string) $this->getData('author_name');
    }

    /**
     * Get author email
     *
     * @return string
     */
    public function getAuthorEmail(): string
    {
        return (string) $this->getData('author_email');
    }

    /**
     * Get content
     *
     * @return string
     */
    public function getContent(): string
    {
        return (string) $this->getData('content');
    }

    /**
     * Get status
     *
     * @return string
     */
    public function getStatus(): string
    {
        return (string) $this->getData('status');
    }
}
