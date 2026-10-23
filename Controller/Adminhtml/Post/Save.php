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
use Magento\Catalog\Model\ImageUploader;
use Magento\Framework\App\ResourceConnection;
use RequestDesk\Blog\Api\Data\PostInterface;
use RequestDesk\Blog\Api\PostRepositoryInterface;
use RequestDesk\Blog\Api\QaLinkResolverInterface;
use RequestDesk\Blog\Model\PostCategoryResolver;
use RequestDesk\Blog\Model\PostFactory;
use RequestDesk\Blog\Model\TagResolver;
use RequestDesk\Blog\Model\UploaderImagePath;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;

class Save extends Action
{
    /**
     * Authorization level
     */
    public const ADMIN_RESOURCE = 'RequestDesk_Blog::manage';

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
     * @param ResourceConnection $resource
     * @param ImageUploader $imageUploader
     * @param UploaderImagePath $uploaderImagePath
     */
    public function __construct(
        Context $context,
        PostRepositoryInterface $postRepository,
        PostFactory $postFactory,
        private readonly PostCategoryResolver $categoryResolver,
        private readonly TagResolver $tagResolver,
        private readonly QaLinkResolverInterface $qaLinkResolver,
        private readonly LoggerInterface $logger,
        private readonly ResourceConnection $resource,
        private readonly ImageUploader $imageUploader,
        private readonly UploaderImagePath $uploaderImagePath
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
            // Blank the column rather than storing '', so "never written" and
            // "cleared by hand" stay distinguishable for the migration backfill.
            $shortDescription = trim((string) ($data['short_description'] ?? ''));
            $post->setShortDescription($shortDescription !== '' ? $shortDescription : null);
            $post->setUrlKey($this->resolveUrlKey($data, $postId));
            $post->setFeaturedImage($this->resolveFeaturedImage($data, (string)$post->getFeaturedImage()));
            $imageAlt = trim((string)($data['featured_image_alt'] ?? ''));
            $post->setFeaturedImageAlt($imageAlt !== '' ? $imageAlt : null);
            $post->setMetaTitle($data['meta_title'] ?? '');
            $post->setMetaDescription($data['meta_description'] ?? '');
            $metaRobots = trim((string) ($data['meta_robots'] ?? ''));
            $post->setMetaRobots($metaRobots !== '' ? $metaRobots : null);
            // The byline is now the author_id select. "author" is the legacy
            // free-text column, no longer on the form — only write it when it was
            // actually posted, or every save would erase the imported name that
            // still backs posts with no author record.
            if (array_key_exists('author', $data)) {
                $post->setAuthor((string)$data['author']);
            }
            $post->setAuthorId(!empty($data['author_id']) ? (int)$data['author_id'] : null);
            if (array_key_exists('store_id', $data)) {
                $post->setStoreId((int)$data['store_id']);
            }
            $this->applyPublishStatus($post, $data);
            $post->setCommentsEnabled(isset($data['comments_enabled']) ? (bool)$data['comments_enabled'] : false);

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
     * Set status and published date (created_at) from the Publish Status fields.
     *
     * Scheduled needs a date; one already past publishes the post now, since the
     * cron would only do the same a minute later. A Published post dated in the
     * future is brought back to now, as Amasty does, so the blog never shows a
     * date that has not happened. An empty date means now for a new post and
     * "unchanged" for an existing one.
     *
     * @param PostInterface $post
     * @param array $data
     * @return void
     * @throws LocalizedException
     */
    private function applyPublishStatus(PostInterface $post, array $data): void
    {
        $status = (int)($data['status'] ?? PostInterface::STATUS_PUBLISHED);
        $allowed = [
            PostInterface::STATUS_DRAFT,
            PostInterface::STATUS_PUBLISHED,
            PostInterface::STATUS_HIDDEN,
            PostInterface::STATUS_SCHEDULED,
        ];
        if (!in_array($status, $allowed, true)) {
            throw new LocalizedException(__('Please choose a valid status.'));
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $date = $this->parsePublishedAt((string)($data['published_at'] ?? ''));

        if ($status === PostInterface::STATUS_SCHEDULED) {
            if ($date === null) {
                throw new LocalizedException(__('Please set a Published Date for a scheduled post.'));
            }
            if ($date <= $now) {
                $status = PostInterface::STATUS_PUBLISHED;
                $this->messageManager->addNoticeMessage(
                    __('The scheduled date has already passed, so the post was published now.')
                );
            }
        } elseif ($status === PostInterface::STATUS_PUBLISHED && $date !== null && $date > $now) {
            $date = $now;
        }

        if ($date === null && !$post->getId()) {
            $date = $now;
        }

        $post->setStatus($status);
        if ($date !== null) {
            $post->setCreatedAt($date->format('Y-m-d H:i:s'));
        }
    }

    /**
     * The posted Published Date as a UTC time, or null when empty or unreadable.
     *
     * The date field with showsTime posts UTC already (an ISO string), having
     * converted from the store time zone in the browser.
     *
     * @param string $value
     * @return \DateTimeImmutable|null
     */
    private function parsePublishedAt(string $value): ?\DateTimeImmutable
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        try {
            return (new \DateTimeImmutable($value, new \DateTimeZone('UTC')))
                ->setTimezone(new \DateTimeZone('UTC'));
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Resolve the featured image path to store.
     *
     * The image uploader posts a list of files, and posts nothing at all once the
     * image is removed. A new upload carries tmp_name and gets moved into place.
     * Anything else is either the image the form opened with or a "Select from
     * Gallery" pick, which UploaderImagePath tells apart by url; the stored path
     * of a kept image (blog/uploads/... for migrated posts) is left as it is.
     *
     * The move is isolated like the author avatar's: a failed image must not cost
     * the editor the rest of the post.
     *
     * @param array $data
     * @param string $current Path already stored on the post, if any
     * @return string|null
     */
    private function resolveFeaturedImage(array $data, string $current): ?string
    {
        $image = $data['featured_image'] ?? null;

        if (is_string($image)) {
            return trim($image) !== '' ? trim($image) : null;
        }

        $file = is_array($image) ? reset($image) : null;
        if (!is_array($file) || empty($file['name'])) {
            return null;
        }

        if (empty($file['tmp_name'])) {
            $path = $this->uploaderImagePath->resolve($file, $current);
            return $path !== '' ? $path : null;
        }

        try {
            return $this->imageUploader->moveFileFromTmp((string)$file['name'], true);
        } catch (\Throwable $e) {
            $this->logger->error(
                'RequestDesk Blog: featured image move failed; saving the post without the new image',
                ['exception' => $e, 'image' => $file['name']]
            );
            $this->messageManager->addWarningMessage(
                __('The post was saved, but the featured image could not be uploaded.')
            );
            return $current !== '' ? $current : null;
        }
    }

    /**
     * The URL key to store, derived from the title when the field is left blank.
     *
     * Imported posts always arrive with a url_key, so nothing generated one and the
     * gap went unnoticed: a post created by hand in the admin was saved with an
     * empty url_key. That leaves a blank column in the grid and, now that
     * /blog/<url-key> routes to posts, no pretty URL at all - the post is only
     * reachable by id. The author form has generated its key from the name for a
     * while; this brings posts in line.
     *
     * @param array $data
     * @param int $postId Zero for a new post
     * @return string
     */
    private function resolveUrlKey(array $data, int $postId): string
    {
        $urlKey = trim((string)($data['url_key'] ?? ''));

        if ($urlKey === '') {
            $urlKey = trim((string)($data['title'] ?? ''));
        }

        $slug = strtolower($urlKey);
        $slug = (string)preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');

        if ($slug === '') {
            return '';
        }

        return $this->uniqueUrlKey($slug, $postId);
    }

    /**
     * Suffix -2, -3, ... until the key is free.
     *
     * The url_key drives routing, so two posts sharing one would make the
     * second unreachable.
     *
     * @param string $base
     * @param int $postId
     * @return string
     */
    private function uniqueUrlKey(string $base, int $postId): string
    {
        try {
            $connection = $this->resource->getConnection();
            $table = $this->resource->getTableName('requestdesk_blog_post');

            $candidate = $base;
            $i = 2;
            while (true) {
                $select = $connection->select()
                    ->from($table, ['post_id'])
                    ->where('url_key = ?', $candidate)
                    ->limit(1);

                if ($postId) {
                    $select->where('post_id != ?', $postId);
                }

                if (!(int)$connection->fetchOne($select)) {
                    return $candidate;
                }

                $candidate = $base . '-' . ($i++);
            }
        } catch (\Throwable $e) {
            $this->logger->error(
                'RequestDesk Blog: could not check url_key uniqueness, using the raw slug',
                ['exception' => $e]
            );
            return $base;
        }
    }

    /**
     * Stamp updated_at so the grid reflects the edit.
     *
     * The updated_at column is ON UPDATE CURRENT_TIMESTAMP, which MySQL only fires when a
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
