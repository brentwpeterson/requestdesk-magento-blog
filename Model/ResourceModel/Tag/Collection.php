<?php
/**
 * RequestDesk Blog - Tag Collection
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Model\ResourceModel\Tag;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use RequestDesk\Blog\Model\Tag;
use RequestDesk\Blog\Model\ResourceModel\Tag as TagResource;

/**
 * Collection of blog tags.
 */
class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'tag_id';

    /**
     * @inheritdoc
     */
    protected function _construct(): void
    {
        $this->_init(Tag::class, TagResource::class);
    }
}
