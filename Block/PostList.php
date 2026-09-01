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
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\StoreManagerInterface;
use RequestDesk\Blog\Api\Data\PostInterface;
use RequestDesk\Blog\Api\Data\PostSearchResultsInterface;
use RequestDesk\Blog\Api\PostRepositoryInterface;
use RequestDesk\Blog\Model\Config;

/**
 * Supplies published posts to the list templates (Luma and Hyva both use this).
 */
class PostList extends Template
{
    /**
     * Query string parameters, same convention as catalog listings.
     */
    private const PAGE_VAR_NAME = 'p';
    private const LIMIT_VAR_NAME = 'limit';

    /**
     * Memoised so the items and the total count share one query.
     *
     * @var PostSearchResultsInterface|null
     */
    private ?PostSearchResultsInterface $postResults = null;

    /**
     * @param Context $context
     * @param PostRepositoryInterface $postRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param SortOrderBuilder $sortOrderBuilder
     * @param StoreManagerInterface $storeManager
     * @param \RequestDesk\Blog\Model\AuthorResolver $authorResolver
     * @param \RequestDesk\Blog\Model\PostContent $postContent
     * @param Config $config
     * @param array $data
     */
    public function __construct(
        Context $context,
        private readonly PostRepositoryInterface $postRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly SortOrderBuilder $sortOrderBuilder,
        private readonly StoreManagerInterface $storeManager,
        private readonly \RequestDesk\Blog\Model\AuthorResolver $authorResolver,
        private readonly \RequestDesk\Blog\Model\PostContent $postContent,
        private readonly Config $config,
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
        $author = $this->authorResolver->getAuthorForPost($post);
        return $author !== null ? $author['name'] : (string) $post->getAuthor();
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
     * Runs the paginated query once and keeps the result for the whole render.
     *
     * The template asks for the items and the page count separately; without
     * memoising, that is two identical queries per request.
     *
     * @return PostSearchResultsInterface
     */
    private function getPostResults(): PostSearchResultsInterface
    {
        if ($this->postResults === null) {
            $sort = $this->sortOrderBuilder
                ->setField(PostInterface::CREATED_AT)
                ->setDirection('DESC')
                ->create();

            $criteria = $this->searchCriteriaBuilder
                ->addFilter(PostInterface::STATUS, PostInterface::STATUS_PUBLISHED)
                ->addSortOrder($sort)
                ->setPageSize($this->getPostsPerPage())
                ->setCurrentPage($this->getCurrentPage())
                ->create();

            $this->postResults = $this->postRepository->getList($criteria);
        }

        return $this->postResults;
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
     * Whether there is more than one page worth of posts.
     *
     * Must exist as a real method: DataObject::__call() answers any undefined
     * has*() with a lookup in $_data, so a missing one here would quietly
     * return false and the template would simply never draw the pager.
     *
     * @return bool
     */
    public function hasPagination(): bool
    {
        return $this->getLastPageNumber() > 1;
    }

    /**
     * URL for a given page. Page 1 drops the parameter to keep /blog canonical.
     *
     * Built from the "blog" route rather than the current-action wildcard, which
     * resolves to blog/index/index and emits /blog/index/index/?p=2 - a second
     * address for a page that already answers on /blog.
     *
     * An active ?limit= has to be carried over explicitly or page 2 would
     * revert to the configured size.
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

        return $this->getUrl('blog', ['_query' => $query]);
    }

    /**
     * URL to a post's detail page.
     *
     * @param PostInterface $post
     * @return string
     */
    public function getPostUrl(PostInterface $post): string
    {
        return PostUrl::resolve($post, $this->_urlBuilder);
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
}
