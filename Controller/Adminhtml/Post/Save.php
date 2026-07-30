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
use Magento\Framework\App\ResourceConnection;
use RequestDesk\Blog\Api\PostRepositoryInterface;
use RequestDesk\Blog\Api\QaLinkResolverInterface;
use RequestDesk\Blog\Model\PostCategoryResolver;
use RequestDesk\Blog\Model\PostFactory;
use RequestDesk\Blog\Model\TagResolver;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;

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
     * @param PostCategoryResolver $categoryResolver
     * @param TagResolver $tagResolver
     * @param QaLinkResolverInterface $qaLinkResolver
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        PostRepositoryInterface $postRepository,
        PostFactory $postFactory,
        private readonly PostCategoryResolver $categoryResolver,
        private readonly TagResolver $tagResolver,
        private readonly QaLinkResolverInterface $qaLinkResolver,
        private readonly LoggerInterface $logger,
        private readonly ResourceConnection $resource
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
            // The byline is now the author_id select. "author" is the legacy
            // free-text column, no longer on the form — only write it when it was
            // actually posted, or every save would erase the imported name that
            // still backs posts with no author record.
            if (array_key_exists('author', $data)) {
                $post->setAuthor((string)$data['author']);
            }
            $post->setAuthorId(!empty($data['author_id']) ? (int)$data['author_id'] : null);
            $post->setIsActive(isset($data['is_active']) ? (int)$data['is_active'] : 0);

            $this->postRepository->save($post);

            $savedId = (int)$post->getId();
            $failed = $this->syncAssociations($savedId, $data);
            $this->touch($savedId);

            if ($failed === []) {
                $this->messageManager->addSuccessMessage(__('The post has been saved.'));
            } else {
                // The post itself saved. Say so, and name what did not, instead of
                // reporting a bare failure on a save that partly succeeded.
                $this->messageManager->addSuccessMessage(__('The post has been saved.'));
                $this->messageManager->addErrorMessage(
                    __('The post saved, but these could not be updated: %1.', implode(', ', $failed))
                );
            }

            if ($this->getRequest()->getParam('back')) {
                return $resultRedirect->setPath('*/*/edit', ['post_id' => $post->getId()]);
            }

            return $resultRedirect->setPath('*/*/');
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->logger->error('RequestDesk Blog: failed to save post', ['exception' => $e]);
            $this->messageManager->addErrorMessage(__('An error occurred while saving the post.'));
        }

        return $resultRedirect->setPath('*/*/edit', ['post_id' => $postId]);
    }

    /**
     * Stamp updated_at so the grid reflects the edit.
     *
     * updated_at is ON UPDATE CURRENT_TIMESTAMP, which MySQL only fires when a
     * column on the post row actually changes. Categories, tags and Q&A pairs all
     * live in their own pivot tables, so editing only those left the post row
     * untouched and the grid's Updated column stale — the post visibly changed but
     * claimed it had not. Stamping it here keeps the column honest.
     *
     * Best-effort: never let a timestamp cost the caller a save that succeeded.
     *
     * @param int $postId
     * @return void
     */
    private function touch(int $postId): void
    {
        if ($postId <= 0) {
            return;
        }

        try {
            $connection = $this->resource->getConnection();
            $connection->update(
                $this->resource->getTableName('requestdesk_blog_post'),
                ['updated_at' => $connection->fetchOne('SELECT NOW()')],
                ['post_id = ?' => $postId]
            );
        } catch (\Throwable $e) {
            $this->logger->error(
                'RequestDesk Blog: could not stamp updated_at for post ' . $postId,
                ['exception' => $e]
            );
        }
    }

    /**
     * Sync categories, tags and Q&A pairs onto a saved post.
     *
     * Each association is isolated: a failure in one used to abort the rest, so
     * selecting a category silently discarded the tags and Q&A pairs chosen in the
     * same save. The real exception is logged and the caller gets the labels of
     * whatever failed.
     *
     * @param int $postId
     * @param array $data
     * @return string[] Labels of the associations that could not be saved
     */
    private function syncAssociations(int $postId, array $data): array
    {
        $syncs = [
            'Categories' => fn () => $this->categoryResolver->syncForPost(
                $postId,
                (array)($data['category_ids'] ?? [])
            ),
            'Tags' => fn () => $this->tagResolver->syncForPost(
                $postId,
                (array)($data['tag_ids'] ?? [])
            ),
            'Q&A pairs' => fn () => $this->qaLinkResolver->syncForEntity(
                QaLinkResolverInterface::ENTITY_BLOG_POST,
                $postId,
                (array)($data['qa_ids'] ?? [])
            ),
        ];

        $failed = [];
        foreach ($syncs as $label => $sync) {
            try {
                $sync();
            } catch (\Throwable $e) {
                $failed[] = $label;
                $this->logger->error(
                    'RequestDesk Blog: failed to sync ' . $label . ' for post ' . $postId,
                    ['exception' => $e]
                );
            }
        }

        return $failed;
    }
}
