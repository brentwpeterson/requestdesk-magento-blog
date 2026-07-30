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

namespace RequestDesk\Blog\Controller\Adminhtml\Import;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use RequestDesk\Blog\Service\PostImportService;
use Psr\Log\LoggerInterface;

class Posts extends Action
{
    /**
     * Authorization level
     */
    public const ADMIN_RESOURCE = 'RequestDesk_Blog::import';

    /**
     * @var JsonFactory
     */
    private JsonFactory $resultJsonFactory;

    /**
     * @var PostImportService
     */
    private PostImportService $postImportService;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param PostImportService $postImportService
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        PostImportService $postImportService,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->postImportService = $postImportService;
        $this->logger = $logger;
    }

    /**
     * Execute import action
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $resultJson = $this->resultJsonFactory->create();

        try {
            $this->logger->info('RequestDesk: Post import initiated from admin');

            // Get optional parameters from request
            $status = $this->getRequest()->getParam('status', 'publish');
            $syncStatus = $this->getRequest()->getParam('sync_status');
            $page = (int)$this->getRequest()->getParam('page', 1);
            $perPage = (int)$this->getRequest()->getParam('per_page', 20);

            // Execute post import
            $result = $this->postImportService->importPosts(
                $status,
                $syncStatus,
                $page,
                $perPage
            );

            if ($result['success']) {
                $message = __(
                    'Import complete: %1 created, %2 updated, %3 failed.',
                    $result['imported'] ?? 0,
                    $result['updated'] ?? 0,
                    $result['failed'] ?? 0
                );
                $this->messageManager->addSuccessMessage($message);
            } else {
                $this->messageManager->addErrorMessage(
                    __('Import failed: %1', $result['error'] ?? 'Unknown error')
                );
            }

            return $resultJson->setData($result);

        } catch (\Throwable $e) {
            $this->logger->error('RequestDesk: Post import failed - ' . $e->getMessage());
            $this->messageManager->addErrorMessage(__('Import failed: %1', $e->getMessage()));

            return $resultJson->setData([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
}
