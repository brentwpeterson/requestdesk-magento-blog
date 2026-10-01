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

namespace RequestDesk\Blog\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class TestConnection extends Field
{
    /** @var string */
    protected $_template = 'RequestDesk_Blog::system/config/test_connection.phtml';

    /**
     * Render the element
     *
     * @param AbstractElement $element
     * @return string
     */
    public function render(AbstractElement $element): string
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    /**
     * Get element html
     *
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element): string
    {
        return $this->_toHtml();
    }

    /**
     * Get ajax url
     *
     * @return string
     */
    public function getAjaxUrl(): string
    {
        return $this->getUrl('requestdesk_blog/config/testconnection');
    }

    /**
     * Get button html
     *
     * @return string
     */
    public function getButtonHtml(): string
    {
        $button = $this->getLayout()->createBlock(
            \Magento\Backend\Block\Widget\Button::class
        )->setData([
            'id' => 'requestdesk_test_connection',
            'label' => __('Test Connection'),
            'class' => 'primary'
        ]);

        return $button->toHtml();
    }
}
