<?php
/**
 * RequestDesk Blog - Posts Widget (native Magento widget)
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Block\Widget;

use Magento\Catalog\Model\Product;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Widget\Block\BlockInterface;
use RequestDesk\Blog\Api\Data\PostInterface;
use RequestDesk\Blog\Api\PostRepositoryInterface;
use RequestDesk\Blog\Block\ImageUrl;
use RequestDesk\Blog\Model\PostCategoryResolver;

/**
 * A native Magento widget that surfaces blog posts anywhere widgets are allowed
 * (CMS pages, blocks, layout, the PDP). Three modes:
 *  - recent:  newest published posts
 *  - category: posts in a chosen native category
 *  - related: posts sharing the current product's categories (the AEO cross-link)
 */
class PostsWidget extends Template implements BlockInterface
{
    /**
     * @var string
     */
    protected $_template = 'RequestDesk_Blog::widget/posts.phtml';

    /**
     * @param Context $context
     * @param PostRepositoryInterface $postRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param SortOrderBuilder $sortOrderBuilder
     * @param StoreManagerInterface $storeManager
     * @param PostCategoryResolver $categoryResolver
     * @param Registry $registry
     * @param \RequestDesk\Blog\Model\PostContent $postContent
     * @param array $data
     */
    public function __construct(
        Context $context,
        private readonly PostRepositoryInterface $postRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly SortOrderBuilder $sortOrderBuilder,
        private readonly StoreManagerInterface $storeManager,
        private readonly PostCategoryResolver $categoryResolver,
        private readonly Registry $registry,
        private readonly \RequestDesk\Blog\Model\PostContent $postContent,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * The posts to display, per the widget's configured mode.
     *
     * @return PostInterface[]
     */
    public function getPosts(): array
    {
        $count = max(1, (int) ($this->getData('posts_count') ?: 3));
        switch ((string) $this->getData('mode')) {
            case 'category':
                return $this->postsInCategories([(int) $this->getData('category_id')], $count);
            case 'related':
                return $this->relatedPosts($count);
            default:
                return $this->recentPosts($count);
        }
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return (string) $this->getData('title');
    }

    /**
     * @param int $count
     * @return PostInterface[]
     */
    private function recentPosts(int $count): array
    {
        $sort = $this->sortOrderBuilder
            ->setField(PostInterface::CREATED_AT)->setDirection('DESC')->create();
        $criteria = $this->searchCriteriaBuilder
            ->addFilter(PostInterface::STATUS, PostInterface::STATUS_PUBLISHED)
            ->addSortOrder($sort)
            ->setPageSize($count)
            ->create();
        return $this->postRepository->getList($criteria)->getItems();
    }

    /**
     * @param int[] $categoryIds
     * @param int $count
     * @return PostInterface[]
     */
    private function postsInCategories(array $categoryIds, int $count): array
    {
        $postIds = [];
        foreach (array_filter($categoryIds) as $categoryId) {
            foreach ($this->categoryResolver->getPostIdsInCategory((int) $categoryId) as $postId) {
                $postIds[$postId] = $postId;
            }
        }
        if ($postIds === []) {
            return [];
        }

        $sort = $this->sortOrderBuilder
            ->setField(PostInterface::CREATED_AT)->setDirection('DESC')->create();
        $criteria = $this->searchCriteriaBuilder
            ->addFilter(PostInterface::STATUS, PostInterface::STATUS_PUBLISHED)
            ->addFilter(PostInterface::POST_ID, array_values($postIds), 'in')
            ->addSortOrder($sort)
            ->setPageSize($count)
            ->create();
        return $this->postRepository->getList($criteria)->getItems();
    }

    /**
     * Posts sharing the current product's categories.
     *
     * @param int $count
     * @return PostInterface[]
     */
    private function relatedPosts(int $count): array
    {
        $product = $this->registry->registry('current_product');
        if (!$product instanceof Product) {
            return [];
        }
        $categoryIds = array_map('intval', (array) $product->getCategoryIds());
        return $categoryIds === [] ? [] : $this->postsInCategories($categoryIds, $count);
    }

    /**
     * @param PostInterface $post
     * @return string
     */
    public function getPostUrl(PostInterface $post): string
    {
        return $this->getUrl('blog/post/view', ['id' => $post->getPostId()]);
    }

    /**
     * @param PostInterface $post
     * @return string
     */
    public function getImageUrl(PostInterface $post): string
    {
        return ImageUrl::resolve($post->getFeaturedImage(), $this->storeManager);
    }

    /**
     * @param PostInterface $post
     * @param int $length
     * @return string
     */
    public function getExcerpt(PostInterface $post, int $length = 120): string
    {
        return $this->postContent->excerpt($post->getContent(), $length);
    }
}
