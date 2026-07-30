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

namespace RequestDesk\Blog\Controller\Adminhtml\Sync;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use RequestDesk\Blog\Service\ProductExportService;
use Psr\Log\LoggerInterface;

class Test extends Action
{
    /**
     * Authorization level
     */
    public const ADMIN_RESOURCE = 'RequestDesk_Blog::config';

    /**
     * @var JsonFactory
     */
    private JsonFactory $resultJsonFactory;

    /**
     * @var ProductExportService
     */
    private ProductExportService $productExportService;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param ProductExportService $productExportService
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        ProductExportService $productExportService,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->productExportService = $productExportService;
        $this->logger = $logger;
    }

    /**
     * Execute connection test
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $resultJson = $this->resultJsonFactory->create();

        try {
            $this->logger->info('RequestDesk: Connection test initiated from admin');

            $result = $this->productExportService->testConnection();

            if ($result['success']) {
                $message = $result['agent_name']
                    ? __('Connected to RequestDesk! Agent: %1', $result['agent_name'])
                    : __('Connected to RequestDesk successfully!');
                $this->messageManager->addSuccessMessage($message);
            } else {
                $this->messageManager->addErrorMessage(
                    __('Connection failed: %1', $result['error'] ?? 'Unknown error')
                );
            }

            return $resultJson->setData($result);

        } catch (\Throwable $e) {
            $this->logger->error('RequestDesk: Connection test failed - ' . $e->getMessage());
            $this->messageManager->addErrorMessage(__('Connection test failed: %1', $e->getMessage()));

            return $resultJson->setData([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
}
