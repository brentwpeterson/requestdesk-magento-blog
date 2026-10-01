<?php
/**
 * RequestDesk Blog - Category Resource Model
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Model\ResourceModel;

use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Resource model for requestdesk_blog_category.
 */
class Category extends AbstractDb
{
    /**
     * @inheritdoc
     */
    protected function _construct(): void
    {
        $this->_init('requestdesk_blog_category', 'category_id');
    }

    /**
     * Hand a deleted category's children to its own parent.
     *
     * The parent_id column carries no FK, so without this the children would point at a
     * row that no longer exists and drop out of every tree built from the table.
     * Moving them up one level keeps them, and their posts, reachable.
     *
     * @param AbstractModel $object
     * @return $this
     */
    protected function _beforeDelete(AbstractModel $object)
    {
        $this->getConnection()->update(
            $this->getMainTable(),
            ['parent_id' => (int) $object->getData('parent_id')],
            ['parent_id = ?' => (int) $object->getId()]
        );

        return parent::_beforeDelete($object);
    }

    /**
     * Every category id below $categoryId, at any depth.
     *
     * Used to refuse a parent that would close a loop. Walks parent_id level by
     * level rather than trusting a stored path, since there is none to trust.
     *
     * @param int $categoryId
     * @return int[]
     */
    public function getDescendantIds(int $categoryId): array
    {
        $connection = $this->getConnection();
        $found = [];
        $frontier = [$categoryId];

        while ($frontier !== []) {
            $children = array_map('intval', $connection->fetchCol(
                $connection->select()
                    ->from($this->getMainTable(), ['category_id'])
                    ->where('parent_id IN (?)', $frontier)
            ));
            // array_diff guards a cycle already in the data from looping forever.
            $frontier = array_values(array_diff($children, $found, [$categoryId]));
            $found = array_merge($found, $frontier);
        }

        return $found;
    }
}
