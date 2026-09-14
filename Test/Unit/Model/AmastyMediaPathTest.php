<?php
/**
 * Copyright © RequestDesk. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace RequestDesk\Blog\Test\Unit\Model;

use PHPUnit\Framework\TestCase;
use RequestDesk\Blog\Model\AmastyMediaPath;

/**
 * On the live Evrig store Amasty's originals sit at /media/amasty/blog/<value>,
 * and the same value at /media/<value> is a 404. The migration copied
 * post_thumbnail verbatim, so every migrated featured image would have broken at
 * cutover. The body shapes below are taken from the Evrig data, where 96 of 274
 * posts carry at least one of them.
 */
class AmastyMediaPathTest extends TestCase
{
    public function testBareFileNameMovesUnderTheBlogFolder(): void
    {
        $this->assertSame('blog/MM26IN.png', AmastyMediaPath::featuredImage('MM26IN.png'));
    }

    public function testWordpressEraSubfolderIsKept(): void
    {
        $this->assertSame(
            'blog/uploads/2022/05/Evrig_Homepage.png',
            AmastyMediaPath::featuredImage('uploads/2022/05/Evrig_Homepage.png')
        );
    }

    public function testAValueThatAlreadyNamesTheAmastyFolderIsNotDoubled(): void
    {
        $this->assertSame('blog/hero.png', AmastyMediaPath::featuredImage('amasty/blog/hero.png'));
    }

    public function testAbsoluteAndRootRelativeValuesAreLeftAlone(): void
    {
        $this->assertSame('https://cdn.test/a.png', AmastyMediaPath::featuredImage('https://cdn.test/a.png'));
        $this->assertSame('/media/a.png', AmastyMediaPath::featuredImage('/media/a.png'));
    }

    public function testEmptyValuesMeanNoImage(): void
    {
        $this->assertNull(AmastyMediaPath::featuredImage(null));
        $this->assertNull(AmastyMediaPath::featuredImage('   '));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function bodyShapes(): array
    {
        return [
            'quoted media directive' => [
                "<img src=\"{{media url='amasty/blog/uploads/2023/04/a.png'}}\">",
                "<img src=\"{{media url='blog/uploads/2023/04/a.png'}}\">",
            ],
            'bare renditions directive' => [
                '<img src="{{media url=.renditions/amasty/blog/hyva-install/a.png}}">',
                '<img src="{{media url=.renditions/blog/hyva-install/a.png}}">',
            ],
            'html-encoded quote from the wysiwyg editor' => [
                '{{media url=&quot;amasty/blog/a.png&quot;}}',
                '{{media url=&quot;blog/a.png&quot;}}',
            ],
            'absolute renditions url' => [
                '<img src="https://www.evrig.com/media/.renditions/amasty/blog/store-credit/a.png">',
                '<img src="https://www.evrig.com/media/.renditions/blog/store-credit/a.png">',
            ],
            'absolute original url' => [
                '<img src="https://www.evrig.com/media/amasty/blog/uploads/2022/10/MEET.png">',
                '<img src="https://www.evrig.com/media/blog/uploads/2022/10/MEET.png">',
            ],
        ];
    }

    /**
     * @dataProvider bodyShapes
     */
    public function testEveryBodyShapeIsRewritten(string $before, string $after): void
    {
        $this->assertSame($after, AmastyMediaPath::rewriteContent($before));
    }

    public function testProseAndAmastyLinksAreNotTouched(): void
    {
        $body = '<p>Read <a href="https://amasty.com/blog/magento-2">their post</a>, path amasty/blog/ in text.</p>';

        $this->assertSame($body, AmastyMediaPath::rewriteContent($body));
    }

    public function testRewriteIsIdempotent(): void
    {
        $once = AmastyMediaPath::rewriteContent('{{media url=\'amasty/blog/a.png\'}}');

        $this->assertSame($once, AmastyMediaPath::rewriteContent($once));
    }
}
