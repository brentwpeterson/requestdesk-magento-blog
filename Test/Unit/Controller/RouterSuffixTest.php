<?php
/**
 * Copyright © RequestDesk. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength.TooLong

namespace RequestDesk\Blog\Test\Unit\Controller;

use Magento\Framework\App\Action\Forward;
use Magento\Framework\App\Action\Redirect;
use Magento\Framework\App\ActionFactory;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\UrlInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RequestDesk\Blog\Controller\Router;
use RequestDesk\Blog\Model\Config;

/**
 * Blog URL Suffix at the router.
 *
 * Magento Association's blog ran on Amasty with every address ending in .html.
 * Moving it to this module must not change an address search engines know, so
 * a store can set the same suffix: the suffixed address answers, and the
 * address without it is redirected with a 301 to the one with it.
 */
class RouterSuffixTest extends TestCase
{
    /** @var Config&MockObject */
    private Config $config;

    /** @var HttpResponse&MockObject */
    private HttpResponse $response;

    /** @var AdapterInterface&MockObject */
    private AdapterInterface $connection;

    /** @var array<string, mixed> what the router wrote onto the request */
    private array $written = [];

    /** @var list<string> action classes the router asked the factory for */
    private array $created = [];

    private Router $router;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->response = $this->createMock(HttpResponse::class);

        $select = $this->createMock(Select::class);
        foreach (['from', 'where', 'limit', 'join', 'order'] as $method) {
            $select->method($method)->willReturnSelf();
        }
        $this->connection = $this->createMock(AdapterInterface::class);
        $this->connection->method('select')->willReturn($select);

        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($this->connection);
        $resource->method('getTableName')->willReturnArgument(0);

        $actionFactory = $this->createMock(ActionFactory::class);
        $actionFactory->method('create')->willReturnCallback(function (string $class) {
            $this->created[] = $class;
            return $this->createMock($class);
        });

        $url = $this->createMock(UrlInterface::class);
        $url->method('getUrl')->willReturnCallback(
            static fn (string $route, array $params): string => 'https://example.test/' . $params['_direct']
        );

