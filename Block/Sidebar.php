<?php
/**
 * RequestDesk Blog - Sidebar Block
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use RequestDesk\Blog\Model\Config;
use RequestDesk\Blog\Model\PostCategoryResolver;
use RequestDesk\Blog\Model\PostSearch;

/**
 * The blog sidebar: a search box and the category list with post counts,
 * matching the sidebar of the Amasty blog it replaces on evrig.com.
 *
 * Added to every blog page through the requestdesk_blog_sidebar layout handle.
 */
class Sidebar extends Template
{
    /**
     * @var array<int, array{id:int, name:string, url:string, count:int}>|null
     */
    private ?array $categories = null;

    /**
     * @param Context $context
     * @param PostCategoryResolver $categoryResolver
     * @param Config $config
     * @param array $data
     */
    public function __construct(
        Context $context,
        private readonly PostCategoryResolver $categoryResolver,
        private readonly Config $config,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Where the search box submits.
     *
     * @return string
     */
    public function getSearchUrl(): string
    {
        return BlogUrl::resolve($this->config->getUrlPrefix(), 'search', [], $this->_urlBuilder);
    }

    /**
     * Where the search box fetches live suggestions from.
     *
     * @return string
     */
    public function getSuggestUrl(): string
    {
        return BlogUrl::resolve($this->config->getUrlPrefix(), 'search/suggest', [], $this->_urlBuilder);
    }

    /**
     * Characters typed before suggestions are fetched.
     *
     * @return int
     */
    public function getSearchMinCharacters(): int
    {
        return $this->config->getSearchMinCharacters();
    }

    /**
     * Name of the search box's field.
     *
     * @return string
     */
    public function getSearchParamName(): string
    {
        return SearchView::QUERY_VAR_NAME;
    }

    /**
     * The current search text, so the box keeps it on the results page.
     *
     * @return string
     */
    public function getSearchQuery(): string
    {
        return PostSearch::normalizeQuery($this->getRequest()->getParam(SearchView::QUERY_VAR_NAME));
    }

    /**
     * Get search max length
     *
     * @return int
     */
    public function getSearchMaxLength(): int
    {
        return PostSearch::MAX_QUERY_LENGTH;
    }

    /**
     * Categories with published posts, largest first.
     *
     * @return array<int, array{id:int, name:string, url:string, count:int}>
     */
    public function getCategories(): array
    {
        if ($this->categories === null) {
            $this->categories = $this->categoryResolver->getCategoriesWithPostCounts();
        }

        return $this->categories;
    }

    /**
     * Whether this is the category archive being viewed, to mark it in the list.
     *
     * @param int $categoryId
     * @return bool
     */
    public function isCurrentCategory(int $categoryId): bool
    {
        $request = $this->getRequest();

        return $request->getControllerName() === 'category'
            && (int) $request->getParam('id') === $categoryId;
    }
}
