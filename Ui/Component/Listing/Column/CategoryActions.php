<?php
/**
 * RequestDesk Blog - Category Grid Actions Column
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class CategoryActions extends Column
{
    /**
     * URL paths
     */
    public const URL_PATH_EDIT = 'requestdesk_blog/category/edit';
    public const URL_PATH_DELETE = 'requestdesk_blog/category/delete';

    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param UrlInterface $urlBuilder
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly UrlInterface $urlBuilder,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * Prepare Data Source
     *
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource): array
    {
        foreach ($dataSource['data']['items'] ?? [] as $i => $item) {
            if (!isset($item['category_id'])) {
                continue;
            }
            $params = ['category_id' => $item['category_id']];
            $dataSource['data']['items'][$i][$this->getData('name')] = [
                'edit' => [
                    'href' => $this->urlBuilder->getUrl(static::URL_PATH_EDIT, $params),
                    'label' => __('Edit'),
                ],
                'delete' => [
                    'href' => $this->urlBuilder->getUrl(static::URL_PATH_DELETE, $params),
                    'label' => __('Delete'),
                    'confirm' => [
                        'title' => __('Delete Category'),
                        'message' => __(
                            'Are you sure you want to delete this category? It will be removed from all posts, '
                            . 'and its subcategories will move up one level.'
                        ),
                    ],
                    'post' => true,
                ],
            ];
        }

        return $dataSource;
    }
}
