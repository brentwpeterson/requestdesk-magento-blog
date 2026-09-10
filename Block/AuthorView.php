<?php
/**
 * RequestDesk Blog - Author View Block
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Block;

use RequestDesk\Blog\Api\Data\PostSearchResultsInterface;

/**
 * Supplies the author profile + their published posts to the author page.
 *
 * Extends PostList so the author page renders its posts with the same list
 * templates the blog index and category pages use; only the collection
 * differs - filtered to one author's post ids.
 */
class AuthorView extends PostList
{
    /**
     * Tri-state memo: null = not loaded yet, false = looked up and missing.
     *
     * @var array|null|false
     */
    private $author = null;

    /**
     * The resolved author, or null.
     *
     * @return array{id:int, name:string, bio:string, avatar:string, page_url:string, link:string}|null
     */
    public function getAuthor(): ?array
    {
        if ($this->author === null) {
            $id = (int) $this->getRequest()->getParam('id');
            $this->author = $id > 0 ? ($this->authorResolver->getAuthor($id) ?: false) : false;
        }
        return $this->author ?: null;
    }

    /**
     * The listing query for this author's posts; paging comes from the parent.
     *
     * @param int|null $pageSize
     * @param int|null $currentPage
     * @return PostSearchResultsInterface
     */
    protected function loadPostResults(?int $pageSize, ?int $currentPage): PostSearchResultsInterface
    {
        $author = $this->getAuthor();
        $postIds = $author === null
            ? []
            : $this->authorResolver->getPostIdsByAuthor((int) $author['id']);

        return $this->postRepository->getList($this->buildListCriteria($postIds, $pageSize, $currentPage));
    }

    /**
     * Page heading for the shared list template: the author's name.
     *
     * @return string
     */
    public function getListingTitle(): string
    {
        $author = $this->getAuthor();
        return $author !== null ? $author['name'] : '';
    }

    /**
     * The pager stays on the author page, not /blog.
     *
     * @return string
     */
    protected function getPagerRoutePath(): string
    {
        return 'blog/author/view';
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
