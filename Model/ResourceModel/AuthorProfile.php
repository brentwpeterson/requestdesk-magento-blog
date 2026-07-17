<?php
/**
 * RequestDesk Blog - Author Profile Resource Model
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Resource model for requestdesk_blog_author_profile. Keyed by admin_user_id;
 * the id is supplied (not auto-increment), so isPkAutoIncrement is false.
 */
class AuthorProfile extends AbstractDb
{
    /**
     * @var bool
     */
    protected $_isPkAutoIncrement = false;

    /**
     * Honor the model's isObjectNew() flag so a supplied (non-auto-increment)
     * admin_user_id can still be INSERTed for a brand-new profile instead of
     * being treated as an UPDATE that matches zero rows.
     *
     * @var bool
     */
    protected $_useIsObjectNew = true;

    /**
     * @inheritdoc
     */
    protected function _construct(): void
    {
        $this->_init('requestdesk_blog_author_profile', 'admin_user_id');
    }
}
