<?php
/**
 * RequestDesk Blog - Author Profile Model
 *
 * Public blog profile that extends a NATIVE admin_user. The primary key is the
 * admin_user_id — there is no separate identity; a profile always belongs to an
 * existing admin user.
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Model;

use Magento\Framework\Model\AbstractModel;
use RequestDesk\Blog\Model\ResourceModel\AuthorProfile as AuthorProfileResource;

/**
 * A blog author's public profile.
 */
class AuthorProfile extends AbstractModel
{
    /**
     * @inheritdoc
     */
    protected function _construct(): void
    {
        $this->_init(AuthorProfileResource::class);
    }
}
