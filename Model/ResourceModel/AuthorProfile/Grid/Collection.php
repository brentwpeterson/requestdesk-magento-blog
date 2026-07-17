<?php
/**
 * RequestDesk Blog - Author Profile Grid Collection
 *
 * Joins the profile to its native admin_user so the grid can show the
 * underlying username / real name alongside the public display name.
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Model\ResourceModel\AuthorProfile\Grid;

use Magento\Framework\Api\Search\SearchResultInterface;
use Magento\Framework\Data\Collection\Db\FetchStrategyInterface;
use Magento\Framework\Data\Collection\EntityFactoryInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult;
use Psr\Log\LoggerInterface;

/**
 * Grid collection for author profiles with admin_user join.
 */
class Collection extends SearchResult implements SearchResultInterface
{
    /**
     * @param EntityFactoryInterface $entityFactory
     * @param LoggerInterface $logger
     * @param FetchStrategyInterface $fetchStrategy
     * @param ManagerInterface $eventManager
     * @param string $mainTable
     * @param string $resourceModel
     */
    public function __construct(
        EntityFactoryInterface $entityFactory,
        LoggerInterface $logger,
        FetchStrategyInterface $fetchStrategy,
        ManagerInterface $eventManager,
        $mainTable = 'requestdesk_blog_author_profile',
        $resourceModel = \RequestDesk\Blog\Model\ResourceModel\AuthorProfile::class
    ) {
        parent::__construct($entityFactory, $logger, $fetchStrategy, $eventManager, $mainTable, $resourceModel);
    }

    /**
     * Join the native admin_user for name/username display.
     *
     * @return $this
     */
    protected function _initSelect()
    {
        parent::_initSelect();
        $this->getSelect()->joinLeft(
            ['au' => $this->getTable('admin_user')],
            'main_table.admin_user_id = au.user_id',
            [
                'username' => 'au.username',
                'admin_name' => new \Zend_Db_Expr("TRIM(CONCAT(COALESCE(au.firstname,''),' ',COALESCE(au.lastname,'')))"),
            ]
        );
        return $this;
    }
}
