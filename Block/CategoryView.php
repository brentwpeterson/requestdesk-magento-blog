<?php
/**
 * RequestDesk Blog - Category Filter Block
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Block;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use RequestDesk\Blog\Api\Data\PostInterface;
use RequestDesk\Blog\Api\PostRepositoryInterface;
use RequestDesk\Blog\Model\PostCategoryResolver;

/**
 * Supplies a native category name + the blog posts in it.
 */
class CategoryView extends Template
{
    /**
     * @param Context $context
     * @param CategoryRepositoryInterface $categoryRepository
     * @param PostCategoryResolver $categoryResolver
     * @param PostRepositoryInterface $postRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param SortOrderBuilder $sortOrderBuilder
     * @param array $data
     */
    public function __construct(
        Context $context,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly PostCategoryResolver $categoryResolver,
        private readonly PostRepositoryInterface $postRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly SortOrderBuilder $sortOrderBuilder,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return string
     */
    public function getCategoryName(): string
    {
        try {
            return (string) $this->categoryRepository->get($this->getCategoryId())->getName();
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * @return int
     */
    public function getCategoryId(): int
    {
        return (int) $this->getRequest()->getParam('id');
    }

    /**
     * @return PostInterface[]
     */
    public function getPosts(): array
    {
        $postIds = $this->categoryResolver->getPostIdsInCategory($this->getCategoryId());
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
     * @param PostInterface $post
     * @return string
     */
    public function getPostUrl(PostInterface $post): string
    {
        return $this->getUrl('blog/post/view', ['id' => $post->getPostId()]);
    }
}
