<?php
/**
 * Copyright (c) 2025 Content Basis LLC
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available at https://opensource.org/licenses/OSL-3.0
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 * @author    Content Basis LLC
 * @copyright Copyright (c) 2025 Content Basis LLC
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License 3.0
 */
declare(strict_types=1);

namespace RequestDesk\Blog\Model\Qa;

/**
 * Reads the Q&A section RequestDesk writes into a post body.
 *
 * RequestDesk posts carry their Q&A as a marked block at the end of the body:
 *
 *   <section class="rd-qa" data-qa>
 *     <h2>Questions and answers</h2>
 *     <h3>Question?</h3>
 *     <p>Answer.</p>
 *     ...
 *   </section>
 *
 * The FAQPage schema is built from Q&A pairs linked to the post, never from
 * the body, so a post that kept its Q&A only in the body shipped with no FAQ
 * schema (Evrig post 292, 2026-11-25). This class finds that block, returns
 * its pairs, and returns the body without it, so the pairs can move into the
 * Q&A library and render once, from one place, with the schema to match.
 *
 * Pure string work with no Magento dependencies, so it is unit-tested alone.
 */
class ContentQa
{
    /**
     * The block is identified by its data-qa attribute, not its class, so a
     * theme that restyles the section does not stop it being found.
     */
    private const SECTION_PATTERN = '#<section\b[^>]*\bdata-qa\b[^>]*>(.*?)</section>#is';

    /**
     * Q&A pairs in the body's Q&A section, in order. Empty when there is no
     * section or it holds no complete pair.
     *
     * @param string $html
     * @return array<int, array{question:string, answer:string}>
     */
    public function extractPairs(string $html): array
    {
        if (!preg_match(self::SECTION_PATTERN, $html, $match)) {
            return [];
        }

        $parts = preg_split('#<h3\b[^>]*>(.*?)</h3>#is', $match[1], -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return [];
        }

        // $parts: [before-first-h3, q1, a1, q2, a2, ...]
        $pairs = [];
        $seen = [];
        for ($i = 1; $i + 1 < count($parts); $i += 2) {
            $question = $this->toText($parts[$i]);
            $answer = $this->toText($parts[$i + 1]);
            if ($question === '' || $answer === '') {
                continue;
            }
            $key = mb_strtolower($question);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $pairs[] = ['question' => $question, 'answer' => $answer];
        }

        return $pairs;
    }

    /**
     * Whether the body has a Q&A section at all.
     *
     * @param string $html
     * @return bool
     */
    public function hasSection(string $html): bool
    {
        return (bool) preg_match(self::SECTION_PATTERN, $html);
    }

    /**
     * The body with its Q&A section removed.
     *
     * @param string $html
     * @return string
     */
    public function stripSection(string $html): string
    {
        return rtrim((string) preg_replace(self::SECTION_PATTERN, '', $html)) . "\n";
    }

    /**
     * Plain text of an HTML fragment: tags dropped, entities decoded,
     * whitespace collapsed. Paragraph breaks inside an answer become spaces.
     *
     * @param string $fragment
     * @return string
     */
    private function toText(string $fragment): string
    {
        $text = preg_replace('#<\s*(br|/p|/li)\b[^>]*>#i', ' ', $fragment);
        $text = strip_tags((string) $text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
