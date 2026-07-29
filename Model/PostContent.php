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

namespace RequestDesk\Blog\Model;

use Magento\Cms\Model\Template\FilterProvider;

/**
 * Turns stored post content into something safe to render or to excerpt.
 *
 * Two problems live in the stored data. Page Builder wraps pasted markup in a
 * data-content-type="html" block and escapes the payload, so the browser prints
 * &lt;p&gt; instead of a paragraph. And imported posts carry editor CSS and
 * WordPress block comments that leak into excerpts.
 */
class PostContent
{
    /**
     * @param FilterProvider $filterProvider
     */
    public function __construct(
        private readonly FilterProvider $filterProvider
    ) {
    }

    /**
     * Content ready to echo on the frontend: markup unescaped and directives
     * ({{media url=...}}, {{store url=...}}) resolved.
     *
     * @param string|null $content
     * @return string
     */
    public function render(?string $content): string
    {
        $html = (string) $content;
        if ($html === '') {
            return '';
        }

        try {
            // The CMS page filter resolves directives and, via Page Builder's
            // Template filter, unescapes data-content-type="html" blocks — so no
            // unescaping of our own is needed on this path.
            return $this->filterProvider->getPageFilter()->filter($html);
        } catch (\Throwable $e) {
            // A malformed directive must not blank the whole post body.
            return $html;
        }
    }

    /**
     * Content flattened to plain text, for excerpts and meta descriptions.
     *
     * @param string|null $content
     * @return string
     */
    public function toPlainText(?string $content): string
    {
        $html = $this->decodeEscapedMarkup((string) $content);

        // strip_tags() removes <script>/<style> tags but keeps the text between
        // them, which is how editor CSS such as "#html-body {...}" ends up in an
        // excerpt. Drop those elements whole, first.
        $html = (string) preg_replace('#<(script|style)\b[^>]*>.*?</\1>#si', ' ', $html);

        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    /**
     * A trimmed plain-text excerpt.
     *
     * @param string|null $content
     * @param int $length
     * @return string
     */
    public function excerpt(?string $content, int $length = 180): string
    {
        $text = $this->toPlainText($content);
        if (mb_strlen($text) <= $length) {
            return $text;
        }
        // Prefer a word boundary so the excerpt does not cut mid-word.
        $clipped = mb_substr($text, 0, $length);
        $lastSpace = mb_strrpos($clipped, ' ');
        if ($lastSpace !== false && $lastSpace > (int) ($length * 0.6)) {
            $clipped = mb_substr($clipped, 0, $lastSpace);
        }
        return rtrim($clipped) . '…';
    }

    /**
     * Unescape markup that was stored HTML-encoded.
     *
     * @param string $content
     * @return string
     */
    private function decodeEscapedMarkup(string $content): string
    {
        if ($content === '' || !str_contains($content, '&lt;')) {
            return $content;
        }

        // The usual case: a Page Builder html block holding an escaped payload.
        $decoded = (string) preg_replace_callback(
            '#(<div[^>]*data-content-type="html"[^>]*>)(.*?)(</div>)#si',
            static function (array $matches): string {
                if (!str_contains($matches[2], '&lt;')) {
                    return $matches[0];
                }
                return $matches[1]
                    . html_entity_decode($matches[2], ENT_QUOTES | ENT_HTML5, 'UTF-8')
                    . $matches[3];
            },
            $content
        );

        // Content escaped in full, with no real markup around it.
        if ($decoded === $content && !str_contains($content, '<')) {
            return html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return $decoded;
    }
}
