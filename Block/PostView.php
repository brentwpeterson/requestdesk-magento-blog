<?php
/**
 * RequestDesk Blog - Post View Block (frontend, theme-agnostic)
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\StoreManagerInterface;
use RequestDesk\Blog\Api\Data\PostInterface;
use RequestDesk\Blog\Api\PostRepositoryInterface;
use Magento\Framework\Data\Form\FormKey;
use RequestDesk\Blog\Model\AuthorResolver;
use RequestDesk\Blog\Model\Comment;
use RequestDesk\Blog\Model\CommentManager;
use RequestDesk\Blog\Model\PostCategoryResolver;
use RequestDesk\Blog\Model\TagResolver;

/**
 * Supplies the single published post to the detail templates (both themes).
 */
class PostView extends Template
{
    /**
     * @var PostInterface|null|false false = looked up and not found
     */
    private $post = null;

    /**
     * @param Context $context
     * @param PostRepositoryInterface $postRepository
     * @param StoreManagerInterface $storeManager
     * @param PostCategoryResolver $categoryResolver
     * @param array $data
     */
    public function __construct(
        Context $context,
        private readonly PostRepositoryInterface $postRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly PostCategoryResolver $categoryResolver,
        private readonly AuthorResolver $authorResolver,
        private readonly TagResolver $tagResolver,
        private readonly CommentManager $commentManager,
        private readonly FormKey $formKey,
        private readonly \RequestDesk\Blog\Model\PostContent $postContent,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * The post body, unescaped and with directives resolved, ready to echo.
     *
     * @return string
     */
    public function getRenderedContent(): string
    {
        $post = $this->getPost();
        return $post !== null ? $this->postContent->render($post->getContent()) : '';
    }

    /**
     * Approved comments on the current post.
     *
     * @return Comment[]
     */
    public function getComments(): array
    {
        $post = $this->getPost();
        return $post ? $this->commentManager->getApprovedForPost((int) $post->getPostId()) : [];
    }

    /**
     * URL the comment form submits to.
     *
     * @return string
     */
    public function getCommentActionUrl(): string
    {
        return $this->getUrl('blog/comment/save');
    }

    /**
     * The session form key (CSRF).
     *
     * @return string
     */
    public function getFormKey(): string
    {
        return $this->formKey->getFormKey();
    }

    /**
     * Tags on the current post, as [id, name, url].
     *
     * @return array<int, array{id:int, name:string, url:string}>
     */
    public function getTags(): array
    {
        $post = $this->getPost();
        return $post ? $this->tagResolver->getTagsForPost((int) $post->getPostId()) : [];
    }

    /**
     * Resolved author (native admin user + public profile), or null.
     *
     * @return array{id:?int, name:string, bio:string, avatar:string, page_url:string, link:string}|null
     */
    public function getAuthorData(): ?array
    {
        $post = $this->getPost();
        return $post ? $this->authorResolver->getAuthorForPost($post) : null;
    }

    /**
     * Native Magento categories assigned to the current post.
     *
     * @return array<int, array{id:int, name:string, url:string}>
     */
    public function getCategories(): array
    {
        $post = $this->getPost();
        return $post ? $this->categoryResolver->getCategoriesForPost((int) $post->getPostId()) : [];
    }

    /**
     * The requested published post, or null.
     *
     * @return PostInterface|null
     */
    public function getPost(): ?PostInterface
    {
        if ($this->post === null) {
            $this->post = false;
            $id = (int) $this->getRequest()->getParam('id');
            if ($id > 0) {
                try {
                    $post = $this->postRepository->getById($id);
                    if ((int) $post->getStatus() === PostInterface::STATUS_PUBLISHED) {
                        $this->post = $post;
                    }
                } catch (\Throwable $e) {
                    $this->post = false;
                }
            }
        }
        return $this->post ?: null;
    }

    /**
     * Featured image URL for the current post.
     *
     * @return string
     */
    public function getFeaturedImageUrl(): string
    {
        $post = $this->getPost();
        return $post ? ImageUrl::resolve($post->getFeaturedImage(), $this->storeManager) : '';
    }

    /**
     * URL back to the blog index.
     *
     * @return string
     */
    public function getBackUrl(): string
    {
        return $this->getUrl('blog');
    }
}
