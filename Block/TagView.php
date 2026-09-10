<?php
/**
 * RequestDesk Blog - Tag Archive Block
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
use RequestDesk\Blog\Model\TagResolver;

/**
 * Supplies the tag and its published posts to the tag archive page.
 *
 * Extends PostList so the tag page renders its posts with the same list
 * templates the blog index and category pages use; only the collection
 * differs - filtered to one tag's post ids.
 */
class TagView extends PostList
{
    /**
     * Tri-state memo: null = not loaded yet, false = looked up and missing.
     *
     * @var array|null|false
     */
    private $tag = null;

    /**
     * @param Context $context
     * @param TagResolver $tagResolver
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
        private readonly TagResolver $tagResolver,
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
     * @return array{id:int, name:string, url:string}|null
     */
    public function getTag(): ?array
    {
        if ($this->tag === null) {
            $id = (int) $this->getRequest()->getParam('id');
            $this->tag = $id > 0 ? ($this->tagResolver->getTag($id) ?: false) : false;
        }
        return $this->tag ?: null;
    }

    /**
     * The listing query for this tag's posts; paging comes from the parent.
     *
     * @param int|null $pageSize
     * @param int|null $currentPage
     * @return PostSearchResultsInterface
     */
    protected function loadPostResults(?int $pageSize, ?int $currentPage): PostSearchResultsInterface
    {
        $tag = $this->getTag();
        $postIds = $tag === null
            ? []
            : $this->tagResolver->getPostIdsByTag((int) $tag['id']);

        return $this->postRepository->getList($this->buildListCriteria($postIds, $pageSize, $currentPage));
    }

    /**
     * Page heading for the shared list template: the tag's name.
     *
     * @return string
     */
    public function getListingTitle(): string
    {
        $tag = $this->getTag();
        return $tag !== null ? $tag['name'] : '';
    }

    /**
     * The pager stays on the tag page, not /blog.
     *
     * @return string
     */
    protected function getPagerRoutePath(): string
    {
        return 'blog/tag/view';
    }

    /**
     * @param array $query
     * @return array
     */
    protected function getPagerRouteParams(array $query): array
    {
        return ['id' => (int) $this->getRequest()->getParam('id'), '_query' => $query];
    }
}
