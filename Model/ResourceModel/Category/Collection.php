<?php
/**
 * RequestDesk Blog - Category Collection
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Model\ResourceModel\Category;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use RequestDesk\Blog\Model\Category;
use RequestDesk\Blog\Model\ResourceModel\Category as CategoryResource;

/**
 * Collection of blog categories.
 */
class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'category_id';

    /**
     * @inheritdoc
     */
    protected function _construct(): void
    {
        $this->_init(Category::class, CategoryResource::class);
    }
}
