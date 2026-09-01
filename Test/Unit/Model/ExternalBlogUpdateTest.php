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
use RequestDesk\Blog\Model\ExternalBlog;
use RequestDesk\Blog\Model\PostCategoryResolver;
use RequestDesk\Blog\Model\PostFactory;
use RequestDesk\Blog\Model\TagResolver;

/**
 * updatePost() has accepted categoryIds and publishedAt since 1.9.0, but the
 * $data array handed to updateExistingPost() carried neither key, so both were
 * silently dropped: the request reported success while categories and the
 * original publish date went untouched. This pins both being carried through.
 */
class ExternalBlogUpdateTest extends TestCase
{
    /** @var PostRepositoryInterface&MockObject */
    private PostRepositoryInterface $postRepository;

    /** @var PostCategoryResolver&MockObject */
    private PostCategoryResolver $postCategoryResolver;

    private ExternalBlog $externalBlog;

    protected function setUp(): void
    {
        $this->postRepository = $this->createMock(PostRepositoryInterface::class);
        $this->postCategoryResolver = $this->createMock(PostCategoryResolver::class);

        $urlBuilder = $this->createMock(UrlInterface::class);
        $urlBuilder->method('getUrl')->willReturn('https://example.com/blog/post/view/id/42/');

        $this->externalBlog = new ExternalBlog(
            $this->postRepository,
            $this->createMock(PostFactory::class),
            $this->createMock(StoreManagerInterface::class),
            $this->createMock(ScopeConfigInterface::class),
            $this->createMock(Request::class),
            $this->createMock(EncryptorInterface::class),
            $this->createMock(SearchCriteriaBuilderFactory::class),
            $this->createMock(LoggerInterface::class),
            $this->createMock(ApiKeyValidator::class),
            $this->createMock(TagResolver::class),
            $this->postCategoryResolver,
            $urlBuilder
        );
    }

    public function testCategoryIdsAreSyncedOnUpdate(): void
    {
        $post = $this->createMock(PostInterface::class);
        $post->method('getPostId')->willReturn(42);
        $this->postRepository->method('getById')->willReturn($post);
        $this->postRepository->method('save')->willReturn($post);

        $this->postCategoryResolver->expects($this->once())
            ->method('syncForPost')
            ->with(42, [12, 14]);

        $result = $this->externalBlog->updatePost(postId: '42', categoryIds: [12, 14]);

        $this->assertTrue($result['success']);
    }

    public function testPublishedAtIsAppliedOnUpdate(): void
    {
        $post = $this->createMock(PostInterface::class);
        $post->method('getPostId')->willReturn(42);
        $post->expects($this->once())
            ->method('setCreatedAt')
            ->with(date('Y-m-d H:i:s', strtotime('2019-04-01')));
        $this->postRepository->method('getById')->willReturn($post);
        $this->postRepository->method('save')->willReturn($post);

        $result = $this->externalBlog->updatePost(postId: '42', publishedAt: '2019-04-01');

        $this->assertTrue($result['success']);
    }

    public function testOmittingBothLeavesNeitherTouched(): void
    {
        $post = $this->createMock(PostInterface::class);
        $post->method('getPostId')->willReturn(42);
        $post->expects($this->never())->method('setCreatedAt');
        $this->postRepository->method('getById')->willReturn($post);
        $this->postRepository->method('save')->willReturn($post);

        $this->postCategoryResolver->expects($this->never())->method('syncForPost');

        $result = $this->externalBlog->updatePost(postId: '42');

        $this->assertTrue($result['success']);
    }
}