        $this->router = new Router($actionFactory, $resource, $this->config, $this->response, $url);
    }

    private function blog(string $prefix, string $suffix): void
    {
        $this->config->method('isBlogEnabled')->willReturn(true);
        $this->config->method('getUrlPrefix')->willReturn($prefix);
        $this->config->method('getUrlSuffix')->willReturn($suffix);
    }

    /**
     * @return Http&MockObject
     */
    private function request(string $pathInfo, bool $isGet = true, string $query = ''): Http
    {
        $request = $this->createMock(Http::class);
        $request->method('getPathInfo')->willReturn($pathInfo);
        $request->method('getModuleName')->willReturn(null);
        $request->method('isGet')->willReturn($isGet);
        $request->method('getServer')->willReturn($query);

        foreach (['setModuleName' => 'module', 'setControllerName' => 'controller', 'setActionName' => 'action'] as $method => $key) {
            $request->method($method)->willReturnCallback(function ($value) use ($request, $key) {
                $this->written[$key] = $value;
                return $request;
            });
        }
        $request->method('setParam')->willReturnCallback(function ($name, $value) use ($request) {
            $this->written['params'][$name] = $value;
            return $request;
        });

        return $request;
    }

    public function testASuffixedPostAddressServesThePost(): void
    {
        $this->blog('commerce-co-op', '.html');
        $this->connection->method('fetchOne')->willReturn('218');
        $this->response->expects($this->never())->method('setRedirect');

        $this->assertNotNull($this->router->match($this->request('/commerce-co-op/metal-mafia-30-percent-more-daily-orders.html')));
        $this->assertSame([Forward::class], $this->created);
        $this->assertSame('post', $this->written['controller']);
        $this->assertSame(['id' => 218], $this->written['params']);
    }

    public function testAPostAddressWithoutTheSuffixIsRedirectedWithA301(): void
    {
        $this->blog('commerce-co-op', '.html');
        $this->connection->method('fetchOne')->willReturn('218');
        $this->response->expects($this->once())->method('setRedirect')
            ->with('https://example.test/commerce-co-op/metal-mafia-30-percent-more-daily-orders.html', 301);

        $this->assertNotNull($this->router->match($this->request('/commerce-co-op/metal-mafia-30-percent-more-daily-orders')));
        $this->assertSame([Redirect::class], $this->created);
    }

    public function testTheSuffixedIndexServesTheListingOnACustomPrefix(): void
    {
        $this->blog('commerce-co-op', '.html');
        $this->response->expects($this->never())->method('setRedirect');

        $this->assertNotNull($this->router->match($this->request('/commerce-co-op.html')));
        $this->assertSame(['module' => 'blog', 'controller' => 'index', 'action' => 'index'], $this->written);
    }

    public function testTheSuffixedIndexServesTheListingOnTheDefaultPrefix(): void
    {
        $this->blog('blog', '.html');

        $this->assertNotNull($this->router->match($this->request('/blog.html')));
        $this->assertSame(['module' => 'blog', 'controller' => 'index', 'action' => 'index'], $this->written);
    }

    public function testTheBareIndexRedirectsAndKeepsTheQueryString(): void
    {
        $this->blog('commerce-co-op', '.html');
        $this->response->expects($this->once())->method('setRedirect')
            ->with('https://example.test/commerce-co-op.html?p=2', 301);

        $this->assertNotNull($this->router->match($this->request('/commerce-co-op', true, 'p=2')));
        $this->assertSame([Redirect::class], $this->created);
    }

    public function testASuffixedArchiveAddressServesTheArchive(): void
    {
        $this->blog('commerce-co-op', '.html');
        $this->connection->method('fetchOne')->willReturn('7');
        $this->response->expects($this->never())->method('setRedirect');

        $this->assertNotNull($this->router->match($this->request('/commerce-co-op/author/Noah-Oken-Berg.html')));
        $this->assertSame('author', $this->written['controller']);
        $this->assertSame('view', $this->written['action']);
        $this->assertSame(['id' => 7], $this->written['params']);
    }

    public function testAnArchiveAddressWithoutTheSuffixIsRedirected(): void
    {
        $this->blog('commerce-co-op', '.html');
        $this->connection->method('fetchOne')->willReturn('7');
        $this->response->expects($this->once())->method('setRedirect')
            ->with('https://example.test/commerce-co-op/tag/magento.html', 301);

        $this->assertNotNull($this->router->match($this->request('/commerce-co-op/tag/magento')));
        $this->assertSame([Redirect::class], $this->created);
    }

    public function testAPostSubmittedWithoutTheSuffixIsNotRedirected(): void
    {
        $this->blog('commerce-co-op', '.html');
        $this->connection->method('fetchOne')->willReturn('218');
        $this->response->expects($this->never())->method('setRedirect');

        $this->assertNotNull($this->router->match($this->request('/commerce-co-op/metal-mafia-30-percent-more-daily-orders', false)));
        $this->assertSame([Forward::class], $this->created);
    }

    public function testControllerPathsAreNeverRedirected(): void
    {
        $this->blog('commerce-co-op', '.html');
        $this->response->expects($this->never())->method('setRedirect');

        $this->assertNotNull($this->router->match($this->request('/commerce-co-op/search')));
        $this->assertSame('search', $this->written['controller']);
    }

    public function testAnUnknownPostIsStillA404EvenWithTheSuffix(): void
    {
        $this->blog('commerce-co-op', '.html');
        $this->connection->method('fetchOne')->willReturn('0');
        $this->response->expects($this->never())->method('setRedirect');

        $this->assertNull($this->router->match($this->request('/commerce-co-op/seans-post.html')));
        $this->assertNull($this->router->match($this->request('/commerce-co-op/seans-post')));
    }

    public function testWithoutASuffixTheDotHtmlAddressStillDoesNotMatch(): void
    {
        $this->blog('commerce-co-op', '');
        $this->connection->method('fetchOne')->willReturn('0');
        $this->response->expects($this->never())->method('setRedirect');

        $this->assertNull($this->router->match($this->request('/commerce-co-op/metal-mafia.html')));
    }
}
