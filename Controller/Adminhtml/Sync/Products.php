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

class Products extends Action
{
    /**
     * Authorization level
     */
    public const ADMIN_RESOURCE = 'RequestDesk_Blog::sync';

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
     * Execute sync action
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $resultJson = $this->resultJsonFactory->create();

        try {
            $this->logger->info('RequestDesk: Product sync initiated from admin');

            // Get optional limit from request (for testing)
            $limit = $this->getRequest()->getParam('limit');
            $limit = $limit ? (int)$limit : null;

            // Execute product export
            $result = $this->productExportService->exportProducts($limit);

            if ($result['success']) {
                $this->messageManager->addSuccessMessage(
                    __('Successfully synced %1 products to RequestDesk.', $result['total_synced'] ?? 0)
                );
            } else {
                $this->messageManager->addErrorMessage(
                    __('Sync failed: %1', $result['error'] ?? 'Unknown error')
                );
            }

            return $resultJson->setData($result);

        } catch (\Throwable $e) {
            $this->logger->error('RequestDesk: Product sync failed - ' . $e->getMessage());
            $this->messageManager->addErrorMessage(__('Sync failed: %1', $e->getMessage()));

            return $resultJson->setData([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
}
