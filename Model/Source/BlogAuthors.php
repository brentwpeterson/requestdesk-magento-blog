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

namespace RequestDesk\Blog\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;
use RequestDesk\Blog\Model\ResourceModel\Author\CollectionFactory;

/**
 * Blog authors, for crediting a post. Replaces the admin-user list: an author
 * here may or may not have a Magento account.
 */
class BlogAuthors implements OptionSourceInterface
{
    /**
     * @param CollectionFactory $authorCollectionFactory
     */
    public function __construct(
        private readonly CollectionFactory $authorCollectionFactory
    ) {
    }

    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        $collection = $this->authorCollectionFactory->create();
        $collection->setOrder('name', 'ASC');

        $options = [['value' => '', 'label' => __('-- None --')]];
        foreach ($collection as $author) {
            $options[] = [
                'value' => (int) $author->getData('author_id'),
                'label' => (string) $author->getData('name'),
            ];
        }
        return $options;
    }
}
