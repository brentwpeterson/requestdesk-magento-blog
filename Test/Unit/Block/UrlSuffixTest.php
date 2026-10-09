<?php
/**
 * Copyright © RequestDesk. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace RequestDesk\Blog\Test\Unit\Block;

use Magento\Framework\UrlInterface;
use PHPUnit\Framework\TestCase;
use RequestDesk\Blog\Block\ArchiveUrl;
use RequestDesk\Blog\Block\BlogUrl;
use RequestDesk\Blog\Block\PostUrl;

/**
 * The URL suffix on every address the module writes: posts, archives and the
 * index take it, controller paths and id-form fallbacks do not.
 */
class UrlSuffixTest extends TestCase
{
    private function urlBuilder(): UrlInterface
    {
        $urlBuilder = $this->createMock(UrlInterface::class);
        $urlBuilder->method('getUrl')->willReturnCallback(
            static fn (string $route, array $params) => 'https://example.test/' . $params['_direct']
        );

        return $urlBuilder;
    }

    public function testThePostAddressTakesTheSuffix(): void
    {
        $this->assertSame(
            'https://example.test/commerce-co-op/episode-10-arron-moss.html',
            PostUrl::fromKey(220, 'episode-10-arron-moss', $this->urlBuilder(), 'commerce-co-op', '.html')
        );
    }

    public function testThePostAddressIsUnchangedWithoutASuffix(): void
    {
        $this->assertSame(
            'https://example.test/blog/my-post',
            PostUrl::fromKey(5, 'my-post', $this->urlBuilder(), 'blog')
        );
    }

    public function testAPostWithoutAUrlKeyKeepsTheIdFormAndNoSuffix(): void
    {
        $this->assertSame(
            'https://example.test/blog/post/view/id/5/',
            PostUrl::fromKey(5, '', $this->urlBuilder(), 'blog', '.html')
        );
    }

    public function testArchiveAddressesTakeTheSuffix(): void
    {
        $this->assertSame(
            'https://example.test/commerce-co-op/author/Noah-Oken-Berg.html',
            ArchiveUrl::resolve(ArchiveUrl::TYPE_AUTHOR, 7, 'Noah-Oken-Berg', $this->urlBuilder(), 'commerce-co-op', '.html')
        );
    }

    public function testAnArchiveWithoutAUrlKeyKeepsTheIdFormAndNoSuffix(): void
    {
        $this->assertSame(
            'https://example.test/blog/tag/view/id/3/',
            ArchiveUrl::resolve(ArchiveUrl::TYPE_TAG, 3, null, $this->urlBuilder(), 'blog', '.html')
        );
    }

    public function testTheIndexTakesTheSuffixInPlaceOfTheTrailingSlash(): void
    {
        $this->assertSame(
            'https://example.test/commerce-co-op.html',
            BlogUrl::resolve('commerce-co-op', '', [], $this->urlBuilder(), '.html')
        );
    }

    public function testThePagerKeepsItsQueryAfterTheSuffix(): void
    {
        $this->assertSame(
            'https://example.test/commerce-co-op.html?p=2',
            BlogUrl::resolve('commerce-co-op', '', ['p' => 2], $this->urlBuilder(), '.html')
        );
    }

    /**
     * comment/save and search are not pages to index, and a suffix on them
     * would break the form action and the search box.
     */
    public function testControllerPathsNeverTakeTheSuffix(): void
    {
        $this->assertSame(
            'https://example.test/commerce-co-op/comment/save/',
            BlogUrl::resolve('commerce-co-op', 'comment/save', [], $this->urlBuilder(), '.html')
        );
    }
}
