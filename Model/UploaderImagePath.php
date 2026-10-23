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

namespace RequestDesk\Blog\Model;

use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use RequestDesk\Blog\Block\ImageUrl;

/**
 * Works out which stored path an admin imageUploader entry stands for, when it
 * is not a fresh upload.
 *
 * The uploader posts one of three things: a fresh upload (carries tmp_name; the
 * caller moves it out of tmp), the image the form was opened with (echoes back
 * the url the data provider gave it), or a pick from "Select from Gallery".
 * A gallery pick is never uploaded - it only posts the chosen file's url - so
 * treating every entry without tmp_name as "unchanged" silently threw the pick
 * away and kept the old image.
 */
class UploaderImagePath
{
    /**
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * The path to store for an entry without tmp_name.
     *
     * @param array $file One entry from the uploader's posted list
     * @param string $current Path already stored, if any
     * @return string Media-relative path, or the url itself if it lives outside media
     */
    public function resolve(array $file, string $current): string
    {
        $url = trim((string)($file['url'] ?? ''));

        // Nothing to compare against: keep what is there.
        if ($url === '') {
            return $current !== '' ? $current : (string)($file['name'] ?? '');
        }

        if ($current !== '' && $this->samePath($url, ImageUrl::resolve($current, $this->storeManager))) {
            return $current;
        }

        return $this->toMediaPath($url);
    }

    /**
     * Strip the media base off a gallery url, so it is stored like any other image.
     *
     * @param string $url
     * @return string
     */
    private function toMediaPath(string $url): string
    {
        $path = (string)parse_url($url, PHP_URL_PATH); // phpcs:ignore Magento2.Functions.DiscouragedFunction
        try {
            $mediaBase = (string)parse_url( // phpcs:ignore Magento2.Functions.DiscouragedFunction
                $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA),
                PHP_URL_PATH
            );
        } catch (\Throwable $e) {
            return $url;
        }

        $mediaBase = '/' . trim($mediaBase, '/') . '/';
        if ($path !== '' && str_starts_with($path, $mediaBase)) {
            return rawurldecode(substr($path, strlen($mediaBase)));
        }

        return $url;
    }

    /**
     * Do two urls name the same file, ignoring host and query string?
     *
     * @param string $a
     * @param string $b
     * @return bool
     */
    private function samePath(string $a, string $b): bool
    {
        // phpcs:ignore Magento2.Functions.DiscouragedFunction
        return rawurldecode((string)parse_url($a, PHP_URL_PATH)) === rawurldecode((string)parse_url($b, PHP_URL_PATH));
    }
}
