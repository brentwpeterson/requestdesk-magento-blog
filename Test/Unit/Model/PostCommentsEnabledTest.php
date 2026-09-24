<?php
/**
 * Copyright © RequestDesk. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace RequestDesk\Blog\Test\Unit\Model;

use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\TestCase;
use RequestDesk\Blog\Api\Data\PostInterface;
use RequestDesk\Blog\Model\Post;

/**
 * The per-post Allow Comment toggle, added in 1.6.5.
 *
 * Until 1.12.0 the default was on, so posts that predated the column kept their
 * comments. It is now off everywhere - the column, the admin form and this
 * getter - because none of the stores running the module take comments and
 * every new post had to be switched off by hand. Existing rows keep whatever
 * they hold; only an absent value changed meaning.
 */
class PostCommentsEnabledTest extends TestCase
{
    private Post $post;

    protected function setUp(): void
    {
        $this->post = (new ObjectManager($this))->getObject(Post::class);
    }

    /**
     * An object built in memory without the value matches the column default,
     * so it does not show a comment form it would lose once saved.
     */
    public function testAbsentValueReadsAsDisabled(): void
    {
        $this->assertFalse($this->post->getCommentsEnabled());
    }

    public function testExplicitNullReadsAsDisabled(): void
    {
        $this->post->setData(PostInterface::COMMENTS_ENABLED, null);

        $this->assertFalse($this->post->getCommentsEnabled());
    }

    public function testZeroReadsAsDisabled(): void
    {
        $this->post->setData(PostInterface::COMMENTS_ENABLED, 0);

        $this->assertFalse($this->post->getCommentsEnabled());
    }

    public function testOneReadsAsEnabled(): void
    {
        $this->post->setData(PostInterface::COMMENTS_ENABLED, 1);

        $this->assertTrue($this->post->getCommentsEnabled());
    }

    /**
     * MySQL hands back a smallint as a string. Reading "0" as truthy would
     * invert the setting for every post loaded from the database, which is the
     * only way this value ever arrives in production.
     */
    public function testStringZeroFromTheDatabaseReadsAsDisabled(): void
    {
        $this->post->setData(PostInterface::COMMENTS_ENABLED, '0');

        $this->assertFalse($this->post->getCommentsEnabled());
    }

    public function testStringOneFromTheDatabaseReadsAsEnabled(): void
    {
        $this->post->setData(PostInterface::COMMENTS_ENABLED, '1');

        $this->assertTrue($this->post->getCommentsEnabled());
    }

    /**
     * The setter stores an int, not a bool. The column is a smallint, and a
     * raw bool would be written as '' for false by some adapters.
     */
    public function testSetterStoresAnIntegerNotABoolean(): void
    {
        $this->post->setCommentsEnabled(false);
        $this->assertSame(0, $this->post->getData(PostInterface::COMMENTS_ENABLED));

        $this->post->setCommentsEnabled(true);
        $this->assertSame(1, $this->post->getData(PostInterface::COMMENTS_ENABLED));
    }

    public function testSetterIsChainable(): void
    {
        $this->assertSame($this->post, $this->post->setCommentsEnabled(true));
    }

    public function testRoundTripThroughTheSetterAndGetter(): void
    {
        $this->assertFalse($this->post->setCommentsEnabled(false)->getCommentsEnabled());
        $this->assertTrue($this->post->setCommentsEnabled(true)->getCommentsEnabled());
    }
}
