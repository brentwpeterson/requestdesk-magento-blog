<?php
/**
 * RequestDesk Blog - Author Profile Edit Delete Button
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Block\Adminhtml\Author\Edit;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class DeleteButton extends GenericButton implements ButtonProviderInterface
{
    /**
     * @return array
     */
    public function getButtonData(): array
    {
        $data = [];
        if ($this->getUserId()) {
            $data = [
                'label' => __('Delete Profile'),
                'class' => 'delete',
                'on_click' => 'deleteConfirm(\'' . __(
                    'Delete this author profile? The admin user account itself is not affected.'
                ) . '\', \'' . $this->getUrl('*/*/delete', ['admin_user_id' => $this->getUserId()]) . '\', {"data": {}})',
                'sort_order' => 20,
            ];
        }
        return $data;
    }
}
