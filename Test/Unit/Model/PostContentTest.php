<?php
/**
 * Copyright © RequestDesk. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace RequestDesk\Blog\Test\Unit\Model;

use Magento\Cms\Model\Template\FilterProvider;
use Magento\Framework\Filter\Template;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RequestDesk\Blog\Model\PostContent;

/**
 * PostContent is where stored post content gets turned into something safe to
 * render or excerpt, and every branch in it exists because of a specific defect
 * in real data. The tests below are written against those defects rather than
 * against the implementation, so they should survive a refactor and fail if a
 * repair is dropped.
 */
class PostContentTest extends TestCase
{
    private PostContent $postContent;

    /** @var FilterProvider&MockObject */
    private FilterProvider $filterProvider;

    protected function setUp(): void
    {
        $this->filterProvider = $this->createMock(FilterProvider::class);
        $this->postContent = new PostContent($this->filterProvider);
    }

    // ---------------------------------------------------------------- render

    public function testRenderReturnsEmptyStringForNull(): void
    {
        $this->filterProvider->expects($this->never())->method('getPageFilter');

        $this->assertSame('', $this->postContent->render(null));
    }

    public function testRenderPassesContentThroughThePageFilter(): void
    {
        $filter = $this->createMock(Template::class);
        $filter->method('filter')->with('<p>Hello</p>')->willReturn('<p>Filtered</p>');
        $this->filterProvider->method('getPageFilter')->willReturn($filter);

        $this->assertSame('<p>Filtered</p>', $this->postContent->render('<p>Hello</p>'));
    }

    /**
     * The deliberate resilience decision in this class: a malformed directive
     * must not blank the whole post body. If the filter throws, the caller gets
     * the unfiltered content rather than an empty page.
     */
    public function testRenderFallsBackToRawContentWhenTheFilterThrows(): void
    {
        $filter = $this->createMock(Template::class);
        $filter->method('filter')->willThrowException(new \RuntimeException('bad directive'));
        $this->filterProvider->method('getPageFilter')->willReturn($filter);

        $this->assertSame(
            '<p>{{media url="broken}}</p>',
            $this->postContent->render('<p>{{media url="broken}}</p>')
        );
    }

    // ----------------------------------------------------------- toPlainText

    public function testToPlainTextReturnsEmptyStringForNull(): void
    {
        $this->assertSame('', $this->postContent->toPlainText(null));
    }

    public function testToPlainTextStripsTags(): void
    {
        $this->assertSame(
            'Hello world',
            $this->postContent->toPlainText('<p>Hello <strong>world</strong></p>')
        );
    }

    /**
     * The reason this class exists at all. strip_tags() removes the <style> tag
     * but keeps the text between them, so editor CSS such as "#html-body {...}"
     * used to surface in excerpts as gibberish. Script and style elements have
     * to be dropped whole, before stripping.
     */
    public function testToPlainTextDropsStyleAndScriptElementsEntirely(): void
    {
        $html = '<style>#html-body { margin: 0; font-size: 14px; }</style>'
            . '<p>The actual post text.</p>'
            . '<script>var tracking = 1;</script>';

        $result = $this->postContent->toPlainText($html);

        $this->assertSame('The actual post text.', $result);
        $this->assertStringNotContainsString('html-body', $result);
        $this->assertStringNotContainsString('tracking', $result);
    }

    public function testToPlainTextDecodesEntitiesAndCollapsesWhitespace(): void
    {
        $this->assertSame(
            'Tom & Jerry said "hi"',
            $this->postContent->toPlainText("<p>Tom &amp; Jerry\n\n  said &quot;hi&quot;</p>")
        );
    }

    /**
     * Page Builder stores pasted markup escaped, so the raw column holds
     * "&lt;p&gt;". Without decoding first, strip_tags() sees no tags and the
     * escaped angle brackets end up in the excerpt as literal text.
     */
    public function testToPlainTextHandlesFullyEscapedMarkup(): void
    {
        $this->assertSame(
            'Escaped paragraph',
            $this->postContent->toPlainText('&lt;p&gt;Escaped paragraph&lt;/p&gt;')
        );
    }

    // --------------------------------------------------------------- excerpt

    public function testExcerptReturnsShortTextUnchangedWithoutEllipsis(): void
    {
        $result = $this->postContent->excerpt('<p>Short enough.</p>', 180);

        $this->assertSame('Short enough.', $result);
        $this->assertStringNotContainsString('…', $result);
    }

