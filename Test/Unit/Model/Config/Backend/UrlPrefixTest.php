<?php
/**
 * Copyright © RequestDesk. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace RequestDesk\Blog\Test\Unit\Model\Config\Backend;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Route\ConfigInterface as RouteConfigInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use PHPUnit\Framework\TestCase;
use RequestDesk\Blog\Model\Config\Backend\UrlPrefix;

/**
 * Saving Blog URL Prefix in the admin. The two values that would leave the blog
 * unreachable are refused with a message instead of being saved.
 */
class UrlPrefixTest extends TestCase
{
    /**
     * @param string[] $frontNameOwners modules the route config reports for the prefix
     */
    private function field(string $value, array $frontNameOwners = []): UrlPrefix
    {
        $context = $this->createMock(Context::class);
        $context->method('getEventDispatcher')->willReturn($this->createMock(ManagerInterface::class));

        $routeConfig = $this->createMock(RouteConfigInterface::class);
        $routeConfig->method('getModulesByFrontName')->willReturn($frontNameOwners);

        $field = new UrlPrefix(
            $context,
            $this->createMock(Registry::class),
            $this->createMock(ScopeConfigInterface::class),
            $this->createMock(TypeListInterface::class),
            $routeConfig
        );
        $field->setValue($value);

        return $field;
    }

    public function testValueIsStoredNormalized(): void
    {
        $field = $this->field(' /News/ ');
        $field->beforeSave();

        $this->assertSame('news', $field->getValue());
    }

    public function testEmptyIsAllowed(): void
    {
        $field = $this->field('');
        $field->beforeSave();

        $this->assertSame('', $field->getValue());
    }

    /**
     * The blog's own front name belongs to this module and is always allowed.
     */
    public function testBlogIsAllowed(): void
    {
        $field = $this->field('blog', ['RequestDesk_Blog']);
        $field->beforeSave();

        $this->assertSame('blog', $field->getValue());
    }

    public function testMoreThanOneSegmentIsRefused(): void
    {
        $this->expectException(LocalizedException::class);

        $this->field('news/2026')->beforeSave();
    }

    public function testSpacesAreRefused(): void
    {
        $this->expectException(LocalizedException::class);

        $this->field('our news')->beforeSave();
    }

    /**
     * The standard router hands /checkout to Magento_Checkout before the blog's
     * router runs, so a blog there could never be reached.
     */
    public function testAnotherModulesFrontNameIsRefused(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Magento_Checkout');

        $this->field('checkout', ['Magento_Checkout'])->beforeSave();
    }
}
