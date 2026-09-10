<?php
/**
 * RequestDesk Blog - Category Filter Block
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Block;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\StoreManagerInterface;
use RequestDesk\Blog\Api\Data\PostSearchResultsInterface;
use RequestDesk\Blog\Api\PostRepositoryInterface;
use RequestDesk\Blog\Model\AuthorResolver;
use RequestDesk\Blog\Model\Config;
use RequestDesk\Blog\Model\PostCategoryResolver;
use RequestDesk\Blog\Model\PostContent;

/**
 * Supplies a native category name + the blog posts in it.
 *
 * Extends PostList so the category page reuses the exact list template the
 * blog index uses: every card helper (image url, author name, summary html)
 * and the pager plumbing come from the parent, and only the collection
 * differs - filtered to one category instead of all published posts.
 */
class CategoryView extends PostList
{
    /**
     * @param Context $context
     * @param CategoryRepositoryInterface $categoryRepository
     * @param PostCategoryResolver $categoryResolver
     * @param PostRepositoryInterface $postRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param SortOrderBuilder $sortOrderBuilder
     * @param StoreManagerInterface $storeManager
     * @param AuthorResolver $authorResolver
     * @param PostContent $postContent
     * @param Config $config
     * @param array $data
     */
    public function __construct(
        Context $context,
        private readonly CategoryRepositoryInterface $categoryRepository,
        PostCategoryResolver $categoryResolver,
        PostRepositoryInterface $postRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        SortOrderBuilder $sortOrderBuilder,
        StoreManagerInterface $storeManager,
        AuthorResolver $authorResolver,
        PostContent $postContent,
        Config $config,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $postRepository,
            $searchCriteriaBuilder,
            $sortOrderBuilder,
            $storeManager,
            $authorResolver,
            $postContent,
            $config,
            $categoryResolver,
            $data
        );
    }

    /**
     * @return string
     */
    public function getCategoryName(): string
    {
        try {
            return (string) $this->categoryRepository->get($this->getCategoryId())->getName();
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * @return int
     */
    public function getCategoryId(): int
    {
        return (int) $this->getRequest()->getParam('id');
    }

    /**
     * The listing query for this category's posts; paging comes from the parent.
     *
     * @param int|null $pageSize
     * @param int|null $currentPage
     * @return PostSearchResultsInterface
     */
    protected function loadPostResults(?int $pageSize, ?int $currentPage): PostSearchResultsInterface
    {
        $postIds = $this->categoryResolver->getPostIdsInCategory($this->getCategoryId());

        return $this->postRepository->getList($this->buildListCriteria($postIds, $pageSize, $currentPage));
    }

    /**
     * Page heading for the shared list template: the category's own name.
     *
     * @return string
     */
    public function getListingTitle(): string
    {
        return $this->getCategoryName();
    }

    /**
     * The pager stays on the category page, not /blog.
     *
     * @return string
     */
    protected function getPagerRoutePath(): string
    {
        return 'blog/category/view';
    }

    /**
     * @param array $query
     * @return array
     */
    protected function getPagerRouteParams(array $query): array
    {
        return ['id' => $this->getCategoryId(), '_query' => $query];
    }
}
