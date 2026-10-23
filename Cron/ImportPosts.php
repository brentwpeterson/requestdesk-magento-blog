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

use Psr\Log\LoggerInterface;
use RequestDesk\Blog\Service\PostImportService;

class ImportPosts
{
    /**
     * @var PostImportService
     */
    private PostImportService $postImportService;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param PostImportService $postImportService
     * @param LoggerInterface $logger
     */
    public function __construct(
        PostImportService $postImportService,
        LoggerInterface $logger
    ) {
        $this->postImportService = $postImportService;
        $this->logger = $logger;
    }

    /**
     * Execute cron job
     *
     * @return void
     */
    public function execute(): void
    {
        $this->logger->debug('RequestDesk: Starting scheduled post import');

        try {
            // Import published posts that haven't been synced yet, for each
            // website that has Automated Import enabled and its own API key
            $result = $this->postImportService->importForAllWebsites(
                'publish',      // Only published posts
                'not_synced',   // Only posts not yet synced to Magento
                1,              // Page 1
                50,             // Up to 50 posts per website per run
                true            // Only websites with Automated Import enabled
            );

            if (!empty($result['disabled'])) {
                $this->logger->debug('RequestDesk: Post import cron is disabled');
                return;
            }

            if ($result['success']) {
                $this->logger->info(sprintf(
                    'RequestDesk: Scheduled import complete - %d created, %d updated, %d failed',
                    $result['imported'] ?? 0,
                    $result['updated'] ?? 0,
                    $result['failed'] ?? 0
                ));

                // If there are more posts, log it
                if (!empty($result['has_more'])) {
                    $this->logger->info('RequestDesk: More posts available, will import on next cron run');
                }
            } else {
                $this->logger->error('RequestDesk: Scheduled import failed - ' . ($result['error'] ?? 'Unknown error'));
            }

            foreach ($result['errors'] ?? [] as $error) {
                $this->logger->warning('RequestDesk: ' . $error);
            }

        } catch (\Exception $e) {
            $this->logger->error('RequestDesk: Scheduled import exception - ' . $e->getMessage());
        }
    }
}
