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
use RequestDesk\Blog\Model\AmastyCategoryMapper;
use RequestDesk\Blog\Model\AuthorResolver;
use RequestDesk\Blog\Model\PostCategoryResolver;
use RequestDesk\Blog\Model\PostFactory;
use RequestDesk\Blog\Model\TagResolver;

/**
 * Migrated posts were all stamped with the moment the import ran - on the Evrig
 * data, 271 of 274 posts claimed to be published on one of two days in 2026,
 * collapsing a four-year archive and making every freshness signal on the blog
 * wrong. The migration never read Amasty's published_at at all.
 *
 * created_at cannot use the "only fill when empty" rule the short-description
 * backfill uses, because the column defaults to CURRENT_TIMESTAMP and is never
 * empty. The rule is instead "only when ours is LATER than the source": an
 * import stamp always is, a date someone moved deliberately to an earlier point
 * is not. These tests pin that rule, because getting it wrong in the other
 * direction would silently rewrite hand-edited dates across the whole blog.
 *
 * Driven through reflection rather than a full command run: the decision is the
 * risky part, and reaching it through execute() would need a dozen ordered
 * fetchOne() expectations that pin mock call order instead of behaviour.
 */
class MigrateAmastyPublishDateTest extends TestCase
{
    /** @var AdapterInterface&MockObject */
    private AdapterInterface $connection;

    private MigrateAmastyCommand $command;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(AdapterInterface::class);

        // backfillPublishDate() reads created_at through a Select. The adapter
        // mock returns null from select() by default, so the fluent chain has to
        // be stood up or the method fatals before it reaches its decision.
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
            $this->createMock(AmastyCategoryMapper::class),
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

    public function testPublishedAtIsPreferredOverCreatedAt(): void
    {
        $this->assertSame(
            '2022-05-16 05:37:40',
            $this->call('publishedAtFrom', [
                'published_at' => '2022-05-16 05:37:40',
                'created_at' => '2024-01-01 00:00:00',
            ])
        );
    }

    /**
     * Amasty rows exist with published_at never set. created_at on the SOURCE
     * row is still the truth about when the post appeared, and far closer than
     * the moment our import ran.
     */
    public function testCreatedAtIsTheFallbackWhenPublishedAtIsMissing(): void
    {
        $this->assertSame(
            '2023-03-04 09:00:00',
            $this->call('publishedAtFrom', ['published_at' => null, 'created_at' => '2023-03-04 09:00:00'])
        );
    }

    /**
     * MySQL's zero date parses to something absurd rather than failing, so it
     * has to be rejected by name or it becomes a "publish date" in 1899.
     */
    public function testZeroDatesAreRejected(): void
    {
        $this->assertNull(
            $this->call('publishedAtFrom', [
                'published_at' => '0000-00-00 00:00:00',
                'created_at' => '0000-00-00 00:00:00',
            ])
        );
    }

    public function testNoUsableDateReturnsNull(): void
    {
        $this->assertNull($this->call('publishedAtFrom', ['published_at' => '', 'created_at' => '']));
    }

    /**
     * The import-stamp case: ours is later than the source, so it is ours to
     * correct.
     */
    public function testALaterStoredDateIsCorrected(): void
    {
        $this->connection->method('fetchOne')->willReturn('2026-09-09 18:59:18');
        $this->connection->expects($this->once())
            ->method('update')
            ->with('requestdesk_blog_post', ['created_at' => '2022-05-16 05:37:40'], ['post_id = ?' => 4]);

        $this->assertTrue($this->call('backfillPublishDate', 4, '2022-05-16 05:37:40', false));
    }

    /**
     * The hand-edited case. Someone backdating a post deliberately must not have
     * it dragged forward by a re-run.
     */
    public function testAnEarlierStoredDateIsLeftAlone(): void
    {
        $this->connection->method('fetchOne')->willReturn('2020-01-01 00:00:00');
        $this->connection->expects($this->never())->method('update');

        $this->assertFalse($this->call('backfillPublishDate', 4, '2022-05-16 05:37:40', false));
    }

    /**
     * Idempotency: once corrected the two match, so the next run is a no-op
     * rather than a rewrite.
     */
    public function testAnAlreadyCorrectDateIsANoOp(): void
    {
        $this->connection->method('fetchOne')->willReturn('2022-05-16 05:37:40');
        $this->connection->expects($this->never())->method('update');

        $this->assertFalse($this->call('backfillPublishDate', 4, '2022-05-16 05:37:40', false));
    }

    public function testDryRunReportsTheChangeWithoutWriting(): void
    {
        $this->connection->method('fetchOne')->willReturn('2026-09-09 18:59:18');
        $this->connection->expects($this->never())->method('update');

        $this->assertTrue($this->call('backfillPublishDate', 4, '2022-05-16 05:37:40', true));
    }
}
