<?php
/**
 * Copyright © RequestDesk. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace RequestDesk\Blog\Test\Unit\Model;

use Magento\Framework\Api\SearchCriteriaBuilderFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RequestDesk\Blog\Api\Data\PostInterface;
use RequestDesk\Blog\Api\PostRepositoryInterface;
use RequestDesk\Blog\Model\ApiKeyValidator;
use RequestDesk\Blog\Model\Config;
use RequestDesk\Blog\Model\ExternalBlog;
use RequestDesk\Blog\Model\PostCategoryResolver;
use RequestDesk\Blog\Model\PostFactory;
use RequestDesk\Blog\Model\TagResolver;

/**
 * getPost()'s response 'url' field used to be hand-built as
 * baseUrl + 'blog/' + urlKey, bypassing Block\PostUrl - the shared helper every
 * other URL-emitting call site in the module was moved onto for exactly this
 * reason (see the 1.9.3 changelog entry "Fix: listing links used the id form").
 * A post with no url_key therefore got a dead "blog/" link in the API payload
 * instead of the id-form fallback PostUrl::resolve() provides. This covers both
 * branches so that regression cannot come back silently.
 */
class ExternalBlogUrlTest extends TestCase
{
    /** @var PostRepositoryInterface&MockObject */
    private PostRepositoryInterface $postRepository;

    /** @var ApiKeyValidator&MockObject */
    private ApiKeyValidator $apiKeyValidator;

    /** @var UrlInterface&MockObject */
    private UrlInterface $urlBuilder;

    private ExternalBlog $externalBlog;

    protected function setUp(): void
    {
        $this->postRepository = $this->createMock(PostRepositoryInterface::class);
        $this->apiKeyValidator = $this->createMock(ApiKeyValidator::class);
        $this->urlBuilder = $this->createMock(UrlInterface::class);
        $config = $this->createMock(Config::class);
        $config->method('getUrlPrefix')->willReturn('blog');

        $this->externalBlog = new ExternalBlog(
            $this->postRepository,
            $this->createMock(PostFactory::class),
            $this->createMock(StoreManagerInterface::class),
            $this->createMock(ScopeConfigInterface::class),
            $this->createMock(Request::class),
            $this->createMock(EncryptorInterface::class),
            $this->createMock(SearchCriteriaBuilderFactory::class),
            $this->createMock(LoggerInterface::class),
            $this->apiKeyValidator,
            $this->createMock(TagResolver::class),
            $this->createMock(PostCategoryResolver::class),
            $this->urlBuilder,
            $config
        );
    }

    public function testUrlKeyResolvesToThePrettyForm(): void
    {
        $post = $this->createMock(PostInterface::class);
        $post->method('getPostId')->willReturn(42);
        $post->method('getUrlKey')->willReturn('evrig-earns-official-hyva-certification');
        $this->postRepository->method('getById')->willReturn($post);

        $this->urlBuilder->expects($this->once())
            ->method('getUrl')
            ->with('', ['_direct' => 'blog/evrig-earns-official-hyva-certification'])
            ->willReturn('https://example.com/blog/evrig-earns-official-hyva-certification');

        $result = $this->externalBlog->getPost('42');

        $this->assertSame(
            'https://example.com/blog/evrig-earns-official-hyva-certification',
            $result['post']['url']
        );
    }

    public function testEmptyUrlKeyFallsBackToTheIdForm(): void
    {
        $post = $this->createMock(PostInterface::class);
        $post->method('getPostId')->willReturn(256);
        $post->method('getUrlKey')->willReturn('');
        $this->postRepository->method('getById')->willReturn($post);

        $this->urlBuilder->expects($this->once())
            ->method('getUrl')
            ->with('', ['_direct' => 'blog/post/view/id/256/'])
            ->willReturn('https://example.com/blog/post/view/id/256/');

        $result = $this->externalBlog->getPost('256');

        $this->assertSame(
            'https://example.com/blog/post/view/id/256/',
            $result['post']['url']
        );
    }
}
