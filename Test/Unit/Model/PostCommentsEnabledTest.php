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
 * The whole risk in this feature is the default. The column arrived on a table
 * that already had rows, and every one of those posts had working comments
 * before it existed. If absent data read as "disabled", shipping the upgrade
 * would silently close comments on every existing post - a data-losing change
 * disguised as a feature. These tests exist to keep that from regressing.
 */
class PostCommentsEnabledTest extends TestCase
{
    private Post $post;

    protected function setUp(): void
    {
        $this->post = (new ObjectManager($this))->getObject(Post::class);
    }

    /**
     * The one that matters: a row written before the column existed, or an
     * object built in memory without it, keeps comments on.
     */
    public function testAbsentValueReadsAsEnabled(): void
    {
        $this->assertTrue($this->post->getCommentsEnabled());
    }

    public function testExplicitNullReadsAsEnabled(): void
    {
        $this->post->setData(PostInterface::COMMENTS_ENABLED, null);

        $this->assertTrue($this->post->getCommentsEnabled());
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
