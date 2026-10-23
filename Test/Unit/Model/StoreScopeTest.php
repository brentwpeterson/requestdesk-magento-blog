<?php
/**
 * Copyright © RequestDesk. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace RequestDesk\Blog\Test\Unit\Model;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Forward;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\View\Result\PageFactory;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use RequestDesk\Blog\Api\PostRepositoryInterface;
use RequestDesk\Blog\Controller\Post\View;
use RequestDesk\Blog\Model\ListingPageRange;
use RequestDesk\Blog\Model\Post;
use RequestDesk\Blog\Model\StorefrontGate;
use RequestDesk\Blog\Model\StoreScope;

/**
 * A store view shows its own posts and the all-store-views posts, nothing else.
 *
 * With Evrig and CommerceKing on one install, a CommerceKing post opened on
 * evrig.com by its id or key rendered as an Evrig page.
 */
class StoreScopeTest extends TestCase
{
    private function scope(int $currentStoreId): StoreScope
    {
        $store = $this->createMock(Store::class);
        $store->method('getId')->willReturn($currentStoreId);
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        return new StoreScope($storeManager);
    }

    public function testVisibleStoresAreAllStoreViewsAndTheCurrentOne(): void
    {
        $this->assertSame([0, 3], $this->scope(3)->getVisibleStoreIds());
    }

    public function testPostOnAnotherStoreViewIsNotVisible(): void
    {
        $scope = $this->scope(3);

        $this->assertTrue($scope->isVisible(0));
        $this->assertTrue($scope->isVisible(3));
        $this->assertFalse($scope->isVisible(1));
    }

    public function testPostOfAnotherStoreViewOpensAsNotFound(): void
    {
        $post = $this->createMock(Post::class);
        $post->method('getStoreId')->willReturn(1);
        $post->method('getStatus')->willReturn(1);

        $repository = $this->createMock(PostRepositoryInterface::class);
        $repository->method('getById')->willReturn($post);

        $request = $this->createMock(RequestInterface::class);
        $request->method('getParam')->willReturn('12');

        $gate = $this->createMock(StorefrontGate::class);
        $gate->method('allows')->willReturn(true);

        $notFound = $this->createMock(Forward::class);
        $range = $this->createMock(ListingPageRange::class);
        $range->expects($this->once())->method('notFound')->willReturn($notFound);

        $pageFactory = $this->createMock(PageFactory::class);
        $pageFactory->expects($this->never())->method('create');

        $controller = new View(
            $pageFactory,
            $this->createMock(ForwardFactory::class),
            $request,
            $gate,
            $repository,
            $range,
            $this->scope(3)
        );

        $this->assertSame($notFound, $controller->execute());
    }
}
