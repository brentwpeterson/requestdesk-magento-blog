<?php
/**
 * RequestDesk Blog - Category Grid Collection
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Model\ResourceModel\Category\Grid;

use Magento\Framework\Api\Search\SearchResultInterface;
use Magento\Framework\Data\Collection\Db\FetchStrategyInterface;
use Magento\Framework\Data\Collection\EntityFactoryInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult;
use Psr\Log\LoggerInterface;

/**
 * Grid collection for blog categories, with the parent's name and a post count
 * so the grid reads as a tree without opening each row.
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
    public function __construct( // phpcs:ignore Generic.CodeAnalysis.UselessOverridingMethod
        EntityFactoryInterface $entityFactory,
        LoggerInterface $logger,
        FetchStrategyInterface $fetchStrategy,
        ManagerInterface $eventManager,
        $mainTable = 'requestdesk_blog_category',
        $resourceModel = \RequestDesk\Blog\Model\ResourceModel\Category::class
    ) {
        parent::__construct($entityFactory, $logger, $fetchStrategy, $eventManager, $mainTable, $resourceModel);
    }

    /**
     * Init select
     *
     * @return $this
     */
    protected function _initSelect()
    {
        parent::_initSelect();

        $postCount = $this->getConnection()->select()
            ->from(['cp' => $this->getTable('requestdesk_blog_category_post')], [new \Zend_Db_Expr('COUNT(*)')])
            ->where('cp.category_id = main_table.category_id');

        $this->getSelect()
            ->joinLeft(
                ['parent' => $this->getTable('requestdesk_blog_category')],
                'parent.category_id = main_table.parent_id',
                ['parent_name' => 'parent.name']
            )
            ->columns(['post_count' => new \Zend_Db_Expr('(' . $postCount . ')')]);

        // The parent join brings a second name/url_key/status into scope; pin
        // the grid's filters and the fulltext search to this row's own columns.
        foreach (['category_id', 'name', 'url_key', 'status', 'sort_order', 'created_at', 'updated_at'] as $field) {
            $this->addFilterToMap($field, 'main_table.' . $field);
        }
        $this->addFilterToMap('parent_name', 'parent.name');

        return $this;
    }
}
