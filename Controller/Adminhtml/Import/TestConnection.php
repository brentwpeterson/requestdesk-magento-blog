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

class TestConnection extends Action
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
     * Execute test connection action
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $resultJson = $this->resultJsonFactory->create();

        try {
            $this->logger->info('RequestDesk: Posts API connection test initiated');

            $result = $this->postImportService->testConnection();

            if ($result['success']) {
                $message = __('Posts API connection successful!');
                if (!empty($result['posts_available'])) {
                    $message = __(
                        'Posts API connection successful! %1 posts available from %2.',
                        $result['posts_available'],
                        $result['agent_name'] ?? 'RequestDesk'
                    );
                }
                $this->messageManager->addSuccessMessage($message);
            } else {
                $this->messageManager->addErrorMessage(
                    __('Connection failed: %1', $result['error'] ?? 'Unknown error')
                );
            }

            return $resultJson->setData($result);

        } catch (\Throwable $e) {
            $this->logger->error('RequestDesk: Posts API connection test failed - ' . $e->getMessage());
            $this->messageManager->addErrorMessage(__('Connection test failed: %1', $e->getMessage()));

            return $resultJson->setData([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
}
