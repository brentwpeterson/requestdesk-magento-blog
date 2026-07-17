<?php
/**
 * Copyright (c) 2025 Content Basis LLC
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available at https://opensource.org/licenses/OSL-3.0
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 * @author    Content Basis LLC
 * @copyright Copyright (c) 2025 Content Basis LLC
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License 3.0
 */
declare(strict_types=1);

namespace RequestDesk\Blog\Controller\Adminhtml\Post;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use RequestDesk\Blog\Api\PostRepositoryInterface;
use RequestDesk\Blog\Model\PostCategoryResolver;
use RequestDesk\Blog\Model\PostFactory;
use RequestDesk\Blog\Model\TagResolver;
use RequestDesk\Qa\Model\QaLinkResolver;
use Magento\Framework\Exception\LocalizedException;

class Save extends Action
{
    /**
     * Authorization level
     */
    const ADMIN_RESOURCE = 'RequestDesk_Blog::manage';

    /**
     * @var PostRepositoryInterface
     */
    protected PostRepositoryInterface $postRepository;

    /**
     * @var PostFactory
     */
    protected PostFactory $postFactory;

    /**
     * @param Context $context
     * @param PostRepositoryInterface $postRepository
     * @param PostFactory $postFactory
     */
    /**
     * @param Context $context
     * @param PostRepositoryInterface $postRepository
     * @param PostFactory $postFactory
     * @param PostCategoryResolver $categoryResolver
     * @param TagResolver $tagResolver
     * @param QaLinkResolver $qaLinkResolver
     */
    public function __construct(
        Context $context,
        PostRepositoryInterface $postRepository,
        PostFactory $postFactory,
        private readonly PostCategoryResolver $categoryResolver,
        private readonly TagResolver $tagResolver,
        private readonly QaLinkResolver $qaLinkResolver
    ) {
        parent::__construct($context);
        $this->postRepository = $postRepository;
        $this->postFactory = $postFactory;
    }

    /**
     * Save blog post
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $data = $this->getRequest()->getPostValue();

        if (!$data) {
            return $resultRedirect->setPath('*/*/');
        }

        $postId = isset($data['post_id']) ? (int)$data['post_id'] : null;

        try {
            if ($postId) {
                $post = $this->postRepository->getById($postId);
            } else {
                $post = $this->postFactory->create();
            }

            $post->setTitle($data['title'] ?? '');
            $post->setContent($data['content'] ?? '');
            $post->setUrlKey($data['url_key'] ?? '');
            $post->setMetaTitle($data['meta_title'] ?? '');
            $post->setMetaDescription($data['meta_description'] ?? '');
            $post->setAuthor($data['author'] ?? '');
            $post->setAuthorId(!empty($data['author_id']) ? (int)$data['author_id'] : null);
            $post->setIsActive(isset($data['is_active']) ? (int)$data['is_active'] : 0);

            $this->postRepository->save($post);

            $savedId = (int)$post->getId();
            $this->categoryResolver->syncForPost($savedId, (array)($data['category_ids'] ?? []));
            $this->tagResolver->syncForPost($savedId, (array)($data['tag_ids'] ?? []));
            $this->qaLinkResolver->syncForEntity(
                QaLinkResolver::ENTITY_BLOG_POST,
                $savedId,
                (array)($data['qa_ids'] ?? [])
            );

            $this->messageManager->addSuccessMessage(__('The post has been saved.'));

            if ($this->getRequest()->getParam('back')) {
                return $resultRedirect->setPath('*/*/edit', ['post_id' => $post->getId()]);
            }

            return $resultRedirect->setPath('*/*/');
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('An error occurred while saving the post.'));
        }

        return $resultRedirect->setPath('*/*/edit', ['post_id' => $postId]);
    }
}
