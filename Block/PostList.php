<?php
/**
 * RequestDesk Blog - Post List Block (frontend, theme-agnostic)
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Block;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\StoreManagerInterface;
use RequestDesk\Blog\Api\Data\PostInterface;
use RequestDesk\Blog\Api\Data\PostSearchResultsInterface;
use RequestDesk\Blog\Api\PostRepositoryInterface;
use RequestDesk\Blog\Model\AuthorResolver;
use RequestDesk\Blog\Model\BlogCache;
use RequestDesk\Blog\Model\Config;
use RequestDesk\Blog\Model\PostCategoryResolver;
use RequestDesk\Blog\Model\PostContent;

/**
 * Supplies published posts to the list templates (Luma and Hyva both use this).
 */
class PostList extends Template implements IdentityInterface
{
    /**
     * Query string parameters, same convention as catalog listings.
     */
    public const PAGE_VAR_NAME = 'p';
    private const LIMIT_VAR_NAME = 'limit';

    /**
     * Placeholder for "match nothing": post ids start at 1, so an IN (0)
     * filter matches no post without a separate empty-result code path.
     */
    protected const NO_MATCH_POST_ID = 0;

    /**
     * Memoised so the items and the total count share one query.
     *
     * @var PostSearchResultsInterface|null
     */
    private ?PostSearchResultsInterface $postResults = null;

    /**
     * Categories per post id for the current page, resolved once.
     *
     * @var array<int, array<int, array{id:int, name:string, url:string}>>|null
     */
    private ?array $postCategories = null;

    /**
     * Card authors keyed by author id and byline, see getCardAuthor().
     *
     * @var array<string, array|null>
     */
    private array $cardAuthors = [];

    /**
     * @param Context $context
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
        protected readonly PostRepositoryInterface $postRepository,
        protected readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        protected readonly SortOrderBuilder $sortOrderBuilder,
        private readonly StoreManagerInterface $storeManager,
        protected readonly AuthorResolver $authorResolver,
        private readonly PostContent $postContent,
        private readonly Config $config,
        protected readonly PostCategoryResolver $categoryResolver,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Resolved author name for a post (matches the post view).
     *
     * @param PostInterface $post
     * @return string
     */
    public function getAuthorName(PostInterface $post): string
    {
        $author = $this->getCardAuthor($post);
        return $author !== null ? $author['name'] : (string) $post->getAuthor();
    }

    /**
     * Author page URL for a card's byline; empty when the author has none.
     *
     * @param PostInterface $post
     * @return string
     */
    public function getAuthorUrl(PostInterface $post): string
    {
        $author = $this->getCardAuthor($post);
        return $author !== null ? $author['page_url'] : '';
    }

    /**
     * The resolved author, looked up once per author id rather than per card.
     *
     * @param PostInterface $post
     * @return array{id:?int, name:string, bio:string, avatar:string, page_url:string, link:string}|null
     */
    private function getCardAuthor(PostInterface $post): ?array
    {
        $key = (string) $post->getAuthorId() . '|' . $post->getAuthor();
        if (!array_key_exists($key, $this->cardAuthors)) {
            $this->cardAuthors[$key] = $this->authorResolver->getAuthorForPost($post);
        }

        return $this->cardAuthors[$key];
    }

    /**
     * Native categories shown on a listing card, as [id, name, url].
     *
     * Resolved for the whole page on the first call so the card loop in the
     * template does not become one query per post.
     *
     * @param PostInterface $post
     * @return array<int, array{id:int, name:string, url:string}>
     */
    public function getPostCategories(PostInterface $post): array
    {
        if ($this->postCategories === null) {
            $postIds = array_map(
                static fn (PostInterface $pagePost): int => (int) $pagePost->getPostId(),
                $this->getPosts()
            );
            $this->postCategories = $this->categoryResolver->getCategoriesForPosts($postIds);
        }

        return $this->postCategories[(int) $post->getPostId()] ?? [];
    }

