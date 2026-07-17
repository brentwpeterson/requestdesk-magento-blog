<?php
/**
 * RequestDesk Blog - Comment Resource Model
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Resource model for requestdesk_blog_comment.
 */
class Comment extends AbstractDb
{
    /**
     * @inheritdoc
     */
    protected function _construct(): void
    {
        $this->_init('requestdesk_blog_comment', 'comment_id');
    }
}
