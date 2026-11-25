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
use Magento\Framework\App\Config\ScopeConfigInterface;

class ImportPosts
{
    private const XML_PATH_CRON_ENABLED = 'requestdesk_blog/cron/enabled';

    /**
     * @var PostImportService
     */
    private PostImportService $postImportService;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * @param PostImportService $postImportService
     * @param LoggerInterface $logger
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        PostImportService $postImportService,
        LoggerInterface $logger,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->postImportService = $postImportService;
        $this->logger = $logger;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Execute cron job
     *
     * @return void
     */
    public function execute(): void
    {
        // Check if cron is enabled
        $cronEnabled = $this->scopeConfig->getValue(
            self::XML_PATH_CRON_ENABLED,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        if (!$cronEnabled) {
            $this->logger->debug('RequestDesk: Post import cron is disabled');
            return;
        }

        $this->logger->info('RequestDesk: Starting scheduled post import');

        try {
            // Import published posts that haven't been synced yet
            $result = $this->postImportService->importPosts(
                'publish',      // Only published posts
                'not_synced',   // Only posts not yet synced to Magento
                1,              // Page 1
                50              // Up to 50 posts per run
            );

            if ($result['success']) {
                $this->logger->info(sprintf(
                    'RequestDesk: Scheduled import complete - %d created, %d updated, %d failed',
                    $result['imported'] ?? 0,
                    $result['updated'] ?? 0,
                    $result['failed'] ?? 0
                ));

                foreach ($result['warnings'] ?? [] as $warning) {
                    $this->logger->error('RequestDesk: ' . $warning);
                }

                // If there are more posts, log it
                if (!empty($result['has_more'])) {
                    $this->logger->info('RequestDesk: More posts available, will import on next cron run');
                }
            } else {
                $this->logger->error('RequestDesk: Scheduled import failed - ' . ($result['error'] ?? 'Unknown error'));
            }

        } catch (\Exception $e) {
            $this->logger->error('RequestDesk: Scheduled import exception - ' . $e->getMessage());
        }
    }
}
