<?php
/**
 * Copyright © RequestDesk. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace RequestDesk\Blog\Test\Unit\Block;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RequestDesk\Blog\Block\PostList;
use RequestDesk\Blog\Model\Config;

/**
 * Posts per page, wired up in 1.6.6.
 *
 * The admin field and its config.xml default of 10 both existed from the start,
 * but nothing on the frontend read either of them, so the listing returned every
 * published post no matter what the setting said. Jeel reported it. The tests
 * below cover the reading of that setting and, more importantly, what happens
 * when it is missing or nonsense - because the tempting fallback, "no limit", is
 * a full table scan on a blog that has been running for a few years.
 *
 * The reading itself now lives in Model\Config, which the block asks through
 * resolvePageSize(). A real Config over a mocked ScopeConfigInterface is used
 * here on purpose: mocking Config instead would assert only that the block
 * calls it, and the fallbacks below are the part worth protecting.
 */
class PostListPerPageTest extends TestCase
{
    /** @var ScopeConfigInterface&MockObject */
    private ScopeConfigInterface $scopeConfig;

    /** @var RequestInterface&MockObject */
    private RequestInterface $request;

    private PostList $block;

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->request = $this->createMock(RequestInterface::class);

        // No ?limit= in play, so the configured value is what decides.
        $this->request->method('getParam')->willReturn(null);

        $context = $this->createMock(Context::class);
        $context->method('getScopeConfig')->willReturn($this->scopeConfig);
        $context->method('getRequest')->willReturn($this->request);

        $this->block = (new ObjectManager($this))->getObject(
            PostList::class,
            [
                'context' => $context,
                'config' => new Config($this->scopeConfig, $this->createMock(\Psr\Log\LoggerInterface::class)),
            ]
        );
    }

    private function configuredValue($value): void
    {
        $this->scopeConfig->method('getValue')
            ->with(Config::XML_PATH_POSTS_PER_PAGE, ScopeInterface::SCOPE_STORE, null)
            ->willReturn($value);
    }

    public function testReadsTheConfiguredValue(): void
    {
        $this->configuredValue('25');

        $this->assertSame(25, $this->block->getPostsPerPage());
    }

    /**
     * The field is a free-text input, so it is stored as a string and can be
     * saved empty. Empty must not mean unlimited.
     */
    public function testEmptyStringFallsBackToTen(): void
    {
        $this->configuredValue('');

        $this->assertSame(10, $this->block->getPostsPerPage());
    }

    public function testNullFallsBackToTen(): void
    {
        $this->configuredValue(null);

        $this->assertSame(10, $this->block->getPostsPerPage());
    }

    public function testZeroFallsBackToTen(): void
    {
        $this->configuredValue('0');

        $this->assertSame(10, $this->block->getPostsPerPage());
    }

    public function testNegativeFallsBackToTen(): void
    {
        $this->configuredValue('-5');

        $this->assertSame(10, $this->block->getPostsPerPage());
    }

    public function testNonNumericFallsBackToTen(): void
    {
        $this->configuredValue('abc');

        $this->assertSame(10, $this->block->getPostsPerPage());
    }

    public function testValueIsReadAtStoreScope(): void
    {
        $this->scopeConfig->expects($this->once())
            ->method('getValue')
            ->with(Config::XML_PATH_POSTS_PER_PAGE, ScopeInterface::SCOPE_STORE, null)
            ->willReturn('5');

        $this->assertSame(5, $this->block->getPostsPerPage());
    }
}
