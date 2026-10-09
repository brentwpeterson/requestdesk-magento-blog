<?php
/**
 * Copyright © RequestDesk. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace RequestDesk\Blog\Test\Unit\Block\Head;

use Magento\Framework\App\Request\Http;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Template\Context;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RequestDesk\Blog\Api\Data\PostInterface;
use RequestDesk\Blog\Api\PostRepositoryInterface;
use RequestDesk\Blog\Block\Head\Canonical;
use RequestDesk\Blog\Model\Config;

/**
 * The canonical link names one address for each blog page. Before 1.14.0 the
 * blog printed none, so a store moving off a blog that did print one lost it
 * on every page.
 */
class CanonicalTest extends TestCase
{
    /** @var Http&MockObject */
    private Http $request;

    /** @var PostRepositoryInterface&MockObject */
    private PostRepositoryInterface $postRepository;

    /** @var AdapterInterface&MockObject */
    private AdapterInterface $connection;

    private Canonical $block;

    protected function setUp(): void
    {
        $this->request = $this->createMock(Http::class);
        $this->postRepository = $this->createMock(PostRepositoryInterface::class);

        $select = $this->createMock(Select::class);
        foreach (['from', 'where', 'limit'] as $method) {
            $select->method($method)->willReturnSelf();
        }
        $this->connection = $this->createMock(AdapterInterface::class);
        $this->connection->method('select')->willReturn($select);
        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($this->connection);
        $resource->method('getTableName')->willReturnArgument(0);

        $urlBuilder = $this->createMock(UrlInterface::class);
        $urlBuilder->method('getUrl')->willReturnCallback(
            static fn (string $route, array $params) => 'https://example.test/' . $params['_direct']
        );

        $config = $this->createMock(Config::class);
        $config->method('getUrlPrefix')->willReturn('commerce-co-op');
        $config->method('getUrlSuffix')->willReturn('.html');

        $context = $this->createMock(Context::class);
        $context->method('getRequest')->willReturn($this->request);
        $context->method('getUrlBuilder')->willReturn($urlBuilder);
        $context->method('getLogger')->willReturn($this->createMock(\Psr\Log\LoggerInterface::class));

        $this->block = (new ObjectManager($this))->getObject(Canonical::class, [
            'context' => $context,
            'postRepository' => $this->postRepository,
            'resource' => $resource,
            'config' => $config,
        ]);
    }

    private function requestIs(string $controller, array $params = []): void
    {
        $this->request->method('getControllerName')->willReturn($controller);
        $this->request->method('getParam')->willReturnCallback(
            static fn (string $name, $default = null) => $params[$name] ?? $default
        );
    }

    private function post(int $status): PostInterface
    {
        $post = $this->createMock(PostInterface::class);
        $post->method('getPostId')->willReturn(218);
        $post->method('getUrlKey')->willReturn('metal-mafia-30-percent-more-daily-orders');
        $post->method('getStatus')->willReturn($status);

        return $post;
    }

    public function testAPostPointsAtItsSuffixedAddressEvenWhenReachedByTheIdForm(): void
    {
        $this->requestIs('post', ['id' => '218']);
        $this->postRepository->method('getById')->with(218)->willReturn($this->post(PostInterface::STATUS_PUBLISHED));

        $this->assertSame(
            'https://example.test/commerce-co-op/metal-mafia-30-percent-more-daily-orders.html',
            $this->block->getCanonicalUrl()
        );
    }

    public function testADraftGetsNoCanonical(): void
    {
        $this->requestIs('post', ['id' => '218']);
        $this->postRepository->method('getById')->willReturn($this->post(PostInterface::STATUS_DRAFT));

        $this->assertSame('', $this->block->getCanonicalUrl());
    }

    public function testTheIndexPointsAtItselfWithoutAPageParameterOnPageOne(): void
    {
        $this->requestIs('index');

        $this->assertSame('https://example.test/commerce-co-op.html', $this->block->getCanonicalUrl());
    }

    public function testLaterIndexPagesAreTheirOwnCanonical(): void
    {
        $this->requestIs('index', ['p' => '2']);

        $this->assertSame('https://example.test/commerce-co-op.html?p=2', $this->block->getCanonicalUrl());
    }

    public function testAnAuthorPageUsesTheAuthorsUrlKey(): void
    {
        $this->requestIs('author', ['id' => '7']);
        $this->connection->method('fetchOne')->willReturn('Noah-Oken-Berg');

        $this->assertSame(
            'https://example.test/commerce-co-op/author/Noah-Oken-Berg.html',
            $this->block->getCanonicalUrl()
        );
    }

    public function testAnArchiveWithoutAUrlKeyFallsBackToTheIdForm(): void
    {
        $this->requestIs('tag', ['id' => '3']);
        $this->connection->method('fetchOne')->willReturn(false);

        $this->assertSame('https://example.test/commerce-co-op/tag/view/id/3/', $this->block->getCanonicalUrl());
    }

    public function testAnUnknownControllerGetsNoCanonical(): void
    {
        $this->requestIs('search', ['id' => '1']);

        $this->assertSame('', $this->block->getCanonicalUrl());
    }
}
