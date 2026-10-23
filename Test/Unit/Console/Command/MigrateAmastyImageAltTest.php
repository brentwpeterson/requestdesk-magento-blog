<?php
/**
 * Copyright © RequestDesk. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace RequestDesk\Blog\Test\Unit\Console\Command;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\State;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RequestDesk\Blog\Api\PostRepositoryInterface;
use RequestDesk\Blog\Console\Command\MigrateAmastyCommand;
use RequestDesk\Blog\Model\AmastyCategoryImporter;
use RequestDesk\Blog\Model\AuthorResolver;
use RequestDesk\Blog\Model\PostCategoryResolver;
use RequestDesk\Blog\Model\PostFactory;
use RequestDesk\Blog\Model\TagResolver;

/**
 * Amasty keeps a hand-written alt for the post image and another for the list
 * image; migrations before 1.13.1 dropped both (149 posts on the Evrig data).
 * We keep one image, so the post alt wins and the list alt is the fallback.
 *
 * The backfill follows the short-description rule: fill only while ours is
 * empty, so an alt edited in the admin after the migration survives a re-run.
 * Driven through reflection, like MigrateAmastyPublishDateTest.
 */
class MigrateAmastyImageAltTest extends TestCase
{
    /** @var AdapterInterface&MockObject */
    private AdapterInterface $connection;

    /** @var MigrateAmastyCommand */
    private MigrateAmastyCommand $command;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(AdapterInterface::class);

        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $select->method('limit')->willReturnSelf();
        $this->connection->method('select')->willReturn($select);

        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($this->connection);
        $resource->method('getTableName')->willReturnArgument(0);

        $this->command = new MigrateAmastyCommand(
            $this->createMock(State::class),
            $resource,
            $this->createMock(PostRepositoryInterface::class),
            $this->createMock(PostFactory::class),
            $this->createMock(TagResolver::class),
            $this->createMock(AuthorResolver::class),
            $this->createMock(AmastyCategoryImporter::class),
            $this->createMock(PostCategoryResolver::class)
        );
    }

    /**
     * @param string $method
     * @param mixed ...$args
     * @return mixed
     */
    private function call(string $method, ...$args)
    {
        $reflection = new \ReflectionMethod(MigrateAmastyCommand::class, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke($this->command, ...$args);
    }

    public function testPostAltIsPreferredOverListAlt(): void
    {
        $this->assertSame('Post alt', $this->call('imageAltFrom', [
            'post_thumbnail_alt' => ' Post alt ',
            'list_thumbnail_alt' => 'List alt',
        ]));
    }

    public function testListAltIsTheFallback(): void
    {
        $this->assertSame('List alt', $this->call('imageAltFrom', [
            'post_thumbnail_alt' => '',
            'list_thumbnail_alt' => 'List alt',
        ]));
    }

    public function testNoAltGivesNullSoTheTitleFallbackApplies(): void
    {
        $this->assertNull($this->call('imageAltFrom', ['post_thumbnail_alt' => '', 'list_thumbnail_alt' => null]));
    }

    public function testAnEmptyStoredAltIsFilled(): void
    {
        $this->connection->method('fetchOne')->willReturn(null);
        $this->connection->expects($this->once())
            ->method('update')
            ->with('requestdesk_blog_post', ['featured_image_alt' => 'Post alt'], ['post_id = ?' => 7]);

        $this->assertTrue($this->call('backfillImageAlt', 7, 'Post alt', false));
    }

    public function testAnAltSetSinceTheMigrationIsKept(): void
    {
        $this->connection->method('fetchOne')->willReturn('Edited in the admin');
        $this->connection->expects($this->never())->method('update');

        $this->assertFalse($this->call('backfillImageAlt', 7, 'Post alt', false));
    }

    public function testDryRunReportsTheFillWithoutWriting(): void
    {
        $this->connection->method('fetchOne')->willReturn('');
        $this->connection->expects($this->never())->method('update');

        $this->assertTrue($this->call('backfillImageAlt', 7, 'Post alt', true));
    }
}
