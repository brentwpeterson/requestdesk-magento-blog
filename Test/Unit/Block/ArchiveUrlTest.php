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

/**
 * Posts moved to /blog/<url-key> in 1.9.3 and the three archives were left
 * behind, so every category, tag and author link on the site still carried a
 * primary key: /blog/category/view/id/77. Four separate call sites built those
 * URLs by hand, which is how they drifted apart in the first place.
 *
 * The id form has to survive as the fallback - a tag imported before url_key
 * existed has none, and a link that renders nowhere is worse than an ugly one -
 * so both shapes are pinned here, along with the empty and whitespace cases
 * that decide between them.
 */
class ArchiveUrlTest extends TestCase
{
    /**
     * @return UrlInterface&\PHPUnit\Framework\MockObject\MockObject
     */
    private function urlBuilder()
    {
        return $this->createMock(UrlInterface::class);
    }

    public function testUrlKeyResolvesToThePrettyForm(): void
    {
        $urlBuilder = $this->urlBuilder();
        $urlBuilder->expects($this->once())
            ->method('getUrl')
            ->with('', ['_direct' => 'blog/category/ecommerce'])
            ->willReturn('https://example.test/blog/category/ecommerce');

        $this->assertSame(
            'https://example.test/blog/category/ecommerce',
            ArchiveUrl::resolve(ArchiveUrl::TYPE_CATEGORY, 77, 'ecommerce', $urlBuilder, 'blog')
        );
    }

    /**
     * The id form goes through _direct as well, under the prefix, with the
     * trailing slash getUrl() wrote for the old route path.
     */
    public function testEmptyUrlKeyFallsBackToTheIdForm(): void
    {
        $urlBuilder = $this->urlBuilder();
        $urlBuilder->expects($this->once())
            ->method('getUrl')
            ->with('', ['_direct' => 'blog/tag/view/id/12/'])
            ->willReturn('https://example.test/blog/tag/view/id/12/');

        $this->assertSame(
            'https://example.test/blog/tag/view/id/12/',
            ArchiveUrl::resolve(ArchiveUrl::TYPE_TAG, 12, '', $urlBuilder, 'blog')
        );
    }

    public function testNullUrlKeyFallsBackToTheIdForm(): void
    {
        $urlBuilder = $this->urlBuilder();
        $urlBuilder->expects($this->once())
            ->method('getUrl')
            ->with('', ['_direct' => 'blog/author/view/id/4/'])
            ->willReturn('https://example.test/blog/author/view/id/4/');

        $this->assertSame(
            'https://example.test/blog/author/view/id/4/',
            ArchiveUrl::resolve(ArchiveUrl::TYPE_AUTHOR, 4, null, $urlBuilder, 'blog')
        );
    }

    /**
     * A url_key of spaces is not a url_key. Without the trim it would produce
     * "blog/author/   ", which routes nowhere and 404s.
     */
    public function testWhitespaceOnlyUrlKeyFallsBackToTheIdForm(): void
    {
        $urlBuilder = $this->urlBuilder();
        $urlBuilder->expects($this->once())
            ->method('getUrl')
            ->with('', ['_direct' => 'blog/author/view/id/9/'])
            ->willReturn('https://example.test/blog/author/view/id/9/');

        $this->assertSame(
            'https://example.test/blog/author/view/id/9/',
            ArchiveUrl::resolve(ArchiveUrl::TYPE_AUTHOR, 9, '   ', $urlBuilder, 'blog')
        );
    }

    /**
     * Each archive type keeps its own segment. A shared resolver that dropped
     * the type would send every link to the same place.
     */
    public function testEachTypeKeepsItsOwnSegment(): void
    {
        foreach (
            [
                ArchiveUrl::TYPE_CATEGORY => 'blog/category/news',
                ArchiveUrl::TYPE_TAG => 'blog/tag/news',
                ArchiveUrl::TYPE_AUTHOR => 'blog/author/news',
            ] as $type => $expectedPath
        ) {
            $urlBuilder = $this->urlBuilder();
            $urlBuilder->expects($this->once())
                ->method('getUrl')
                ->with('', ['_direct' => $expectedPath])
                ->willReturn('https://example.test/' . $expectedPath);

            $this->assertSame(
                'https://example.test/' . $expectedPath,
                ArchiveUrl::resolve($type, 1, 'news', $urlBuilder, 'blog')
            );
        }
    }

    /**
     * A store that moved the blog to /news gets /news links in both forms. A
     * resolver that kept writing /blog would send every visitor to a 404.
     */
    public function testCustomPrefixIsUsedForBothForms(): void
    {
        $urlBuilder = $this->urlBuilder();
        $urlBuilder->expects($this->exactly(2))
            ->method('getUrl')
            ->willReturnCallback(static fn (string $route, array $params) => 'https://example.test/' . $params['_direct']);

        $this->assertSame(
            'https://example.test/news/tag/hyva',
            ArchiveUrl::resolve(ArchiveUrl::TYPE_TAG, 3, 'hyva', $urlBuilder, 'news')
        );
        $this->assertSame(
            'https://example.test/news/tag/view/id/3/',
            ArchiveUrl::resolve(ArchiveUrl::TYPE_TAG, 3, null, $urlBuilder, 'news')
        );
    }
}
