<?php
/**
 * RequestDesk Blog - Tag Save Controller
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Controller\Adminhtml\Tag;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;
use RequestDesk\Blog\Model\ResourceModel\Tag as TagResource;
use RequestDesk\Blog\Model\TagFactory;

class Save extends Action
{
    /**
     * Authorization level
     */
    const ADMIN_RESOURCE = 'RequestDesk_Blog::tags';

    /**
     * @param Context $context
     * @param TagFactory $tagFactory
     * @param TagResource $tagResource
     */
    public function __construct(
        Context $context,
        private readonly TagFactory $tagFactory,
        private readonly TagResource $tagResource
    ) {
        parent::__construct($context);
    }

    /**
     * Save a tag
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $data = $this->getRequest()->getPostValue();

        if (!$data) {
            return $resultRedirect->setPath('*/*/');
        }

        $tagId = isset($data['tag_id']) ? (int)$data['tag_id'] : null;

        try {
            $tag = $this->tagFactory->create();
            if ($tagId) {
                $this->tagResource->load($tag, $tagId);
                if (!$tag->getId()) {
                    throw new LocalizedException(__('This tag no longer exists.'));
                }
            }

            $name = trim((string)($data['name'] ?? ''));
            if ($name === '') {
                throw new LocalizedException(__('Tag name is required.'));
            }

            $urlKey = trim((string)($data['url_key'] ?? ''));
            if ($urlKey === '') {
                $urlKey = $name;
            }
            $urlKey = $this->slugify($urlKey);

            $tag->setData('name', $name);
            $tag->setData('url_key', $urlKey);

            $this->tagResource->save($tag);

            $this->messageManager->addSuccessMessage(__('The tag has been saved.'));

            if ($this->getRequest()->getParam('back')) {
                return $resultRedirect->setPath('*/*/edit', ['tag_id' => $tag->getId()]);
            }

            return $resultRedirect->setPath('*/*/');
        } catch (AlreadyExistsException $e) {
            $this->messageManager->addErrorMessage(
                __('A tag with URL key "%1" already exists.', $data['url_key'] ?? $data['name'] ?? '')
            );
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('An error occurred while saving the tag.'));
        }

        if ($tagId) {
            return $resultRedirect->setPath('*/*/edit', ['tag_id' => $tagId]);
        }
        return $resultRedirect->setPath('*/*/edit');
    }

    /**
     * Normalize a string into a URL key.
     *
     * @param string $value
     * @return string
     */
    private function slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = (string)preg_replace('/[^a-z0-9]+/', '-', $value);
        return trim($value, '-');
    }
}
