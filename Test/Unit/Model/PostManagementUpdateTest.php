<?php
/**
 * Copyright © RequestDesk. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace RequestDesk\Blog\Test\Unit\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RequestDesk\Blog\Api\Data\PostInterface;
use RequestDesk\Blog\Api\PostRepositoryInterface;
use RequestDesk\Blog\Model\BlogCache;
use RequestDesk\Blog\Model\PostCategoryResolver;
use RequestDesk\Blog\Model\PostFactory;
use RequestDesk\Blog\Model\PostManagement;
use RequestDesk\Blog\Model\Qa\ContentQaSync;

/**
 * createOrUpdate matches on requestdeskPostId and replaces the whole post, so
 * it cannot edit a post that never came from RequestDesk (Evrig's 290 migrated
 * posts): it creates a duplicate instead. updatePost is the partial, by-id edit.
 */
class PostManagementUpdateTest extends TestCase
{
    /** @var PostRepositoryInterface&MockObject */
    private PostRepositoryInterface $postRepository;

    /** @var PostFactory&MockObject */
    private PostFactory $postFactory;

    /** @var ContentQaSync&MockObject */
    private ContentQaSync $contentQaSync;

    private PostManagement $management;

    protected function setUp(): void
    {
        $this->postRepository = $this->createMock(PostRepositoryInterface::class);
        $this->postFactory = $this->createMock(PostFactory::class);
        $this->contentQaSync = $this->createMock(ContentQaSync::class);
        $this->contentQaSync->method('prepare')
            ->willReturn(['status' => ContentQaSync::STATUS_NONE, 'qa_ids' => [], 'pairs' => 0]);
        $this->contentQaSync->method('finish')->willReturnArgument(1);

        $this->management = new PostManagement(
            $this->postRepository,
            $this->postFactory,
            $this->createMock(ResourceConnection::class),
            $this->createMock(LoggerInterface::class),
            $this->createMock(PostCategoryResolver::class),
            $this->createMock(BlogCache::class),
            $this->contentQaSync
        );
    }

    public function testOnlySentFieldsChange(): void
    {
        $post = $this->existingPost();
        $post->expects($this->once())->method('setTitle')->with('New title');
        $post->expects($this->never())->method('setContent');
        $post->expects($this->never())->method('setUrlKey');
        $post->expects($this->never())->method('setAuthor');
        $post->expects($this->never())->method('setStatus');
        $post->expects($this->never())->method('setRequestdeskPostId');
        $this->contentQaSync->expects($this->never())->method('prepare');
        $this->postRepository->expects($this->once())->method('save')->willReturn($post);

        $this->management->updatePost(15, 'New title');
    }

    public function testNewContentGoesThroughTheQaMove(): void
    {
        $post = $this->existingPost();
        $post->expects($this->once())->method('setContent')->with('<p>New body.</p>');
        $this->contentQaSync->expects($this->once())->method('prepare')->with($post);
        $this->postRepository->method('save')->willReturn($post);

        $this->management->updatePost(15, null, '<p>New body.</p>');
    }

    public function testNeverCreatesAPost(): void
    {
        $this->postRepository->method('getById')->willThrowException(new NoSuchEntityException(__('missing')));
        $this->postFactory->expects($this->never())->method('create');
        $this->postRepository->expects($this->never())->method('save');

        $this->expectException(NoSuchEntityException::class);
        $this->management->updatePost(999, 'Title');
    }

    public function testEmptyUpdateIsRejected(): void
    {
        $this->existingPost();
        $this->postRepository->expects($this->never())->method('save');

        $this->expectException(LocalizedException::class);
        $this->management->updatePost(15);
    }

    public function testUnknownStatusIsRejected(): void
    {
        $this->existingPost();
        $this->postRepository->expects($this->never())->method('save');

        $this->expectException(LocalizedException::class);
        $this->management->updatePost(15, null, null, null, null, null, null, null, 7);
    }

    /**
     * @return PostInterface&MockObject
     */
    private function existingPost(): PostInterface
    {
        $post = $this->createMock(PostInterface::class);
        $post->method('getPostId')->willReturn(15);
        $this->postRepository->method('getById')->with(15)->willReturn($post);
        return $post;
    }
}
