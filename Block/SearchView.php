<?php
/**
 * RequestDesk Blog - Search Results Block
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength.TooLong

namespace RequestDesk\Blog\Block;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\StoreManagerInterface;
use RequestDesk\Blog\Api\Data\PostSearchResultsInterface;
use RequestDesk\Blog\Api\PostRepositoryInterface;
use RequestDesk\Blog\Model\AuthorResolver;
use RequestDesk\Blog\Model\BlogSearch;
use RequestDesk\Blog\Model\Config;
use RequestDesk\Blog\Model\PostCategoryResolver;
use RequestDesk\Blog\Model\PostContent;
use RequestDesk\Blog\Model\PostSearch;

/**
 * Supplies the posts matching ?query= to the search results page.
 *
 * Extends PostList like the category, tag and author archives, so results
 * render with the same list templates and pager; only the collection differs.
 *
 * Results are split into Amasty's four tabs - Posts, Authors, Categories,
 * Tags - each counting the published posts it would list. ?tab= picks one;
 * the pager carries it. See Model\BlogSearch for what each tab matches.
 */
class SearchView extends PostList
{
    public const QUERY_VAR_NAME = 'query';
    public const TAB_VAR_NAME = 'tab';

    /** @var array<string, int[]> post ids per tab, for this request */
    private array $postIdsByTab = [];

    /**
     * @param Context $context
     * @param BlogSearch $blogSearch
     * @param PostRepositoryInterface $postRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param SortOrderBuilder $sortOrderBuilder
     * @param StoreManagerInterface $storeManager
     * @param AuthorResolver $authorResolver
     * @param PostContent $postContent
     * @param Config $config
     * @param PostCategoryResolver $categoryResolver
     * @param Config $searchConfig the same Config; the parent keeps its copy private
     * @param array $data
     */
    public function __construct(
        Context $context,
        private readonly BlogSearch $blogSearch,
        PostRepositoryInterface $postRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        SortOrderBuilder $sortOrderBuilder,
        StoreManagerInterface $storeManager,
        AuthorResolver $authorResolver,
        PostContent $postContent,
        Config $config,
        PostCategoryResolver $categoryResolver,
        private readonly Config $searchConfig,
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
        $postIds = $this->getTabPostIds($this->getActiveTab());

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
            ? (string) __("Search results for '%1'", $query)
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
            ? (string) __('No posts in this tab match your search.')
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
        $tab = $this->getActiveTab();

        return [
            self::QUERY_VAR_NAME => $this->getSearchQuery(),
            self::TAB_VAR_NAME => $tab !== BlogSearch::TYPE_POSTS ? $tab : null,
        ];
    }

    /**
     * The tab being shown; an unknown or missing ?tab= means Posts.
     *
     * @return string one of BlogSearch::TYPES
     */
    public function getActiveTab(): string
    {
        $tab = (string) $this->getRequest()->getParam(self::TAB_VAR_NAME);

        return in_array($tab, BlogSearch::TYPES, true) ? $tab : BlogSearch::TYPE_POSTS;
    }

    /**
     * The four tabs, in order, for the results page; empty without a query.
     *
     * @return array<int, array{type:string, label:string, count:int, url:string, active:bool}>
     */
    public function getSearchTabs(): array
    {
        $query = $this->getSearchQuery();
        if ($query === '') {
            return [];
        }

        $active = $this->getActiveTab();
        $tabs = [];
        foreach (BlogSearch::TYPES as $type) {
            $tabs[] = [
                'type' => $type,
                'label' => (string) self::tabLabel($type),
                'count' => count($this->getTabPostIds($type)),
                'url' => BlogUrl::resolve(
                    $this->searchConfig->getUrlPrefix(),
                    'search',
                    [
                        self::QUERY_VAR_NAME => $query,
                        self::TAB_VAR_NAME => $type !== BlogSearch::TYPE_POSTS ? $type : null,
                    ],
                    $this->_urlBuilder
                ),
                'active' => $type === $active,
            ];
        }

        return $tabs;
    }

    /**
     * Display name of a tab / suggestion group.
     *
     * @param string $type
     * @return \Magento\Framework\Phrase
     */
    public static function tabLabel(string $type): \Magento\Framework\Phrase // phpcs:ignore Magento2.Functions.StaticFunction
    {
        return match ($type) {
            BlogSearch::TYPE_AUTHORS => __('Authors'),
            BlogSearch::TYPE_CATEGORIES => __('Categories'),
            BlogSearch::TYPE_TAGS => __('Tags'),
            default => __('Posts'),
        };
    }

    /**
     * Post ids behind one tab, worked out once per request.
     *
     * The tab bar needs all four counts and the listing needs the active one
     * again.
     *
     * @param string $type
     * @return int[]
     */
    private function getTabPostIds(string $type): array
    {
        if (!isset($this->postIdsByTab[$type])) {
            $this->postIdsByTab[$type] = $this->blogSearch->getPostIds($type, $this->getSearchQuery());
        }

        return $this->postIdsByTab[$type];
    }
}
