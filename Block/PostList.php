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
     * @param array $data
     */
    public function __construct(
        Context $context,
        private readonly PostRepositoryInterface $postRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly SortOrderBuilder $sortOrderBuilder,
        private readonly StoreManagerInterface $storeManager,
        private readonly \RequestDesk\Blog\Model\AuthorResolver $authorResolver,
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
            ->create();

        return $this->postRepository->getList($criteria)->getItems();
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
        $text = trim((string) preg_replace('/\s+/', ' ', strip_tags((string) $post->getContent())));
        if (mb_strlen($text) <= $length) {
            return $text;
        }
        return mb_substr($text, 0, $length) . '…';
    }
}
