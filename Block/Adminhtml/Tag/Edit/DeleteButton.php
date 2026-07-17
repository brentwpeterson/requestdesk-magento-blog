<?php
/**
 * RequestDesk Blog - Tag Edit Delete Button
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Block\Adminhtml\Tag\Edit;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class DeleteButton extends GenericButton implements ButtonProviderInterface
{
    /**
     * @return array
     */
    public function getButtonData(): array
    {
        $data = [];
        if ($this->getTagId()) {
            $data = [
                'label' => __('Delete'),
                'class' => 'delete',
                'on_click' => 'deleteConfirm(\'' . __(
                    'Are you sure you want to delete this tag? It will be removed from all posts.'
                ) . '\', \'' . $this->getUrl('*/*/delete', ['tag_id' => $this->getTagId()]) . '\', {"data": {}})',
                'sort_order' => 20,
            ];
        }
        return $data;
    }
}
