<?php
/**
 * RequestDesk Blog - Tag Archive Block
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
use RequestDesk\Blog\Model\TagResolver;

/**
 * Supplies the tag and its published posts to the tag archive page.
 */
class TagView extends Template
{
    /**
     * @var array|null|false
     */
    private $tag = null;

    /**
     * @param Context $context
     * @param TagResolver $tagResolver
     * @param PostRepositoryInterface $postRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param SortOrderBuilder $sortOrderBuilder
     * @param array $data
     */
    public function __construct(
        Context $context,
        private readonly TagResolver $tagResolver,
        private readonly PostRepositoryInterface $postRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly SortOrderBuilder $sortOrderBuilder,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return array{id:int, name:string, url:string}|null
     */
    public function getTag(): ?array
    {
        if ($this->tag === null) {
            $id = (int) $this->getRequest()->getParam('id');
            $this->tag = $id > 0 ? ($this->tagResolver->getTag($id) ?: false) : false;
        }
        return $this->tag ?: null;
    }

    /**
     * @return PostInterface[]
     */
    public function getPosts(): array
    {
        $tag = $this->getTag();
        if ($tag === null) {
            return [];
        }
        $postIds = $this->tagResolver->getPostIdsByTag((int) $tag['id']);
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
