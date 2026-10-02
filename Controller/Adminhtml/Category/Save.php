<?php
/**
 * RequestDesk Blog - Category Save Controller
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Controller\Adminhtml\Category;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;
use RequestDesk\Blog\Model\Category;
use RequestDesk\Blog\Model\Category\DataProvider;
use RequestDesk\Blog\Model\CategoryFactory;
use RequestDesk\Blog\Model\ResourceModel\Category as CategoryResource;

class Save extends Action implements HttpPostActionInterface
{
    /**
     * Authorization level
     */
    public const ADMIN_RESOURCE = 'RequestDesk_Blog::categories';

    /** Form fields written straight through, after trimming. */
    private const TEXT_FIELDS = ['description', 'meta_title', 'meta_tags', 'meta_description', 'meta_robots'];

    /**
     * @param Context $context
     * @param CategoryFactory $categoryFactory
     * @param CategoryResource $categoryResource
     * @param DataPersistorInterface $dataPersistor
     */
    public function __construct(
        Context $context,
        private readonly CategoryFactory $categoryFactory,
        private readonly CategoryResource $categoryResource,
        private readonly DataPersistorInterface $dataPersistor
    ) {
        parent::__construct($context);
    }

    /**
     * Save a category
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

        $categoryId = !empty($data['category_id']) ? (int) $data['category_id'] : null;

        try {
            $category = $this->categoryFactory->create();
            if ($categoryId) {
                $this->categoryResource->load($category, $categoryId);
                if (!$category->getId()) {
                    throw new LocalizedException(__('This category no longer exists.'));
                }
            }

            $name = trim((string) ($data['name'] ?? ''));
            if ($name === '') {
                throw new LocalizedException(__('Category name is required.'));
            }

            $urlKey = self::slugify(trim((string) ($data['url_key'] ?? '')) ?: $name);
            if ($urlKey === '') {
                throw new LocalizedException(__('The URL key must contain at least one letter or number.'));
            }

            $parentId = max(0, (int) ($data['parent_id'] ?? 0));
            if ($categoryId && $parentId > 0) {
                // Under itself or under its own descendant would cut the
                // subtree off from the top level, and out of every archive list.
                if ($parentId === $categoryId
                    || in_array($parentId, $this->categoryResource->getDescendantIds($categoryId), true)
                ) {
                    throw new LocalizedException(
                        __('A category cannot be placed under itself or one of its own subcategories.')
                    );
                }
            }

            $category->setData('name', $name);
            $category->setData('url_key', $urlKey);
            $category->setData('parent_id', $parentId);
            $category->setData(
                'status',
                (int) ($data['status'] ?? Category::STATUS_ENABLED) === Category::STATUS_ENABLED
                    ? Category::STATUS_ENABLED
                    : Category::STATUS_DISABLED
            );
            $category->setData('sort_order', max(0, (int) ($data['sort_order'] ?? 0)));
            foreach (self::TEXT_FIELDS as $field) {
                $value = trim((string) ($data[$field] ?? ''));
                $category->setData($field, $value !== '' ? $value : null);
            }

            $this->categoryResource->save($category);

            $this->dataPersistor->clear(DataProvider::PERSISTOR_KEY);
            $this->messageManager->addSuccessMessage(__('The category has been saved.'));

            if ($this->getRequest()->getParam('back')) {
                return $resultRedirect->setPath('*/*/edit', ['category_id' => $category->getId()]);
            }

            return $resultRedirect->setPath('*/*/');
        } catch (AlreadyExistsException $e) {
            $this->messageManager->addErrorMessage(
                __('A category with URL key "%1" already exists.', $urlKey ?? '')
            );
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('An error occurred while saving the category.'));
        }

        $this->dataPersistor->set(DataProvider::PERSISTOR_KEY, $data);

        return $categoryId
            ? $resultRedirect->setPath('*/*/edit', ['category_id' => $categoryId])
            : $resultRedirect->setPath('*/*/edit');
    }

    /**
     * Normalize a string into a URL key.
     *
     * @param string $value
     * @return string
     */
    public static function slugify(string $value): string // phpcs:ignore Magento2.Functions.StaticFunction
    {
        $value = strtolower(trim($value));
        $value = (string) preg_replace('/[^a-z0-9]+/', '-', $value);
        return trim($value, '-');
    }
}
