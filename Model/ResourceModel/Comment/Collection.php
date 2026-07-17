<?php
/**
 * RequestDesk Blog - Comment Collection
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Model\ResourceModel\Comment;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use RequestDesk\Blog\Model\Comment;
use RequestDesk\Blog\Model\ResourceModel\Comment as CommentResource;

/**
 * Collection of blog comments.
 */
class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'comment_id';

    /**
     * @inheritdoc
     */
    protected function _construct(): void
    {
        $this->_init(Comment::class, CommentResource::class);
    }
}
