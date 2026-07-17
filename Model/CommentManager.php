<?php
/**
 * RequestDesk Blog - Comment Manager
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Model;

use Magento\Framework\App\ResourceConnection;
use RequestDesk\Blog\Model\ResourceModel\Comment as CommentResource;
use RequestDesk\Blog\Model\ResourceModel\Comment\CollectionFactory;

/**
 * Creates, reads, and moderates blog comments. New comments always start
 * pending; only approved comments are ever shown on the storefront.
 */
class CommentManager
{
    /**
     * @param CommentFactory $commentFactory
     * @param CommentResource $commentResource
     * @param CollectionFactory $collectionFactory
     * @param ResourceConnection $resource
     */
    public function __construct(
        private readonly CommentFactory $commentFactory,
        private readonly CommentResource $commentResource,
        private readonly CollectionFactory $collectionFactory,
        private readonly ResourceConnection $resource
    ) {
    }

    /**
     * Create a pending comment.
     *
     * @param int $postId
     * @param string $name
     * @param string|null $email
     * @param string $content
     * @return Comment
     */
    public function submit(int $postId, string $name, ?string $email, string $content): Comment
    {
        /** @var Comment $comment */
        $comment = $this->commentFactory->create();
        $comment->setData([
            'post_id' => $postId,
            'author_name' => $name,
            'author_email' => $email,
            'content' => $content,
            'status' => Comment::STATUS_PENDING,
        ]);
        $this->commentResource->save($comment);
        return $comment;
    }

    /**
     * Approved comments for a post, oldest first.
     *
     * @param int $postId
     * @return Comment[]
     */
    public function getApprovedForPost(int $postId): array
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('post_id', $postId)
            ->addFieldToFilter('status', Comment::STATUS_APPROVED)
            ->setOrder('created_at', 'ASC');
        return array_values($collection->getItems());
    }

    /**
     * Approved comment count for a post.
     *
     * @param int $postId
     * @return int
     */
    public function getApprovedCountForPost(int $postId): int
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('post_id', $postId)
            ->addFieldToFilter('status', Comment::STATUS_APPROVED);
        return $collection->getSize();
    }

    /**
     * Set the status of many comments.
     *
     * @param int[] $commentIds
     * @param string $status
     * @return int Number updated
     */
    public function moderate(array $commentIds, string $status): int
    {
        if ($commentIds === []) {
            return 0;
        }
        $connection = $this->resource->getConnection();
        return $connection->update(
            $this->resource->getTableName('requestdesk_blog_comment'),
            ['status' => $status],
            ['comment_id IN (?)' => $commentIds]
        );
    }

    /**
     * Delete many comments.
     *
     * @param int[] $commentIds
     * @return int Number deleted
     */
    public function remove(array $commentIds): int
    {
        if ($commentIds === []) {
            return 0;
        }
        $connection = $this->resource->getConnection();
        return $connection->delete(
            $this->resource->getTableName('requestdesk_blog_comment'),
            ['comment_id IN (?)' => $commentIds]
        );
    }
}
