<?php
/**
 * Copyright © RequestDesk. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace RequestDesk\Blog\Test\Unit\Block;

use Magento\Framework\UrlInterface;
use PHPUnit\Framework\TestCase;
use RequestDesk\Blog\Block\BlogUrl;

/**
 * getUrl('blog/...') writes the route's front name whatever the store's prefix
 * is, which is why every blog address now goes through _direct. With the
 * default prefix the output has to match what getUrl() wrote, so links, the
 * pager and cached pages do not change shape on upgrade.
 */
class BlogUrlTest extends TestCase
{
    private function urlBuilder(): UrlInterface
    {
        $urlBuilder = $this->createMock(UrlInterface::class);
        $urlBuilder->method('getUrl')->willReturnCallback(
            static fn (string $route, array $params) => 'https://example.test/' . $params['_direct']
        );

        return $urlBuilder;
    }

    public function testIndexKeepsTheTrailingSlash(): void
    {
        $this->assertSame('https://example.test/blog/', BlogUrl::resolve('blog', '', [], $this->urlBuilder()));
    }

    public function testPathAndQueryUnderACustomPrefix(): void
    {
        $this->assertSame(
            'https://example.test/news/category/view/id/77/?p=2&limit=25',
            BlogUrl::resolve('news', 'category/view/id/77', ['p' => 2, 'limit' => 25], $this->urlBuilder())
        );
    }

    /**
     * Page 1 passes p => null so the canonical first page carries no parameter.
     */
    public function testNullQueryValuesAreDropped(): void
    {
        $this->assertSame(
            'https://example.test/news/',
            BlogUrl::resolve('news', '', ['p' => null], $this->urlBuilder())
        );
    }
}
