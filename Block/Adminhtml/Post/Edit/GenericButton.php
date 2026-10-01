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

namespace RequestDesk\Blog\Block\Adminhtml\Post\Edit;

use Magento\Backend\Block\Widget\Context;
use RequestDesk\Blog\Api\PostRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;

class GenericButton
{
    /**
     * @var Context
     */
    protected Context $context;

    /**
     * @var PostRepositoryInterface
     */
    protected PostRepositoryInterface $postRepository;

    /**
     * @param Context $context
     * @param PostRepositoryInterface $postRepository
     */
    public function __construct(
        Context $context,
        PostRepositoryInterface $postRepository
    ) {
        $this->context = $context;
        $this->postRepository = $postRepository;
    }

    /**
     * Get post ID
     *
     * @return int|null
     */
    public function getPostId(): ?int
    {
        try {
            $postId = (int)$this->context->getRequest()->getParam('post_id');
            if ($postId) {
                return $postId;
            }
        } catch (NoSuchEntityException $e) { // phpcs:ignore Magento2.CodeAnalysis.EmptyBlock
            // Do nothing
        }
        return null;
    }

    /**
     * Generate url by route and parameters
     *
     * @param string $route
     * @param array $params
     * @return string
     */
    public function getUrl(string $route = '', array $params = []): string
    {
        return $this->context->getUrlBuilder()->getUrl($route, $params);
    }
}
