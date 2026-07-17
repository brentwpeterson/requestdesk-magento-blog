<?php
/**
 * RequestDesk Blog - Admin Users Source (author picker)
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Model\Source;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Data\OptionSourceInterface;

/**
 * Native Magento admin users, for choosing a post's author.
 */
class AdminUsers implements OptionSourceInterface
{
    /**
     * @param ResourceConnection $resource
     */
    public function __construct(
        private readonly ResourceConnection $resource
    ) {
    }

    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName('admin_user'), ['user_id', 'firstname', 'lastname', 'username'])
            ->where('is_active = ?', 1)
            ->order('firstname ASC');

        $options = [['value' => '', 'label' => __('-- None --')]];
        foreach ($connection->fetchAll($select) as $row) {
            $name = trim(($row['firstname'] ?? '') . ' ' . ($row['lastname'] ?? '')) ?: (string) $row['username'];
            $options[] = ['value' => (int) $row['user_id'], 'label' => $name];
        }
        return $options;
    }
}
