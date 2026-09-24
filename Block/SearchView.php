<?php
/**
 * RequestDesk Blog - Search Results Block
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Block;

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
use RequestDesk\Blog\Model\PostSearch;

/**
 * Supplies the posts matching ?query= to the search results page.
 *
 * Extends PostList like the category, tag and author archives, so results
 * render with the same list templates and pager; only the collection differs.
 */
class SearchView extends PostList
{
    public const QUERY_VAR_NAME = 'query';

    /**
     * @param Context $context
     * @param PostSearch $postSearch
     * @param PostRepositoryInterface $postRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param SortOrderBuilder $sortOrderBuilder
     * @param StoreManagerInterface $storeManager
     * @param AuthorResolver $authorResolver
     * @param PostContent $postContent
     * @param Config $config
     * @param PostCategoryResolver $categoryResolver
     * @param array $data
     */
    public function __construct(
        Context $context,
        private readonly PostSearch $postSearch,
        PostRepositoryInterface $postRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        SortOrderBuilder $sortOrderBuilder,
        StoreManagerInterface $storeManager,
        AuthorResolver $authorResolver,
        PostContent $postContent,
        Config $config,
        PostCategoryResolver $categoryResolver,
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
     * The search box's text, trimmed and capped.
     *
     * @return string
     */
    public function getSearchQuery(): string
    {
        return PostSearch::normalizeQuery($this->getRequest()->getParam(self::QUERY_VAR_NAME));
    }

    /**
     * The listing query for the matching posts; paging comes from the parent.
     *
     * @param int|null $pageSize
     * @param int|null $currentPage
     * @return PostSearchResultsInterface
     */
    protected function loadPostResults(?int $pageSize, ?int $currentPage): PostSearchResultsInterface
    {
        $postIds = $this->postSearch->findPostIds($this->getSearchQuery());

        return $this->postRepository->getList($this->buildListCriteria($postIds, $pageSize, $currentPage));
    }

    /**
     * Page heading for the shared list template.
     *
     * @return string
     */
    public function getListingTitle(): string
    {
        $query = $this->getSearchQuery();

        return $query !== ''
            ? (string) __('Search results for "%1"', $query)
            : (string) __('Search the blog');
    }

    /**
     * "No posts yet" would read as an empty blog, not an empty search.
     *
     * @return string
     */
    public function getEmptyListMessage(): string
    {
        return $this->getSearchQuery() !== ''
            ? (string) __('No posts match your search.')
            : (string) __('Type a word or two into the search box.');
    }

    /**
     * The pager stays on the results page.
     *
     * @return string
     */
    protected function getPagerPath(): string
    {
        return 'search';
    }

    /**
     * The pager keeps the search text on every page.
     *
     * @return array<string, string>
     */
    protected function getPagerQuery(): array
    {
        return [self::QUERY_VAR_NAME => $this->getSearchQuery()];
    }
}
