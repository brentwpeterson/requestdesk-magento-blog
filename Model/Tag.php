<?php
/**
 * RequestDesk Blog - Tag Model
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Model;

use Magento\Framework\Model\AbstractModel;
use RequestDesk\Blog\Model\ResourceModel\Tag as TagResource;

/**
 * A blog tag.
 */
class Tag extends AbstractModel
{
    /**
     * @inheritdoc
     */
    protected function _construct(): void
    {
        $this->_init(TagResource::class);
    }

    /**
     * Get name
     *
     * @return string
     */
    public function getName(): string
    {
        return (string) $this->getData('name');
    }

    /**
     * Get url key
     *
     * @return string
     */
    public function getUrlKey(): string
    {
        return (string) $this->getData('url_key');
    }
}
