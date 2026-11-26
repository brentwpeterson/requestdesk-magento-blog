<?php
/**
 * Copyright © RequestDesk. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace RequestDesk\Blog\Test\Unit\Model;

use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\ReadInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RequestDesk\Blog\Model\ImagePreview;

/**
 * Magento's image preview prints file.size through formatSize(), and a missing
 * size prints "NaN undefined". The post and author forms sent no size, so every
 * saved image showed that (Evrig, 2026-11-26).
 */
class ImagePreviewTest extends TestCase
{
    /** @var ReadInterface&MockObject */
    private ReadInterface $media;

    private ImagePreview $preview;

    protected function setUp(): void
    {
        $store = $this->createMock(Store::class);
        $store->method('getBaseUrl')->with(UrlInterface::URL_TYPE_MEDIA)->willReturn('https://shop.test/media/');
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $this->media = $this->createMock(ReadInterface::class);
        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->method('getDirectoryRead')->willReturn($this->media);

        $this->preview = new ImagePreview($storeManager, $filesystem);
    }

    public function testMediaFileCarriesItsSize(): void
    {
        $this->media->method('isFile')->with('blog/hero.jpg')->willReturn(true);
        $this->media->method('stat')->with('blog/hero.jpg')->willReturn(['size' => 48213]);

        $this->assertSame(
            [['name' => 'hero.jpg', 'url' => 'https://shop.test/media/blog/hero.jpg', 'size' => 48213]],
            $this->preview->forUploader('blog/hero.jpg')
        );
    }

    public function testRootRelativeMediaPathIsSizedFromTheMediaDirectory(): void
    {
        $this->media->method('isFile')->with('blog/hero.jpg')->willReturn(true);
        $this->media->method('stat')->willReturn(['size' => 10]);

        $this->assertSame(10, $this->preview->forUploader('/media/blog/hero.jpg')[0]['size']);
    }

    public function testRemoteImageHasNoSizeAndIsNotLookedUp(): void
    {
        $this->media->expects($this->never())->method('isFile');
        $url = 'https://cb-content-storage.s3.us-east-1.amazonaws.com/blog-images/a.png';

        $file = $this->preview->forUploader($url)[0];

        $this->assertSame($url, $file['url']);
        $this->assertArrayNotHasKey('size', $file);
    }

    public function testMissingMediaFileHasNoSize(): void
    {
        $this->media->method('isFile')->willReturn(false);

        $this->assertArrayNotHasKey('size', $this->preview->forUploader('blog/gone.jpg')[0]);
    }

    public function testEmptyPathHasNoFile(): void
    {
        $this->assertSame([], $this->preview->forUploader('  '));
    }

    public function testPreviewTemplatePrintsSizeOnlyWhenKnown(): void
    {
        $template = (string) file_get_contents(
            __DIR__ . '/../../../view/adminhtml/web/template/form/element/image-preview.html'
        );

        $this->assertMatchesRegularExpression(
            '#<if args="\$file\.size">.*formatSize\(\$file\.size\).*</if>#s',
            $template
        );
        foreach (['post', 'author'] as $form) {
            $xml = (string) file_get_contents(
                __DIR__ . "/../../../view/adminhtml/ui_component/requestdesk_blog_{$form}_form.xml"
            );
            $this->assertStringContainsString('RequestDesk_Blog/form/element/image-preview', $xml);
            $this->assertStringNotContainsString('Magento_Catalog/image-preview', $xml);
        }
    }
}
