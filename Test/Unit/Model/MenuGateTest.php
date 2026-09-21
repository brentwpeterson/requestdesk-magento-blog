<?php
/**
 * Copyright © RequestDesk. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace RequestDesk\Blog\Test\Unit\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\UrlInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RequestDesk\Blog\Model\Config;
use RequestDesk\Blog\Model\MenuGate;

/**
 * Whether the blog's menu entry shows, and which entry is the blog's.
 *
 * The two-settings rule is cheap to state and easy to get backwards, and the
 * URL matching is where a real menu will differ from a tidy expectation: a
 * category URL arrives with Magento's .html suffix on it, an entry may point
 * at a page underneath the blog rather than at the listing, and the address
 * moves when Blog URL Prefix does. The end-to-end behaviour on Luma, Hyva and
 * the Evrig theme was checked against the local Evrig copy, since that renders
 * two menus this cannot reach from a unit test.
 */
class MenuGateTest extends TestCase
{
    /** @var ScopeConfigInterface&MockObject */
    private ScopeConfigInterface $scopeConfig;

    /** @var UrlInterface&MockObject */
    private UrlInterface $urlBuilder;

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->urlBuilder = $this->createMock(UrlInterface::class);
    }

    /**
     * @param bool $enabled
     * @param bool $includeInMenu
     * @param string $prefix
     * @param string $suffix
     * @return MenuGate
     */
    private function gate(
        bool $enabled,
        bool $includeInMenu,
        string $prefix = 'blog',
        string $suffix = '.html'
    ): MenuGate {
        $this->scopeConfig->method('isSetFlag')->willReturnCallback(
            static fn (string $path) => match ($path) {
                Config::XML_PATH_ENABLED => $enabled,
                Config::XML_PATH_INCLUDE_IN_MENU => $includeInMenu,
                default => false,
            }
        );

        $this->scopeConfig->method('getValue')->willReturnCallback(
            static fn (string $path) => match ($path) {
                Config::XML_PATH_URL_PREFIX => $prefix,
                'catalog/seo/category_url_suffix' => $suffix,
                default => null,
            }
        );

        $this->urlBuilder->method('getUrl')->willReturnCallback(
            static fn ($route, $params) => 'https://example.test/' . ($params['_direct'] ?? '')
        );

        $config = new Config($this->scopeConfig, $this->createMock(\Psr\Log\LoggerInterface::class));

        return new MenuGate($config, $this->scopeConfig, $this->urlBuilder);
    }

    public function testBothSettingsOnShowsTheEntry(): void
    {
        $this->assertTrue($this->gate(true, true)->showsBlogEntry());
    }

    public function testIncludeInMenuOffHidesTheEntry(): void
    {
        $this->assertFalse($this->gate(true, false)->showsBlogEntry());
    }

    /**
     * The case Jeel reported: the blog is switched off, so its pages 404, and
     * the menu entry has to go with them rather than advertising a dead page.
     */
    public function testBlogDisabledHidesTheEntryEvenWhenIncludeInMenuIsOn(): void
    {
        $this->assertFalse($this->gate(false, true)->showsBlogEntry());
    }

    public function testBothOffHidesTheEntry(): void
    {
        $this->assertFalse($this->gate(false, false)->showsBlogEntry());
    }

    public function testMatchesTheListingUrl(): void
    {
        $this->assertTrue($this->gate(true, true)->isBlogUrl('https://example.test/blog'));
    }

    public function testMatchesTheListingUrlWithATrailingSlash(): void
    {
        $this->assertTrue($this->gate(true, true)->isBlogUrl('https://example.test/blog/'));
    }

    /**
     * A category at the store root comes out as /blog.html on a default store,
     * because Magento appends the category URL suffix.
     */
    public function testMatchesACategoryUrlCarryingTheCategorySuffix(): void
    {
        $this->assertTrue($this->gate(true, true)->isBlogUrl('https://example.test/blog.html'));
    }

    public function testMatchesAPageUnderneathTheBlog(): void
    {
        $this->assertTrue($this->gate(true, true)->isBlogUrl('https://example.test/blog/category/news'));
    }

    public function testDoesNotMatchAnUnrelatedEntry(): void
    {
        $this->assertFalse($this->gate(true, true)->isBlogUrl('https://example.test/careers'));
    }

    /**
     * Prefix matching is on whole segments. "blogger" starts with "blog" as a
     * string but is a different page.
     */
    public function testDoesNotMatchASegmentThatMerelyStartsWithThePrefix(): void
    {
        $this->assertFalse($this->gate(true, true)->isBlogUrl('https://example.test/blogger'));
    }

    /**
     * Move the blog with Blog URL Prefix and the entry that disappears moves
     * with it, rather than /blog staying hardcoded here.
     */
    public function testFollowsAChangedUrlPrefix(): void
    {
        $gate = $this->gate(true, true, 'news');

        $this->assertTrue($gate->isBlogUrl('https://example.test/news'));
        $this->assertFalse($gate->isBlogUrl('https://example.test/blog'));
    }

    public function testMatchesWithNoCategoryUrlSuffixConfigured(): void
    {
        $this->assertTrue($this->gate(true, true, 'blog', '')->isBlogUrl('https://example.test/blog'));
    }

    public function testIgnoresAQueryString(): void
    {
        $this->assertTrue($this->gate(true, true)->isBlogUrl('https://example.test/blog?p=2'));
    }

    public function testEmptyUrlIsNotTheBlog(): void
    {
        $this->assertFalse($this->gate(true, true)->isBlogUrl(''));
    }
}
