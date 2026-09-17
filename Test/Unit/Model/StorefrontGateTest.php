<?php
/**
 * Copyright © RequestDesk. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace RequestDesk\Blog\Test\Unit\Model;

use Magento\Framework\App\Request\Http;
use PHPUnit\Framework\TestCase;
use RequestDesk\Blog\Model\Config;
use RequestDesk\Blog\Model\StorefrontGate;

/**
 * The check every storefront blog controller runs first.
 */
class StorefrontGateTest extends TestCase
{
    private function gate(bool $enabled, string $prefix): StorefrontGate
    {
        $config = $this->createMock(Config::class);
        $config->method('isBlogEnabled')->willReturn($enabled);
        $config->method('getUrlPrefix')->willReturn($prefix);

        return new StorefrontGate($config);
    }

    private function request(string $pathInfo): Http
    {
        $request = $this->createMock(Http::class);
        $request->method('getPathInfo')->willReturn($pathInfo);

        return $request;
    }

    /**
     * Enable Blog set to No closes every address, on any prefix.
     */
    public function testSwitchedOffBlogAllowsNothing(): void
    {
        $this->assertFalse($this->gate(false, 'blog')->allows($this->request('/blog')));
        $this->assertFalse($this->gate(false, 'news')->allows($this->request('/news/post/view/id/5')));
    }

    /**
     * The default prefix does not look at the path, so a store whose Default
     * Web URL is blog/index/index keeps its home page.
     */
    public function testDefaultPrefixAllowsAnyPath(): void
    {
        $this->assertTrue($this->gate(true, 'blog')->allows($this->request('/blog/post/view/id/5')));
        $this->assertTrue($this->gate(true, 'blog')->allows($this->request('/')));
    }

    public function testCustomPrefixAllowsItsOwnAddresses(): void
    {
        $this->assertTrue($this->gate(true, 'news')->allows($this->request('/news')));
        $this->assertTrue($this->gate(true, 'news')->allows($this->request('/news/category/view/id/77/')));
    }

    /**
     * The standard router still reaches the controllers under /blog on a store
     * that moved to /news. Without this the same pages answer at two addresses.
     */
    public function testCustomPrefixClosesTheOldBlogAddresses(): void
    {
        $this->assertFalse($this->gate(true, 'news')->allows($this->request('/blog')));
        $this->assertFalse($this->gate(true, 'news')->allows($this->request('/blog/post/view/id/5')));
    }
}
