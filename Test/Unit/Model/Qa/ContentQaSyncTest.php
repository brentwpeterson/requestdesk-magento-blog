<?php
/**
 * Copyright © RequestDesk. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace RequestDesk\Blog\Test\Unit\Model\Qa;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RequestDesk\Blog\Api\Data\PostInterface;
use RequestDesk\Blog\Api\QaLinkResolverInterface;
use RequestDesk\Blog\Model\Qa\ContentQa;
use RequestDesk\Blog\Model\Qa\ContentQaSync;
use RequestDesk\Blog\Model\Qa\QaBridge;

/**
 * The move from body section to linked pairs has one rule that matters most:
 * the body only loses its Q&A once the pairs are stored somewhere that renders
 * them. Without RequestDesk_Qa the body is left alone and the result says why.
 */
class ContentQaSyncTest extends TestCase
{
    private const BODY = '<p>Body.</p><section class="rd-qa" data-qa><h2>Questions and answers</h2>'
        . '<h3>First?</h3><p>One.</p><h3>Second?</h3><p>Two.</p></section>';

    /** @var QaBridge&MockObject */
    private QaBridge $bridge;

    /** @var QaLinkResolverInterface&MockObject */
    private QaLinkResolverInterface $linkResolver;

    /** @var LoggerInterface&MockObject */
    private LoggerInterface $logger;

    private ContentQaSync $sync;

    protected function setUp(): void
    {
        $this->bridge = $this->createMock(QaBridge::class);
        $this->linkResolver = $this->createMock(QaLinkResolverInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->sync = new ContentQaSync(new ContentQa(), $this->bridge, $this->linkResolver, $this->logger);
    }

    public function testPostWithoutSectionIsUntouched(): void
    {
        $post = $this->post('<p>No Q&amp;A here.</p>');
        $post->expects($this->never())->method('setContent');
        $this->bridge->expects($this->never())->method('isAvailable');

        $result = $this->sync->prepare($post);

        $this->assertSame(ContentQaSync::STATUS_NONE, $result['status']);
    }

    public function testWithoutQaModuleBodyKeepsSectionAndResultSaysWhy(): void
    {
        $post = $this->post(self::BODY);
        $this->bridge->method('isAvailable')->willReturn(false);
        $post->expects($this->never())->method('setContent');
        $this->logger->expects($this->once())->method('error');

        $result = $this->sync->prepare($post);

        $this->assertSame(ContentQaSync::STATUS_QA_MODULE_MISSING, $result['status']);
        $this->assertSame(2, $result['pairs']);
    }

    public function testWithQaModulePairsAreStoredThenBodyStrippedThenLinked(): void
    {
        $post = $this->post(self::BODY);
        $this->bridge->method('isAvailable')->willReturn(true);

        $this->bridge->method('create')->willReturnCallback(
            static function (string $class) {
                return str_ends_with($class, 'Collection')
                    ? new FakePairCollection()
                    : new FakePairModel();
            }
        );
        $this->bridge->method('get')->willReturn(new FakePairResource());

        $post->expects($this->once())->method('setContent')->with(
            $this->callback(static fn (string $html) => !str_contains($html, 'data-qa') && str_contains($html, 'Body.'))
        );

        $prepared = $this->sync->prepare($post);
        $this->assertSame(ContentQaSync::STATUS_PENDING_LINK, $prepared['status']);
        $this->assertSame([10, 11], $prepared['qa_ids']);

        $this->linkResolver->expects($this->once())->method('syncForEntity')
            ->with(QaLinkResolverInterface::ENTITY_BLOG_POST, 292, [10, 11]);

        $finished = $this->sync->finish(292, $prepared);
        $this->assertSame(ContentQaSync::STATUS_LINKED, $finished['status']);
    }

    public function testFinishDoesNothingUnlessPairsWereStored(): void
    {
        $this->linkResolver->expects($this->never())->method('syncForEntity');

        $result = $this->sync->finish(292, ['status' => ContentQaSync::STATUS_QA_MODULE_MISSING, 'qa_ids' => [], 'pairs' => 2]);

        $this->assertSame(ContentQaSync::STATUS_QA_MODULE_MISSING, $result['status']);
    }

    /**
     * @param string $content
     * @return PostInterface&MockObject
     */
    private function post(string $content): PostInterface
    {
        $post = $this->createMock(PostInterface::class);
        $post->method('getContent')->willReturn($content);
        return $post;
    }
}

/**
 * Stand-ins for RequestDesk_Qa's pair classes, which are not in this module's
 * composer tree. Only the methods ContentQaSync calls are here.
 */
class FakePairCollection
{
    public function addFieldToFilter(string $field, $value): self
    {
        return $this;
    }

    public function setPageSize(int $size): self
    {
        return $this;
    }

    public function getFirstItem(): FakePairModel
    {
        return new FakePairModel();
    }
}

class FakePairModel
{
    private array $data = [];

    public function setData(string $key, $value): self
    {
        $this->data[$key] = $value;
        return $this;
    }

    public function getData(string $key)
    {
        return $this->data[$key] ?? null;
    }
}

class FakePairResource
{
    private int $nextId = 10;

    public function save(FakePairModel $model): void
    {
        $model->setData('qa_id', $this->nextId++);
    }
}
