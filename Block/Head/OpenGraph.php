<?php
/**
 * RequestDesk Blog - Open Graph head block
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Block\Head;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\StoreManagerInterface;
use RequestDesk\Blog\Api\Data\PostInterface;
use RequestDesk\Blog\Api\PostRepositoryInterface;
use RequestDesk\Blog\Block\ImageUrl;

/**
 * Emits Open Graph meta tags (property=) for the current post in the head.
 * Magento's setMetadata renders name=, which is invalid for OG, so this outputs
 * the raw property= tags a social scraper needs.
 */
class OpenGraph extends Template
{
    /**
     * @var PostInterface|null|false
     */
    private $post = null;

    /**
     * @param Context $context
     * @param PostRepositoryInterface $postRepository
     * @param StoreManagerInterface $storeManager
     * @param array $data
     */
    public function __construct(
        Context $context,
        private readonly PostRepositoryInterface $postRepository,
        private readonly StoreManagerInterface $storeManager,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return PostInterface|null
     */
    public function getPost(): ?PostInterface
    {
        if ($this->post === null) {
            $this->post = false;
            $id = (int) $this->getRequest()->getParam('id');
            if ($id > 0) {
                try {
                    $post = $this->postRepository->getById($id);
                    if ((int) $post->getStatus() === PostInterface::STATUS_PUBLISHED) {
                        $this->post = $post;
                    }
                } catch (\Throwable $e) {
                    $this->post = false;
                }
            }
        }
        return $this->post ?: null;
    }

    /**
     * @return string
     */
    public function getOgTitle(): string
    {
        $post = $this->getPost();
        return $post ? (string) ($post->getMetaTitle() ?: $post->getTitle()) : '';
    }

    /**
     * @return string
     */
    public function getOgDescription(): string
    {
        $post = $this->getPost();
        return $post ? (string) $post->getMetaDescription() : '';
    }

    /**
     * @return string
     */
    public function getOgImageUrl(): string
    {
        $post = $this->getPost();
        return $post ? ImageUrl::resolve($post->getFeaturedImage(), $this->storeManager) : '';
    }
}
