<?php
/**
 * Copyright © RequestDesk. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace RequestDesk\Blog\Test\Unit\Model\Qa;

use PHPUnit\Framework\TestCase;
use RequestDesk\Blog\Model\Qa\ContentQa;

/**
 * RequestDesk writes a post's Q&A as a <section data-qa> block in the body. Evrig
 * post 292 (2026-11-25) shipped with that block and no FAQPage schema, because
 * schema comes from linked Q&A pairs and nothing turned the block into pairs.
 * The body below is the shape that post carried.
 */
class ContentQaTest extends TestCase
{
    private ContentQa $contentQa;

    private const BODY = '<p>Intro paragraph.</p><h2>Where Evrig helps</h2><p>Closing.</p>'
        . "\n<section class=\"rd-qa\" data-qa>\n<h2>Questions and answers</h2>\n"
        . "<h3>How often does Adobe release Magento security patches now?</h3>\n"
        . "<p>Adobe releases isolated security patches every month on Patch Tuesday.</p>\n"
        . "<h3>What happens if my Magento version reaches end of support?</h3>\n"
        . "<p>The store keeps running, but Adobe stops sending it security patches. Adobe&#x27;s FAQ says so.</p>\n"
        . "<h3>What should a Magento support company handle?</h3>\n"
        . "<p>Security patching and upgrades.</p><p>Also an upgrade roadmap.</p>\n"
        . "</section>\n";

    protected function setUp(): void
    {
        $this->contentQa = new ContentQa();
    }

    public function testExtractsEveryPairInOrder(): void
    {
        $pairs = $this->contentQa->extractPairs(self::BODY);

        $this->assertCount(3, $pairs);
        $this->assertSame('How often does Adobe release Magento security patches now?', $pairs[0]['question']);
        $this->assertSame(
            'Adobe releases isolated security patches every month on Patch Tuesday.',
            $pairs[0]['answer']
        );
    }

    public function testDecodesEntitiesInAnswers(): void
    {
        $pairs = $this->contentQa->extractPairs(self::BODY);

        $this->assertStringContainsString("Adobe's FAQ says so.", $pairs[1]['answer']);
    }

    public function testJoinsMultiParagraphAnswersWithASpace(): void
    {
        $pairs = $this->contentQa->extractPairs(self::BODY);

        $this->assertSame('Security patching and upgrades. Also an upgrade roadmap.', $pairs[2]['answer']);
    }

    public function testSectionHeadingIsNotAQuestion(): void
    {
        foreach ($this->contentQa->extractPairs(self::BODY) as $pair) {
            $this->assertNotSame('Questions and answers', $pair['question']);
        }
    }

    public function testStripRemovesOnlyTheSection(): void
    {
        $stripped = $this->contentQa->stripSection(self::BODY);

        $this->assertStringNotContainsString('data-qa', $stripped);
        $this->assertStringNotContainsString('Patch Tuesday', $stripped);
        $this->assertStringContainsString('<h2>Where Evrig helps</h2><p>Closing.</p>', $stripped);
    }

    public function testBodyWithoutSectionHasNoPairs(): void
    {
        $body = '<h2>FAQ</h2><h3>A heading that looks like a question?</h3><p>Body copy.</p>';

        $this->assertFalse($this->contentQa->hasSection($body));
        $this->assertSame([], $this->contentQa->extractPairs($body));
        $this->assertStringContainsString('A heading that looks like a question?', $this->contentQa->stripSection($body));
    }

    public function testQuestionWithNoAnswerIsSkipped(): void
    {
        $body = '<section data-qa><h3>Asked?</h3><h3>Answered?</h3><p>Yes.</p></section>';

        $pairs = $this->contentQa->extractPairs($body);

        $this->assertCount(1, $pairs);
        $this->assertSame('Answered?', $pairs[0]['question']);
    }

    public function testDuplicateQuestionIsKeptOnce(): void
    {
        $body = '<section data-qa><h3>Same?</h3><p>First.</p><h3>same?</h3><p>Second.</p></section>';

        $this->assertCount(1, $this->contentQa->extractPairs($body));
    }
}
