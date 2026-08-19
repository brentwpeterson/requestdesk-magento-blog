<?php
/**
 * Copyright © RequestDesk. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace RequestDesk\Blog\Test\Unit\Block;

use Magento\Framework\UrlInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use RequestDesk\Blog\Block\ImageUrl;

/**
 * ImageUrl became load-bearing in 1.6.6. Before that the Hyva templates put the
 * raw stored value straight into src, so a media-relative path like
 * "blog/hero.jpg" resolved against the current page URL and 404'd - blog images
 * simply did not appear on Hyva, while Luma was fine because it already called
 * this resolver. These tests pin the three shapes of stored path that exist in
 * real data, so a future edit cannot quietly send one of them back out unresolved.
 */
class ImageUrlTest extends TestCase
{
    private function storeManagerReturning(string $mediaUrl): StoreManagerInterface
    {
        $store = $this->createMock(Store::class);
        $store->method('getBaseUrl')->with(UrlInterface::URL_TYPE_MEDIA)->willReturn($mediaUrl);

        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        return $storeManager;
    }

    public function testNullPathResolvesToEmptyString(): void
    {
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->expects($this->never())->method('getStore');

        $this->assertSame('', ImageUrl::resolve(null, $storeManager));
    }

    /**
     * Whitespace counts as empty. A featured_image column that was "cleared" by
     * saving a space would otherwise produce a media URL pointing at the media
     * root, which renders as a broken image rather than as no image at all.
     */
    public function testWhitespaceOnlyPathResolvesToEmptyString(): void
    {
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->expects($this->never())->method('getStore');

        $this->assertSame('', ImageUrl::resolve('   ', $storeManager));
    }

    public function testMediaRelativePathIsPrefixedWithTheMediaBaseUrl(): void
    {
        $storeManager = $this->storeManagerReturning('https://example.test/media/');

        $this->assertSame(
            'https://example.test/media/blog/hero.jpg',
            ImageUrl::resolve('blog/hero.jpg', $storeManager)
        );
    }

    /**
     * The join must not double the slash. Magento's media base URL carries a
     * trailing one, so a stored path is only ever safe if the resolver strips
     * its own leading slash before concatenating.
     */
    public function testJoinDoesNotProduceADoubleSlash(): void
    {
        $storeManager = $this->storeManagerReturning('https://example.test/media/');

        $this->assertSame(
            'https://example.test/media/blog/hero.jpg',
            ImageUrl::resolve('blog/hero.jpg', $storeManager)
        );
    }

    /**
     * And the mirror case: a media base URL with no trailing slash must still
     * produce exactly one separator.
     */
    public function testJoinAddsASlashWhenTheBaseUrlLacksOne(): void
    {
        $storeManager = $this->storeManagerReturning('https://example.test/media');

        $this->assertSame(
            'https://example.test/media/blog/hero.jpg',
            ImageUrl::resolve('blog/hero.jpg', $storeManager)
        );
    }

    /**
     * An absolute URL is passed through untouched. Posts synced from RequestDesk
     * store a full CDN URL, and prefixing that with the local media base would
     * produce a URL that resolves to nothing.
     */
    public function testAbsoluteHttpsUrlIsReturnedUnchanged(): void
    {
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->expects($this->never())->method('getStore');

        $this->assertSame(
            'https://cdn.example.com/a.jpg',
            ImageUrl::resolve('https://cdn.example.com/a.jpg', $storeManager)
        );
    }

    public function testAbsoluteHttpUrlIsReturnedUnchanged(): void
    {
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->expects($this->never())->method('getStore');

        $this->assertSame(
            'http://cdn.example.com/a.jpg',
            ImageUrl::resolve('http://cdn.example.com/a.jpg', $storeManager)
        );
    }

    /**
     * A root-relative path is already servable and must not be treated as
     * media-relative, or "/pub/media/x.jpg" would come back as
     * ".../media/pub/media/x.jpg".
     */
    public function testRootRelativePathIsReturnedUnchanged(): void
    {
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->expects($this->never())->method('getStore');

        $this->assertSame('/pub/media/x.jpg', ImageUrl::resolve('/pub/media/x.jpg', $storeManager));
    }

    /**
     * Resolution failure degrades to no image rather than taking the page down.
     * This runs in a template, so an exception here would turn a missing image
     * into a 500 on the whole listing.
     */
    public function testStoreManagerFailureDegradesToEmptyString(): void
    {
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willThrowException(new \RuntimeException('no store'));

        $this->assertSame('', ImageUrl::resolve('blog/hero.jpg', $storeManager));
    }
}
