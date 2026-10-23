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

namespace RequestDesk\Blog\Cron;

use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;
use RequestDesk\Blog\Api\Data\PostInterface;
use RequestDesk\Blog\Model\BlogCache;

/**
 * Publishes Scheduled posts whose Published Date (created_at, UTC) has passed.
 *
 * Runs every minute (etc/crontab.xml), so a post goes live within a minute of
 * its time. The UPDATE re-checks the status, so a post someone changed in the
 * admin between the SELECT and the UPDATE is left as they set it.
 */
class PublishScheduledPosts
{
    /**
     * @param ResourceConnection $resource
     * @param BlogCache $blogCache
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly BlogCache $blogCache,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Execute cron job
     *
     * @return void
     */
    public function execute(): void
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('requestdesk_blog_post');
        $now = gmdate('Y-m-d H:i:s');

        $due = array_map('intval', $connection->fetchCol(
            $connection->select()
                ->from($table, ['post_id'])
                ->where('status = ?', PostInterface::STATUS_SCHEDULED)
                ->where('created_at <= ?', $now)
        ));
        if ($due === []) {
            return;
        }

        $published = $connection->update(
            $table,
            ['status' => PostInterface::STATUS_PUBLISHED, 'updated_at' => $now],
            ['post_id IN (?)' => $due, 'status = ?' => PostInterface::STATUS_SCHEDULED]
        );
        if ($published > 0) {
            $this->blogCache->clean();
            $this->logger->info('RequestDesk Blog: published scheduled posts', ['post_ids' => $due]);
        }
    }
}
