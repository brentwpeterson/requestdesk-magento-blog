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
 * Reading Blog URL Suffix.
 *
 * Amasty Blog Pro ends every address in .html. A store migrating off it sets the
 * same suffix here so no indexed address changes.
 */
class ConfigUrlSuffixTest extends TestCase
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

    private function suffixIs(?string $value): void
    {
        $this->scopeConfig->method('getValue')->with(Config::XML_PATH_URL_SUFFIX)->willReturn($value);
    }

    public function testNothingConfiguredMeansNoSuffix(): void
    {
        $this->suffixIs(null);
        $this->logger->expects($this->never())->method('error');

        $this->assertSame('', $this->config->getUrlSuffix());
    }

    public function testDotHtmlIsKept(): void
    {
        $this->suffixIs('.html');

        $this->assertSame('.html', $this->config->getUrlSuffix());
    }

    public function testTheLeadingDotIsAddedAndCaseIsFolded(): void
    {
        $this->suffixIs(' HTML ');

        $this->assertSame('.html', $this->config->getUrlSuffix());
    }

    /**
     * config:set skips any form validation. A suffix with a slash or a space
     * can never be routed, so it is logged and ignored rather than served.
     *
     * @dataProvider malformedSuffixes
     */
    public function testMalformedSuffixIsLoggedAndIgnored(string $value): void
    {
        $this->suffixIs($value);
        $this->logger->expects($this->once())->method('error');

        $this->assertSame('', $this->config->getUrlSuffix());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function malformedSuffixes(): array
    {
        return [
            'slash' => ['/index.html'],
            'space' => ['.ht ml'],
            'second dot' => ['.tar.gz'],
            'too long' => ['.abcdefghijkl'],
            'punctuation' => ['.h-tml'],
        ];
    }
}
