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
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RequestDesk\Blog\Api\PostRepositoryInterface;
use RequestDesk\Blog\Console\Command\MigrateAmastyCommand;
use RequestDesk\Blog\Model\AmastyCategoryMapper;
use RequestDesk\Blog\Model\AuthorResolver;
use RequestDesk\Blog\Model\PostCategoryResolver;
use RequestDesk\Blog\Model\PostFactory;
use RequestDesk\Blog\Model\TagResolver;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The command's own comment claims "a bad --parent-category fails before
 * anything is written", but the option was cast straight to int with no check:
 * a non-numeric value silently became 0 (and the auto-create-or-find path took
 * over instead of failing), and a numeric-but-nonexistent id sailed through the
 * guard and only surfaced deep inside mapCategory(), where every failure is
 * caught, logged, and swallowed rather than surfaced. This pins the option
 * being validated - and the run refused - before the migration starts.
 */
class MigrateAmastyCommandTest extends TestCase
{
    /** @var AdapterInterface&MockObject */
    private AdapterInterface $connection;

    /** @var AmastyCategoryMapper&MockObject */
    private AmastyCategoryMapper $categoryMapper;

    private CommandTester $tester;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(AdapterInterface::class);
        $this->connection->method('isTableExists')->willReturn(true);

        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($this->connection);
        $resource->method('getTableName')->willReturnArgument(0);

        $this->categoryMapper = $this->createMock(AmastyCategoryMapper::class);

        $command = new MigrateAmastyCommand(
            $this->createMock(State::class),
            $resource,
            $this->createMock(PostRepositoryInterface::class),
            $this->createMock(PostFactory::class),
            $this->createMock(TagResolver::class),
            $this->createMock(AuthorResolver::class),
            $this->categoryMapper,
            $this->createMock(PostCategoryResolver::class)
        );

        $this->tester = new CommandTester($command);
    }

    public function testNonNumericParentCategoryIsRefused(): void
    {
        $this->categoryMapper->expects($this->never())->method('categoryExists');

        $exitCode = $this->tester->execute(['--parent-category' => 'abc']);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('must be a positive category id', $this->tester->getDisplay());
    }

    public function testNonexistentParentCategoryIsRefused(): void
    {
        $this->categoryMapper->method('categoryExists')->with(9999)->willReturn(false);

        $exitCode = $this->tester->execute(['--parent-category' => '9999']);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('does not exist', $this->tester->getDisplay());
    }
}
