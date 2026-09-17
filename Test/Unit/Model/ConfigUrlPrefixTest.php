<?php
/**
 * Copyright © RequestDesk. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace RequestDesk\Blog\Test\Unit\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RequestDesk\Blog\Model\Config;

/**
 * Reading Enable Blog and Blog URL Prefix. Both fields sat in the admin for
 * several releases with nothing reading them.
 */
class ConfigUrlPrefixTest extends TestCase
{
    /** @var ScopeConfigInterface&MockObject */
    private ScopeConfigInterface $scopeConfig;

    /** @var LoggerInterface&MockObject */
    private LoggerInterface $logger;

    private Config $config;

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->config = new Config($this->scopeConfig, $this->logger);
    }

    private function prefixIs(?string $value): void
    {
        $this->scopeConfig->method('getValue')
            ->with(Config::XML_PATH_URL_PREFIX)
            ->willReturn($value);
    }

    /**
     * The admin note has always said "leave empty for /blog".
     */
    public function testEmptyPrefixMeansBlog(): void
    {
        $this->prefixIs(null);
        $this->logger->expects($this->never())->method('error');

        $this->assertSame('blog', $this->config->getUrlPrefix());
    }

    public function testPrefixIsNormalized(): void
    {
        $this->prefixIs(' /News/ ');

        $this->assertSame('news', $this->config->getUrlPrefix());
    }

    /**
     * config:set skips the admin form's validation. A prefix with a slash can
     * never be routed, so serving it would take the whole blog down; it is
     * logged and the blog stays on /blog.
     */
    public function testMalformedPrefixIsLoggedAndTheBlogStaysOnBlog(): void
    {
        $this->prefixIs('news/2026');
        $this->logger->expects($this->once())->method('error');

        $this->assertSame('blog', $this->config->getUrlPrefix());
    }

    public function testEnableBlogIsReadPerStore(): void
    {
        $this->scopeConfig->expects($this->once())
            ->method('isSetFlag')
            ->with(Config::XML_PATH_ENABLED, 'store', 3)
            ->willReturn(false);

        $this->assertFalse($this->config->isBlogEnabled(3));
    }
}
