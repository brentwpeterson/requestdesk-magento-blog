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

namespace RequestDesk\Blog\Controller\Adminhtml\Config;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\HTTP\Client\Curl;

class TestConnection extends Action
{
    public const ADMIN_RESOURCE = 'RequestDesk_Blog::config';

    private JsonFactory $resultJsonFactory;
    private Curl $curl;

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        Curl $curl
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->curl = $curl;
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();

        $apiKey = $this->getRequest()->getParam('api_key');
        $endpointUrl = $this->getRequest()->getParam('endpoint_url');

        if (!$apiKey || !$endpointUrl) {
            return $result->setData([
                'success' => false,
                'message' => 'API Key and Endpoint URL are required.'
            ]);
        }

        try {
            // Clean up the endpoint URL
            $endpointUrl = rtrim($endpointUrl, '/');
            $testUrl = $endpointUrl . '/api/public/magento/test';

            // Set up the request
            $this->curl->addHeader('Content-Type', 'application/json');
            $this->curl->addHeader('x-requestdesk-api-key', $apiKey);
            $this->curl->setOption(CURLOPT_TIMEOUT, 10);
            $this->curl->setOption(CURLOPT_SSL_VERIFYPEER, true);

            // Make the test request
            $this->curl->post($testUrl, json_encode(['test' => true]));

            $statusCode = $this->curl->getStatus();
            $responseBody = $this->curl->getBody();
            $response = json_decode($responseBody, true);

            if ($statusCode === 200 && isset($response['success']) && $response['success']) {
                $companyName = $response['company_name'] ?? 'Unknown';
                return $result->setData([
                    'success' => true,
                    'message' => "Connected successfully! Company: {$companyName}"
                ]);
            } elseif ($statusCode === 401 || $statusCode === 403) {
                return $result->setData([
                    'success' => false,
                    'message' => 'Invalid API key. Please check your credentials.'
                ]);
            } else {
                $errorMsg = $response['detail'] ?? $response['message'] ?? "HTTP {$statusCode}";
                return $result->setData([
                    'success' => false,
                    'message' => "Connection failed: {$errorMsg}"
                ]);
            }
        } catch (\Throwable $e) {
            return $result->setData([
                'success' => false,
                'message' => 'Connection error: ' . $e->getMessage()
            ]);
        }
    }
}
