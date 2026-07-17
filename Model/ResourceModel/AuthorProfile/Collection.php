<?php
/**
 * RequestDesk Blog - Author Profile Collection
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Model\ResourceModel\AuthorProfile;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use RequestDesk\Blog\Model\AuthorProfile;
use RequestDesk\Blog\Model\ResourceModel\AuthorProfile as AuthorProfileResource;

/**
 * Collection of author profiles.
 */
class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'admin_user_id';

    /**
     * @inheritdoc
     */
    protected function _construct(): void
    {
        $this->_init(AuthorProfile::class, AuthorProfileResource::class);
    }
}
