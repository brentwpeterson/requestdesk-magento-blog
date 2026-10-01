<?php
/**
 * RequestDesk Blog - Category Model
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Model;

use Magento\Framework\Model\AbstractModel;
use RequestDesk\Blog\Model\ResourceModel\Category as CategoryResource;

/**
 * A blog category (requestdesk_blog_category).
 */
class Category extends AbstractModel
{
    public const STATUS_DISABLED = 0;
    public const STATUS_ENABLED = 1;

    /**
     * @inheritdoc
     */
    protected function _construct(): void
    {
        $this->_init(CategoryResource::class);
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

    /**
     * Get parent id
     *
     * @return int
     */
    public function getParentId(): int
    {
        return (int) $this->getData('parent_id');
    }

    /**
     * Whether enabled
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return (int) $this->getData('status') === self::STATUS_ENABLED;
    }

    /**
     * Get description
     *
     * @return string
     */
    public function getDescription(): string
    {
        return (string) $this->getData('description');
    }

    /**
     * Get meta title
     *
     * @return string
     */
    public function getMetaTitle(): string
    {
        return (string) $this->getData('meta_title');
    }

    /**
     * Get meta description
     *
     * @return string
     */
    public function getMetaDescription(): string
    {
        return (string) $this->getData('meta_description');
    }

    /**
     * Get meta tags
     *
     * @return string
     */
    public function getMetaTags(): string
    {
        return (string) $this->getData('meta_tags');
    }

    /**
     * Get meta robots
     *
     * @return string
     */
    public function getMetaRobots(): string
    {
        return (string) $this->getData('meta_robots');
    }
}
