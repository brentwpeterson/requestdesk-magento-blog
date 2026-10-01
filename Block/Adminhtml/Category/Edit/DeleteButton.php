<?php
/**
 * RequestDesk Blog - Category Edit Delete Button
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength.TooLong

namespace RequestDesk\Blog\Block\Adminhtml\Category\Edit;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class DeleteButton extends GenericButton implements ButtonProviderInterface
{
    /**
     * Button configuration
     *
     * @return array
     */
    public function getButtonData(): array
    {
        $data = [];
        if ($this->getCategoryId()) {
            $data = [
                'label' => __('Delete'),
                'class' => 'delete',
                'on_click' => 'deleteConfirm(\'' . __(
                    'Are you sure you want to delete this category? It will be removed from all posts, and its subcategories will move up one level.'
                ) . '\', \'' . $this->getUrl('*/*/delete', ['category_id' => $this->getCategoryId()]) . '\', {"data": {}})',
                'sort_order' => 20,
            ];
        }
        return $data;
    }
}
