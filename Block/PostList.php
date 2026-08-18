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
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\StoreManagerInterface;
use RequestDesk\Blog\Api\Data\PostInterface;
use RequestDesk\Blog\Api\PostRepositoryInterface;

/**
 * Supplies published posts to the list templates (Luma and Hyva both use this).
 */
class PostList extends Template
{
    /**
     * @param Context $context
     * @param PostRepositoryInterface $postRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param SortOrderBuilder $sortOrderBuilder
     * @param StoreManagerInterface $storeManager
     * @param \RequestDesk\Blog\Model\AuthorResolver $authorResolver
     * @param \RequestDesk\Blog\Model\PostContent $postContent
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
     * Published posts, newest first.
     *
     * @return PostInterface[]
     */
    public function getPosts(): array
    {
        $sort = $this->sortOrderBuilder
            ->setField(PostInterface::CREATED_AT)
            ->setDirection('DESC')
            ->create();

        $criteria = $this->searchCriteriaBuilder
            ->addFilter(PostInterface::STATUS, PostInterface::STATUS_PUBLISHED)
            ->addSortOrder($sort)
            ->setPageSize($this->getPostsPerPage())
            ->setCurrentPage(1)
            ->create();

        return $this->postRepository->getList($criteria)->getItems();
    }

    /**
     * Posts per page, from requestdesk_blog/general/posts_per_page.
     *
     * The admin field and its config.xml default of 10 both existed already, but
     * nothing on the frontend ever read them, so the listing returned every
     * published post regardless of what the setting said. A non-positive or
     * missing value falls back to 10 rather than to "unlimited", because a
     * blank field should not silently turn into a full table scan.
     *
     * @return int
     */
    public function getPostsPerPage(): int
    {
        $configured = (int) $this->_scopeConfig->getValue(
            'requestdesk_blog/general/posts_per_page',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        return $configured > 0 ? $configured : 10;
    }

    /**
     * URL to a post's detail page.
     *
     * @param PostInterface $post
     * @return string
     */
    public function getPostUrl(PostInterface $post): string
    {
        return $this->getUrl('blog/post/view', ['id' => $post->getPostId()]);
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
}
