<?php
/**
 * RequestDesk Blog - Post Featured Image Upload Controller
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Controller\Adminhtml\Post;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Catalog\Model\ImageUploader;
use Magento\Framework\Controller\Result\JsonFactory;

/**
 * Receives a featured image from the post form's image uploader and stores it
 * in the media tmp folder. The path is committed to the post when the form is
 * saved.
 */
class Upload extends Action
{
    /**
     * Authorization level
     */
    public const ADMIN_RESOURCE = 'RequestDesk_Blog::manage';

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
        $imageId = $this->_request->getParam('param_name', 'featured_image');

        try {
            $result = $this->imageUploader->saveFileToTmpDir($imageId);
        } catch (\Throwable $e) {
            $result = ['error' => $e->getMessage(), 'errorcode' => $e->getCode()];
        }

        return $this->resultJsonFactory->create()->setData($result);
    }
}