    /**
     * A card's date line, e.g. "August 12, 2026", in the store locale.
     *
     * @param PostInterface $post
     * @return string
     */
    public function getPostDate(PostInterface $post): string
    {
        return $post->getCreatedAt()
            ? (string) $this->formatDate($post->getCreatedAt(), \IntlDateFormatter::LONG)
            : '';
    }

    /**
     * Published posts for the current page, newest first.
     *
     * @return PostInterface[]
     */
    public function getPosts(): array
    {
        return $this->getPostResults()->getItems();
    }

    /**
     * Runs the listing query once and keeps the result for the whole render.
     *
     * The template asks for the items and the page count separately; without
     * memoising, that is two identical queries per request.
     *
     * @return PostSearchResultsInterface
     */
    private function getPostResults(): PostSearchResultsInterface
    {
        if ($this->postResults === null) {
            $this->postResults = $this->isPaginationEnabled()
                ? $this->loadPostResults($this->getPostsPerPage(), $this->getCurrentPage())
                : $this->loadPostResults(null, null);
        }

        return $this->postResults;
    }

    /**
     * The listing query itself: all published posts, newest first.
     *
     * Subclasses (category/author/tag views) override this to filter the same
     * base shape by their own post ids. A null $pageSize means pagination is
     * disabled and every post loads in one go.
     *
     * @param int|null $pageSize
     * @param int|null $currentPage
     * @return PostSearchResultsInterface
     */
    protected function loadPostResults(?int $pageSize, ?int $currentPage): PostSearchResultsInterface
    {
        return $this->postRepository->getList($this->buildListCriteria(null, $pageSize, $currentPage));
    }

    /**
     * Shared criteria shape: published only, newest first, paged when asked.
     *
     * $postIds null means "every published post" (the plain blog list); an
     * empty array means "deliberately nothing" and is sent as IN (0), since
     * post ids start at 1 - one code path instead of special-cased empties.
     *
     * @param int[]|null $postIds
     * @param int|null $pageSize
     * @param int|null $currentPage
     * @return \Magento\Framework\Api\SearchCriteriaInterface
     */
    protected function buildListCriteria(
        ?array $postIds,
        ?int $pageSize,
        ?int $currentPage
    ): \Magento\Framework\Api\SearchCriteriaInterface {
        $sort = $this->sortOrderBuilder
            ->setField(PostInterface::CREATED_AT)
            ->setDirection('DESC')
            ->create();

        $criteria = $this->searchCriteriaBuilder
            ->addFilter(PostInterface::STATUS, PostInterface::STATUS_PUBLISHED)
            ->addSortOrder($sort);
        if ($postIds !== null) {
            $criteria->addFilter(PostInterface::POST_ID, $postIds ?: [self::NO_MATCH_POST_ID], 'in');
        }
        if ($pageSize !== null) {
            $criteria->setPageSize($pageSize)->setCurrentPage($currentPage ?? 1);
        }

        return $criteria->create();
    }

    /**
     * Whether the pager renders anywhere it is included.
     *
     * @return bool
     */
    protected function isPaginationEnabled(): bool
    {
        return $this->config->isPaginationEnabled();
    }

    /**
     * Page size for this request: the ?limit= override if usable, else config.
     *
     * Stays public because the templates and PostListPerPageTest both read it.
     *
     * @return int
     */
    public function getPostsPerPage(): int
    {
        return $this->config->resolvePageSize($this->getRequestedLimit());
    }

    /**
     * The ?limit= override, or null when absent or not a usable number.
     *
     * Anything that is not a positive integer ('', 'abc', '-5', '0') becomes
     * null so resolvePageSize() falls back to the admin value rather than to
     * its own ceiling.
     *
     * @return int|null
     */
    private function getRequestedLimit(): ?int
    {
        $requested = $this->getRequest()->getParam(self::LIMIT_VAR_NAME);

        return is_numeric($requested) && (int) $requested > 0 ? (int) $requested : null;
    }