    public function testExcerptTruncatesLongTextAndAppendsEllipsis(): void
    {
        $text = str_repeat('word ', 100);

        $result = $this->postContent->excerpt($text, 50);

        $this->assertStringEndsWith('…', $result);
        $this->assertLessThanOrEqual(51, mb_strlen($result));
    }

    /**
     * Truncation prefers a word boundary, but only when the last space falls
     * past 60% of the limit. Otherwise a single long token would collapse the
     * excerpt to almost nothing.
     */
    public function testExcerptBreaksOnAWordBoundaryRatherThanMidWord(): void
    {
        $text = 'The quick brown fox jumps over the lazy dog and keeps running onward';

        $result = $this->postContent->excerpt($text, 30);

        $this->assertStringEndsWith('…', $result);
        $trimmed = rtrim($result, '…');
        $this->assertSame($trimmed, rtrim($trimmed), 'excerpt should not keep a trailing space before the ellipsis');
        $this->assertStringContainsString($trimmed, $text, 'excerpt should be a prefix of the source text');
    }

    public function testExcerptCutsHardWhenNoUsableWordBoundaryExists(): void
    {
        // One long token: the only space sits before 60% of the limit, so the
        // boundary is rejected and the text is cut mid-word on purpose.
        $text = 'ab ' . str_repeat('x', 60);

        $result = $this->postContent->excerpt($text, 40);

        $this->assertStringEndsWith('…', $result);
        $this->assertStringStartsWith('ab xxx', $result);
    }

    public function testExcerptCountsCharactersNotBytes(): void
    {
        $text = str_repeat('é', 60);

        $result = $this->postContent->excerpt($text, 20);

        // 20 characters plus the ellipsis, not 20 bytes.
        $this->assertSame(21, mb_strlen($result));
    }

    // ---------------------------------------------------- normalizeForStorage

    public function testNormalizeForStorageReturnsEmptyStringForBlankInput(): void
    {
        $this->assertSame('', $this->postContent->normalizeForStorage(null));
        $this->assertSame('', $this->postContent->normalizeForStorage('   '));
    }

    /**
     * Documented contract: content with nothing to fix comes back byte-identical,
     * so a caller can use a strict comparison to decide whether a row needs
     * writing at all. A repair pass that rewrote every row would be useless.
     */
    public function testNormalizeForStorageLeavesCleanContentUntouched(): void
    {
        $clean = '<p>Already fine.</p><p>Second paragraph.</p>';

        $this->assertSame($clean, $this->postContent->normalizeForStorage($clean));
    }

    public function testNormalizeForStorageUnwrapsAPageBuilderHtmlBlock(): void
    {
        $stored = '<div data-content-type="html" data-appearance="default">'
            . '<p>Real content.</p>'
            . '</div>';

        $this->assertSame('<p>Real content.</p>', $this->postContent->normalizeForStorage($stored));
    }

    public function testNormalizeForStorageDecodesEscapedMarkupInsideTheBlock(): void
    {
        $stored = '<div data-content-type="html">&lt;p&gt;Escaped body&lt;/p&gt;</div>';

        $this->assertSame('<p>Escaped body</p>', $this->postContent->normalizeForStorage($stored));
    }

    /**
     * A body that merely starts and ends with different divs is not a wrapper.
     * Unwrapping it would drop the closing div's content and corrupt the post.
     */
    public function testNormalizeForStorageLeavesUnrelatedDivsAlone(): void
    {
        $stored = '<div class="intro"><p>One</p></div><div class="outro"><p>Two</p></div>';

        $this->assertSame($stored, $this->postContent->normalizeForStorage($stored));
    }

    public function testNormalizeForStorageDoesNotUnwrapNestedHtmlBlocks(): void
    {
        $stored = '<div data-content-type="html">'
            . '<div data-content-type="html"><p>Inner</p></div>'
            . '</div>';

        // The inner payload is itself a Page Builder block, so the outer wrapper
        // is left in place rather than guessing which level is the real body.
        $this->assertStringContainsString('data-content-type="html"', $this->postContent->normalizeForStorage($stored));
    }

    /**
     * The repair applied on read and the repair applied for keeps have to agree,
     * or a one-off cleanup and the render path end up with different ideas of
     * what "repaired" means.
     */
    public function testNormalizeForStorageIsIdempotent(): void
    {
        $stored = '<div data-content-type="html">&lt;p&gt;Body&lt;/p&gt;</div>';

        $once = $this->postContent->normalizeForStorage($stored);
        $twice = $this->postContent->normalizeForStorage($once);

        $this->assertSame($once, $twice);
    }
}
