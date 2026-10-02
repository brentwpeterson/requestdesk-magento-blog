<?php
/**
 * Copyright © RequestDesk. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace RequestDesk\Blog\Test\Unit\Console\Command;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RequestDesk\Blog\Console\Command\DisableCommentsCommand;
use Symfony\Component\Console\Tester\CommandTester;

class DisableCommentsCommandTest extends TestCase
{
    /**
     * @var AdapterInterface&MockObject
     */
    private AdapterInterface $connection;

    /**
     * @var CommandTester
     */
    private CommandTester $tester;

    protected function setUp(): void
    {
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();

        $this->connection = $this->createMock(AdapterInterface::class);
        $this->connection->method('select')->willReturn($select);

        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($this->connection);
        $resource->method('getTableName')->willReturnArgument(0);

        $this->tester = new CommandTester(new DisableCommentsCommand($resource));
    }

    public function testTurnsCommentsOffOnEveryPostThatHasThem(): void
    {
        $this->connection->method('fetchOne')->willReturn('273');
        $this->connection->expects($this->once())
            ->method('update')
            ->with('requestdesk_blog_post', ['comments_enabled' => 0], ['comments_enabled = ?' => 1])
            ->willReturn(273);

        $this->assertSame(0, $this->tester->execute([]));
        $this->assertStringContainsString('273 post(s)', $this->tester->getDisplay());
    }

    public function testDryRunWritesNothing(): void
    {
        $this->connection->method('fetchOne')->willReturn('273');
        $this->connection->expects($this->never())->method('update');

        $this->assertSame(0, $this->tester->execute(['--dry-run' => true]));
        $this->assertStringContainsString('DRY RUN - 273 post(s)', $this->tester->getDisplay());
    }

    public function testNothingToDoSkipsTheUpdate(): void
    {
        $this->connection->method('fetchOne')->willReturn('0');
        $this->connection->expects($this->never())->method('update');

        $this->assertSame(0, $this->tester->execute([]));
    }
}
