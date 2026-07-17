<?php
/**
 * RequestDesk Blog - Author View Block
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
use RequestDesk\Blog\Api\Data\PostInterface;
use RequestDesk\Blog\Api\PostRepositoryInterface;
use RequestDesk\Blog\Model\AuthorResolver;

/**
 * Supplies the author profile + their published posts to the author page.
 */
class AuthorView extends Template
{
    /**
     * @var array|null|false
     */
    private $author = null;

    /**
     * @param Context $context
     * @param AuthorResolver $authorResolver
     * @param PostRepositoryInterface $postRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param SortOrderBuilder $sortOrderBuilder
     * @param array $data
     */
    public function __construct(
        Context $context,
        private readonly AuthorResolver $authorResolver,
        private readonly PostRepositoryInterface $postRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly SortOrderBuilder $sortOrderBuilder,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

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
     * The author's published posts.
     *
     * @return PostInterface[]
     */
    public function getPosts(): array
    {
        $author = $this->getAuthor();
        if ($author === null) {
            return [];
        }
        $postIds = $this->authorResolver->getPostIdsByAuthor((int) $author['id']);
        if ($postIds === []) {
            return [];
        }

        $sort = $this->sortOrderBuilder
            ->setField(PostInterface::CREATED_AT)->setDirection('DESC')->create();
        $criteria = $this->searchCriteriaBuilder
            ->addFilter(PostInterface::POST_ID, $postIds, 'in')
            ->addFilter(PostInterface::STATUS, PostInterface::STATUS_PUBLISHED)
            ->addSortOrder($sort)
            ->create();
        return $this->postRepository->getList($criteria)->getItems();
    }

    /**
     * URL to a post.
     *
     * @param PostInterface $post
     * @return string
     */
    public function getPostUrl(PostInterface $post): string
    {
        return $this->getUrl('blog/post/view', ['id' => $post->getPostId()]);
    }
}