    /**
     * Requested page number, floored at 1.
     *
     * @return int
     */
    public function getCurrentPage(): int
    {
        return max(1, (int) $this->getRequest()->getParam(self::PAGE_VAR_NAME, 1));
    }

    /**
     * Number of the last page, always at least 1.
     *
     * @return int
     */
    public function getLastPageNumber(): int
    {
        $total = (int) $this->getPostResults()->getTotalCount();

        return max(1, (int) ceil($total / $this->getPostsPerPage()));
    }

    /**
     * Whether the pager should be drawn: config on AND more than one page.
     *
     * The config check comes first so a disabled pager never triggers the
     * total-count work behind getLastPageNumber().
     *
     * Must exist as a real method: DataObject::__call() answers any undefined
     * has*() with a lookup in $_data, so a missing one here would quietly
     * return false and the template would simply never draw the pager.
     *
     * @return bool
     */
    public function hasPagination(): bool
    {
        return $this->isPaginationEnabled() && $this->getLastPageNumber() > 1;
    }

    /**
     * Whether ?p= asks for a page this listing does not have.
     *
     * The repository answers a past-the-end page with page 1's posts, so
     * without this check one list would live under endless ?p= URLs. With the
     * pager off, everything sits on page 1 and any higher number is past it.
     *
     * @return bool
     */
    public function isPageOutOfRange(): bool
    {
        $lastPage = $this->isPaginationEnabled() ? $this->getLastPageNumber() : 1;

        return $this->getCurrentPage() > $lastPage;
    }

    /**
     * Heading for the shared listing template; empty means "Blog".
     *
     * Blocks that reuse the list template with a filtered collection
     * (CategoryView) override this so the page title matches what is shown.
     *
     * @return string
     */
    public function getListingTitle(): string
    {
        return $this->config->getBlogTitle();
    }

    /**
     * Intro HTML under the heading, directives resolved; empty for none.
     *
     * @return string
     */
    public function getListingDescriptionHtml(): string
    {
        $description = trim($this->getListingDescription());

        return $description === '' ? '' : $this->postContent->render($description);
    }

    /**
     * Raw intro for the listing. CategoryView supplies the category description.
     *
     * @return string
     */
    protected function getListingDescription(): string
    {
        return '';
    }

    /**
     * What the list templates say when there are no posts.
     *
     * Empty means the template's own wording.
     *
     * @return string
     */
    public function getEmptyListMessage(): string
    {
        return '';
    }

    /**
     * URL for a given page. Page 1 drops the parameter to keep the page canonical.
     *
     * An active ?limit= has to be carried over explicitly or page 2 would
     * revert to the configured size.
     *
     * Built on getListingUrl(), so a category, author or tag pages under its
     * own address (/blog/category/general?p=2), the one the Amasty blog used,
     * not under the internal id route.
     *
     * @param int $page
     * @return string
     */
    public function getPageUrl(int $page): string
    {
        $query = [self::PAGE_VAR_NAME => $page > 1 ? $page : null];

        $limit = $this->getRequestedLimit();
        if ($limit !== null) {
            $query[self::LIMIT_VAR_NAME] = $limit;
        }

        return $this->withQuery($this->getListingUrl(), $query + $this->getPagerQuery());
    }

    /**
     * Canonical URL of the page being shown; empty for none.
     *
     * The listing's own address plus ?p= past page 1, without ?limit=, as the
     * Amasty blog wrote it. One address per page of results, however it was
     * reached (/blog/, /blog/category/view/id/1, ?limit=...).
     *
     * @return string
     */
    public function getCanonicalUrl(): string
    {
        $page = $this->getCurrentPage();

        return $this->withQuery($this->getListingUrl(), [self::PAGE_VAR_NAME => $page > 1 ? $page : null]);
    }

