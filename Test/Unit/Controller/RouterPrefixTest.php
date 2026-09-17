<?php
/**
 * Copyright © RequestDesk. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace RequestDesk\Blog\Test\Unit\Controller;

use Magento\Framework\App\Action\Forward;
use Magento\Framework\App\ActionFactory;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RequestDesk\Blog\Controller\Router;
use RequestDesk\Blog\Model\Config;

/**
 * Blog URL Prefix and Enable Blog at the router.
 *
 * Before 1.10.3 the router matched the literal "blog" and read neither setting,
 * so a store that set the prefix to "news" got /news 404s while /blog carried on.
 * On a custom prefix the standard router cannot reach the blog's controllers at
 * all, since it only knows the front name "blog", so this router maps the
 * controller paths too. It maps only the pairs the module has: a forward to an
 * action that does not exist lands back in this router, and Magento ends that
 * loop after 100 passes with an exception instead of a 404.
 */
class RouterPrefixTest extends TestCase
{
    /** @var Config&MockObject */
    private Config $config;

    /** @var AdapterInterface&MockObject */
    private AdapterInterface $connection;

    /** @var array<string, mixed> what the router wrote onto the request */
    private array $written = [];

    private Router $router;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);

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
        $actionFactory->method('create')->willReturn($this->createMock(Forward::class));

        $this->router = new Router($actionFactory, $resource, $this->config);
    }

    private function blog(bool $enabled, string $prefix): void
    {
        $this->config->method('isBlogEnabled')->willReturn($enabled);
        $this->config->method('getUrlPrefix')->willReturn($prefix);
    }

    /**
     * @return Http&MockObject
     */
    private function request(string $pathInfo, ?string $moduleName = null): Http
    {
        $request = $this->createMock(Http::class);
        $request->method('getPathInfo')->willReturn($pathInfo);
        $request->method('getModuleName')->willReturn($moduleName);

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

    public function testCustomPrefixServesTheListing(): void
    {
        $this->blog(true, 'news');

        $this->assertNotNull($this->router->match($this->request('/news/')));
        $this->assertSame(['module' => 'blog', 'controller' => 'index', 'action' => 'index'], $this->written);
    }

    public function testCustomPrefixServesTheIdForm(): void
    {
        $this->blog(true, 'news');

        $this->assertNotNull($this->router->match($this->request('/news/post/view/id/5/')));
        $this->assertSame('post', $this->written['controller']);
        $this->assertSame('view', $this->written['action']);
        $this->assertSame(['id' => '5'], $this->written['params']);
    }

    public function testCustomPrefixReachesTheCommentEndpoint(): void
    {
        $this->blog(true, 'news');

        $this->assertNotNull($this->router->match($this->request('/news/comment/save')));
        $this->assertSame('comment', $this->written['controller']);
        $this->assertSame('save', $this->written['action']);
    }

    public function testCustomPrefixServesAPrettyPostUrl(): void
    {
        $this->blog(true, 'news');
        $this->connection->method('fetchOne')->willReturn('12');

        $this->assertNotNull($this->router->match($this->request('/news/mage-os-version-update')));
        $this->assertSame('post', $this->written['controller']);
        $this->assertSame(['id' => 12], $this->written['params']);
    }

    /**
     * An action the module does not have is left to 404. Forwarding it would
     * bring the request straight back here.
     */
    public function testUnknownActionUnderACustomPrefixIsNotForwarded(): void
    {
        $this->blog(true, 'news');
        $this->connection->method('fetchOne')->willReturn(false);

        $this->assertNull($this->router->match($this->request('/news/category/no-such-category')));
        $this->assertNull($this->router->match($this->request('/news/post')));
        $this->assertSame([], $this->written);
    }

    /**
     * The old /blog addresses on a store that moved to /news are not this
     * router's to serve. The standard router still reaches the controllers
     * there, and Model\StorefrontGate turns those requests into 404s.
     */
    public function testTheOldPrefixIsNotMatchedOnACustomPrefix(): void
    {
        $this->blog(true, 'news');
        $this->connection->method('fetchOne')->willReturn('12');

        $this->assertNull($this->router->match($this->request('/blog/mage-os-version-update')));
    }

    /**
     * On the default prefix the standard router owns /blog/post/view/id/N, as
     * it always has, so nothing changes for a store that never set a prefix.
     */
    public function testControllerPathsAreLeftToTheStandardRouterOnTheDefaultPrefix(): void
    {
        $this->blog(true, 'blog');

        $this->assertNull($this->router->match($this->request('/blog/post/view/id/5')));
        $this->assertSame([], $this->written);
    }

    public function testNothingMatchesWhileTheBlogIsSwitchedOff(): void
    {
        $this->blog(false, 'news');
        $this->connection->method('fetchOne')->willReturn('12');

        $this->assertNull($this->router->match($this->request('/news')));
        $this->assertNull($this->router->match($this->request('/news/mage-os-version-update')));
        $this->assertSame([], $this->written);
    }

    /**
     * A request this router already forwarded, that the standard router still
     * could not run, must not be forwarded a second time.
     */
    public function testAForwardedRequestIsNotForwardedAgain(): void
    {
        $this->blog(true, 'news');

        $this->assertNull($this->router->match($this->request('/news/post/view/id/5', 'blog')));
    }
}
