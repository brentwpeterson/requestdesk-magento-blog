<?php
/**
 * RequestDesk Blog - Posts Widget (native Magento widget)
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Block\Widget;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Widget\Block\BlockInterface;
use RequestDesk\Blog\Api\Data\PostInterface;
use RequestDesk\Blog\Api\PostRepositoryInterface;
use RequestDesk\Blog\Block\ImageUrl;
use RequestDesk\Blog\Block\PostUrl;
use RequestDesk\Blog\Model\PostCategoryResolver;
use RequestDesk\Blog\Model\Config;

/**
 * A native Magento widget that surfaces blog posts anywhere widgets are allowed
 * (CMS pages, blocks, layout, the PDP). Two modes:
 *  - recent:  newest published posts
 *  - category: posts in a chosen blog category
 *
 * A third mode, related, showed posts sharing the current product's catalog
 * categories. It went when posts moved to blog categories (1.13.0): there is no
 * longer anything tying a post to a product's categories. A widget instance
 * still saved with mode=related renders nothing rather than unrelated posts.
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
        private readonly PostCategoryResolver $categoryResolver,
        private readonly \RequestDesk\Blog\Model\PostContent $postContent,
        private readonly Config $config,
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
                return [];
            default:
                return $this->recentPosts($count);
        }
    }

    /**
     * Get title
     *
     * @return string
     */
    public function getTitle(): string
    {
        return (string) $this->getData('title');
    }

    /**
     * Recent posts
     *
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
     * Posts in categories
     *
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
     * Render nothing while the blog is switched off.
     *
     * So a widget placed on a CMS page or a product page does not keep linking
     * to posts that now 404.
     *
     * @return string
     */
    protected function _toHtml()
    {
        if (!$this->config->isBlogEnabled()) {
            return '';
        }

        return parent::_toHtml();
    }

    /**
     * Get post url
     *
     * @param PostInterface $post
     * @return string
     */
    public function getPostUrl(PostInterface $post): string
    {
        return PostUrl::resolve($post, $this->_urlBuilder, $this->config->getUrlPrefix());
    }

    /**
     * Get image url
     *
     * @param PostInterface $post
     * @return string
     */
    public function getImageUrl(PostInterface $post): string
    {
        return ImageUrl::resolve($post->getFeaturedImage(), $this->storeManager);
    }

    /**
     * Get excerpt
     *
     * @param PostInterface $post
     * @param int $length
     * @return string
     */
    public function getExcerpt(PostInterface $post, int $length = 120): string
    {
        return $this->postContent->excerpt($post->getContent(), $length);
    }
}
