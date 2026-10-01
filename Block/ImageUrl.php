<?php
/**
 * RequestDesk Blog - Featured Image URL Resolver
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength.TooLong

namespace RequestDesk\Blog\Block;

use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Turns a stored featured-image path into a usable URL. Accepts an absolute
 * URL, a root-relative path, or a media-relative path.
 */
class ImageUrl
{
    /**
     * Resolve
     *
     * @param string|null $path
     * @param StoreManagerInterface $storeManager
     * @return string
     */
    public static function resolve(?string $path, StoreManagerInterface $storeManager): string // phpcs:ignore Magento2.Functions.StaticFunction
    {
        $path = trim((string) $path);
        if ($path === '') {
            return '';
        }
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '/')) {
            return $path;
        }
        try {
            $mediaUrl = $storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);
            return rtrim($mediaUrl, '/') . '/' . ltrim($path, '/');
        } catch (\Throwable $e) {
            return '';
        }
    }
}
