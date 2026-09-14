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
 * The repair half of the 1.10.2 image move. Posts migrated earlier hold Amasty's
 * post_thumbnail verbatim and body links into amasty/blog/. The rule pinned here
 * is what makes the repair safe to run on a live blog: move only what still
 * holds the Amasty value, touch only the columns that changed, and do nothing on
 * a second run.
 *
 * Driven through reflection, as MigrateAmastyPublishDateTest is, so the decision
 * is tested without standing up a whole command run.
 */
class MigrateAmastyMediaPathTest extends TestCase
{
    /** @var AdapterInterface&MockObject */
    private AdapterInterface $connection;

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
            $this->createMock(AmastyCategoryMapper::class),
            $this->createMock(PostCategoryResolver::class)
        );
    }

    private function backfill(?string $sourceThumbnail, bool $dryRun = false): bool
    {
        $reflection = new \ReflectionMethod(MigrateAmastyCommand::class, 'backfillMediaPaths');
        $reflection->setAccessible(true);

        return $reflection->invoke($this->command, 42, $sourceThumbnail, $dryRun);
    }

    public function testAnAmastyFeaturedImageAndBodyLinkAreBothMoved(): void
    {
        $this->connection->method('fetchRow')->willReturn([
            'featured_image' => 'MM26IN.png',
            'content' => "<img src=\"{{media url='amasty/blog/uploads/a.png'}}\">",
        ]);
        $this->connection->expects($this->once())
            ->method('update')
            ->with(
                'requestdesk_blog_post',
                [
                    'featured_image' => 'blog/MM26IN.png',
                    'content' => "<img src=\"{{media url='blog/uploads/a.png'}}\">",
                ],
                ['post_id = ?' => 42]
            );

        $this->assertTrue($this->backfill('MM26IN.png'));
    }

    /**
     * Only the column that changed is written, so a body nobody linked images
     * from is not rewritten with an identical copy of itself.
     */
    public function testOnlyTheChangedColumnIsWritten(): void
    {
        $this->connection->method('fetchRow')->willReturn([
            'featured_image' => 'MM26IN.png',
            'content' => '<p>No images here.</p>',
        ]);
        $this->connection->expects($this->once())
            ->method('update')
            ->with('requestdesk_blog_post', ['featured_image' => 'blog/MM26IN.png'], ['post_id = ?' => 42]);

        $this->assertTrue($this->backfill('MM26IN.png'));
    }

    /**
     * A featured image someone picked by hand after the migration no longer
     * matches the source and does not name the Amasty folder. It is not ours.
     */
    public function testAHandPickedFeaturedImageIsLeftAlone(): void
    {
        $this->connection->method('fetchRow')->willReturn([
            'featured_image' => 'requestdesk/blog/new-hero.png',
            'content' => '',
        ]);
        $this->connection->expects($this->never())->method('update');

        $this->assertFalse($this->backfill('MM26IN.png'));
    }

    public function testASecondRunIsANoOp(): void
    {
        $this->connection->method('fetchRow')->willReturn([
            'featured_image' => 'blog/MM26IN.png',
            'content' => "<img src=\"{{media url='blog/uploads/a.png'}}\">",
        ]);
        $this->connection->expects($this->never())->method('update');

        $this->assertFalse($this->backfill('MM26IN.png'));
    }

    public function testDryRunReportsWithoutWriting(): void
    {
        $this->connection->method('fetchRow')->willReturn([
            'featured_image' => 'MM26IN.png',
            'content' => '',
        ]);
        $this->connection->expects($this->never())->method('update');

        $this->assertTrue($this->backfill('MM26IN.png', true));
    }
}
