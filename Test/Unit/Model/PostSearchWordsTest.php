<?php
/**
 * Copyright © RequestDesk. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength.TooLong

namespace RequestDesk\Blog\Test\Unit\Model;

use Magento\Framework\DB\Adapter\AdapterInterface;
use PHPUnit\Framework\TestCase;
use RequestDesk\Blog\Model\PostSearch;

/**
 * The word split and LIKE builder are shared by the results page and the live
 * search, so both match the same way.
 */
class PostSearchWordsTest extends TestCase
{
    public function testWordsSplitOnAnyWhitespace(): void
    {
        $this->assertSame(['hyva', 'checkout'], PostSearch::words("hyva \t checkout"));
    }

    public function testWordsAreCapped(): void
    {
        $this->assertCount(10, PostSearch::words(implode(' ', range(1, 25))));
    }

    public function testEmptyQueryHasNoWords(): void
    {
        $this->assertSame([], PostSearch::words(''));
    }

    /**
     * % and _ in the query are literal characters, not wildcards.
     */
    public function testLikeAllEscapesWildcards(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('quoteInto')
            ->willReturnCallback(static fn (string $text, string $value): string => str_replace('?', "'" . $value . "'", $text));

        $this->assertSame(
            ["e.name LIKE '%50\\%%'", "e.name LIKE '%a\\_b%'"],
            PostSearch::likeAll($connection, 'e.name', ['50%', 'a_b'])
        );
    }
}
