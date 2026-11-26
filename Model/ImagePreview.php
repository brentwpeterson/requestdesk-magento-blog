<?php
/**
 * RequestDesk Blog - image data for the admin image uploader
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Model;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Store\Model\StoreManagerInterface;
use RequestDesk\Blog\Block\ImageUrl;

/**
 * The file entry an imageUploader field shows for an image already saved.
 *
 * Magento's image preview prints the file size through formatSize(), which
 * turns a missing size into "NaN undefined". The post and author forms sent
 * only a name and a URL, so every saved image showed that (Evrig, 2026-11-26).
 *
 * A file in pub/media gets its real size. An image held elsewhere, such as the
 * S3 URL a RequestDesk import stores, has no size here, and the module's own
 * preview template shows its dimensions without one.
 */
class ImagePreview
{
    /**
     * @param StoreManagerInterface $storeManager
     * @param Filesystem $filesystem
     */
    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly Filesystem $filesystem
    ) {
    }

    /**
     * The uploader's file list for a stored image path or URL.
     *
     * @param string $path Media-relative path, root-relative path or absolute URL
     * @return array<int, array{name:string, url:string, size?:int}>
     */
    public function forUploader(string $path): array
    {
        $path = trim($path);
        if ($path === '') {
            return [];
        }

        $file = [
            'name' => basename($path), // phpcs:ignore Magento2.Functions.DiscouragedFunction
            'url' => ImageUrl::resolve($path, $this->storeManager),
        ];

        $size = $this->localSize($path);
        if ($size !== null) {
            $file['size'] = $size;
        }

        return [$file];
    }

    /**
     * Size in bytes of an image stored in pub/media, or null when the image is
     * not a local media file.
     *
     * @param string $path
     * @return int|null
     */
    private function localSize(string $path): ?int
    {
        if (preg_match('#^([a-z][a-z0-9+.-]*:)?//#i', $path)) {
            return null;
        }

        $relative = ltrim((string) preg_replace('#^/?(pub/)?media/#', '', $path), '/');
        if ($relative === '') {
            return null;
        }

        // The size is display only. A media directory that cannot be read
        // leaves the size off, and the preview shows the image without it.
        try {
            $media = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA);
            if (!$media->isFile($relative)) {
                return null;
            }
            $stat = $media->stat($relative);
        } catch (\Exception $e) {
            return null;
        }

        return isset($stat['size']) ? (int) $stat['size'] : null;
    }
}
