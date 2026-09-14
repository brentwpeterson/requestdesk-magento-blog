<?php
/**
 * RequestDesk Blog - Amasty media path translation
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Model;

/**
 * Moves Amasty blog image references onto the blog's own media folder.
 *
 * Amasty keeps blog images under pub/media/amasty/blog and stores a featured
 * image as a path relative to that folder: post_thumbnail holds "MM26IN.png" or
 * "uploads/2022/05/Evrig_Homepage.png", never the folder itself. Copied across
 * verbatim, Block\ImageUrl resolved those against the media root, so every
 * migrated featured image pointed at /media/<file> and 404'd on the live store.
 *
 * Post bodies point at the same folder directly, in three shapes found in the
 * Evrig data: {{media url='amasty/blog/...'}} directives, .renditions copies
 * ({{media url=.renditions/amasty/blog/...}}), and absolute
 * https://<host>/media/.renditions/amasty/blog/... URLs.
 *
 * The files are copied on the server to pub/media/blog (and
 * pub/media/.renditions/blog), so the blog stops depending on a folder named
 * after the extension it replaced. This class is the one rule for both halves,
 * used when a post is migrated and when an already-migrated post is repaired.
 */
class AmastyMediaPath
{
    /** Media-relative folder the blog's images live in. */
    public const TARGET_DIR = 'blog/';

    /** Media-relative folder Amasty kept them in. */
    public const AMASTY_DIR = 'amasty/blog/';

    /**
     * An amasty/blog/ reference that is unmistakably a media path: straight after
     * a {{media url=}} directive (bare, quoted, or with the quote HTML-encoded by
     * the WYSIWYG editor), or straight after /media/, in both cases optionally
     * through .renditions/. Prose that happens to contain "amasty/blog/", or a
     * link to amasty.com/blog, is left alone.
     */
    private const CONTENT_PATTERN =
        '#(\{\{media\s+url=(?:&quot;|&\#0?39;|["\'])?(?:\.renditions/)?|/media/(?:\.renditions/)?)amasty/blog/#i';

    /**
     * Translate one Amasty image value into a path under the blog media folder.
     *
     * Absolute URLs and root-relative paths are returned untouched: they already
     * say exactly where the file is, and rewriting them would be a guess.
     *
     * @param string|null $value post_thumbnail as Amasty stored it
     * @return string|null null when there is no image
     */
    public static function featuredImage(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (str_starts_with($value, 'http://')
            || str_starts_with($value, 'https://')
            || str_starts_with($value, '/')
        ) {
            return $value;
        }

        if (str_starts_with($value, self::AMASTY_DIR)) {
            $value = substr($value, strlen(self::AMASTY_DIR));
        }

        return self::TARGET_DIR . $value;
    }

    /**
     * Point every media reference to amasty/blog/ in a post body at blog/.
     *
     * @param string $content
     * @return string
     */
    public static function rewriteContent(string $content): string
    {
        $rewritten = preg_replace(self::CONTENT_PATTERN, '$1' . self::TARGET_DIR, $content);

        // preg_replace returns null only on a PCRE failure (backtrack limit on a
        // pathological body). Returning the original would quietly leave that
        // post pointing at the old folder, so it has to stop the post instead.
        if ($rewritten === null) {
            throw new \RuntimeException('Could not rewrite media paths: ' . preg_last_error_msg());
        }

        return $rewritten;
    }
}