    /**
     * The listing's own address, without a trailing slash or query.
     *
     * The plain list answers on the prefix itself; archives override this.
     *
     * @return string
     */
    protected function getListingUrl(): string
    {
        return $this->_urlBuilder->getUrl('', ['_direct' => $this->config->getUrlPrefix()]);
    }

    /**
     * The store's blog URL prefix, for the archive blocks' getListingUrl().
     *
     * @return string
     */
    protected function getUrlPrefix(): string
    {
        return $this->config->getUrlPrefix();
    }

    /**
     * Append query parameters; null values are dropped.
     *
     * @param string $url
     * @param array $query array<string, mixed>
     * @return string
     */
    private function withQuery(string $url, array $query): string
    {
        $query = array_filter($query, static fn ($value) => $value !== null);

        return $query === [] ? $url : $url . (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
    }

    /**
     * Add the canonical link to the page head.
     *
     * The author page nests one AuthorView in another; both add the same URL
     * and the page config keeps it once.
     *
     * @return $this
     */
    protected function _prepareLayout()
    {
        parent::_prepareLayout();

        $canonical = $this->getCanonicalUrl();
        if ($canonical !== '') {
            $this->pageConfig->addRemotePageAsset($canonical, 'canonical', ['attributes' => ['rel' => 'canonical']]);
        }

        return $this;
    }

    /**
     * Extra query parameters the pager has to carry.
     *
     * Such as the search results page's ?query=.
     *
     * @return array<string, string>
     */
    protected function getPagerQuery(): array
    {
        return [];
    }

    /**
     * URL to a post's detail page.
     *
     * @param PostInterface $post
     * @return string
     */
    public function getPostUrl(PostInterface $post): string
    {
        return PostUrl::resolve($post, $this->_urlBuilder, $this->config->getUrlPrefix());
    }

    /**
     * Resolve a stored featured-image path to a usable URL.
     *
     * @param string|null $path
     * @return string
     */
    public function getImageUrl(?string $path): string
    {
        return ImageUrl::resolve($path, $this->storeManager);
    }

    /**
     * A plain-text excerpt from post content.
     *
     * @param PostInterface $post
     * @param int $length
     * @return string
     */
    public function getExcerpt(PostInterface $post, int $length = 180): string
    {
        return $this->postContent->excerpt($post->getContent(), $length);
    }

    /**
     * Teaser for a listing card, as HTML ready to echo unescaped.
     *
     * The authored short description when there is one, otherwise the automatic
     * excerpt the cards showed before the field existed - so posts that predate
     * the field do not go blank while it is being filled in.
     *
     * The short description is passed through untouched rather than flattened to
     * plain text, and that is the point. Hiding an element in the editor is a
     * styling instruction, not a deletion: Page Builder leaves the element in the
     * markup and marks it. strip_tags() would drop the marking and keep the
     * words, so anything the author hid came back on the card. Emitting the real
     * markup means the browser applies the author's intent here exactly as it
     * does on the post itself - without this code needing to know how any
     * particular editor spells "hidden".
     *
     * The fallback is escaped here, because the caller echoes the result raw.
     *
     * @param PostInterface $post
     * @param int $length applies to the fallback excerpt only
     * @return string
     */
    public function getCardSummaryHtml(PostInterface $post, int $length = 220): string
    {
        $short = trim((string) $post->getShortDescription());

        if ($short !== '') {
            // render() resolves {{media url=...}} directives and unescapes a
            // Page Builder payload, the same treatment the post body gets.
            return $this->postContent->render($short);
        }

        return $this->_escaper->escapeHtml($this->postContent->excerpt($post->getContent(), $length));
    }

    /**
     * Cache tag for full page cache invalidation, see BlogCache.
     *
     * @return string[]
     */
    public function getIdentities(): array
    {
        return [BlogCache::TAG];
    }
}
