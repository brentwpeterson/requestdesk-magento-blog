<?php
/**
 * RequestDesk Blog - Author Avatar Upload Controller
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Controller\Adminhtml\Author;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Catalog\Model\ImageUploader;
use Magento\Framework\Controller\Result\JsonFactory;

/**
 * Receives an avatar image from the author form's image uploader and stores it
 * in the media tmp folder. The path is committed to the author record when the
 * form is saved.
 */
class Upload extends Action
{
    /**
     * Authorization level
     */
    const ADMIN_RESOURCE = 'RequestDesk_Blog::authors';

    /**
     * @param Context $context
     * @param ImageUploader $imageUploader
     * @param JsonFactory $resultJsonFactory
     */
    public function __construct(
        Context $context,
        private readonly ImageUploader $imageUploader,
        private readonly JsonFactory $resultJsonFactory
    ) {
        parent::__construct($context);
    }

    /**
     * Upload the posted file.
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $imageId = $this->_request->getParam('param_name', 'avatar');

        try {
            $result = $this->imageUploader->saveFileToTmpDir($imageId);
        } catch (\Throwable $e) {
            $result = ['error' => $e->getMessage(), 'errorcode' => $e->getCode()];
        }

        return $this->resultJsonFactory->create()->setData($result);
    }
}
