<?php
/**
 * Copyright © RequestDesk. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace RequestDesk\Blog\Test\Unit\Block;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RequestDesk\Blog\Block\PostList;

/**
 * Posts per page, wired up in 1.6.6.
 *
 * The admin field and its config.xml default of 10 both existed from the start,
 * but nothing on the frontend read either of them, so the listing returned every
 * published post no matter what the setting said. Jeel reported it. The tests
 * below cover the reading of that setting and, more importantly, what happens
 * when it is missing or nonsense - because the tempting fallback, "no limit", is
 * a full table scan on a blog that has been running for a few years.
 */
class PostListPerPageTest extends TestCase
{
    /** @var ScopeConfigInterface&MockObject */
    private ScopeConfigInterface $scopeConfig;

    private PostList $block;

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);

        $context = $this->createMock(Context::class);
        $context->method('getScopeConfig')->willReturn($this->scopeConfig);

        $this->block = (new ObjectManager($this))->getObject(
            PostList::class,
            ['context' => $context]
        );
    }

    private function configuredValue($value): void
    {
        $this->scopeConfig->method('getValue')
            ->with('requestdesk_blog/general/posts_per_page', ScopeInterface::SCOPE_STORE)
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

    /**
     * A negative page size is not merely wrong, it is the value most likely to
     * be interpreted as "everything" further down the stack.
     */
    public function testNegativeValueFallsBackToTen(): void
    {
        $this->configuredValue('-5');

        $this->assertSame(10, $this->block->getPostsPerPage());
    }

    public function testNonNumericValueFallsBackToTen(): void
    {
        $this->configuredValue('abc');

        $this->assertSame(10, $this->block->getPostsPerPage());
    }

    public function testValueIsReadAtStoreScope(): void
    {
        $this->scopeConfig->expects($this->once())
            ->method('getValue')
            ->with('requestdesk_blog/general/posts_per_page', ScopeInterface::SCOPE_STORE)
            ->willReturn('5');

        $this->assertSame(5, $this->block->getPostsPerPage());
    }
}
