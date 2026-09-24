<?php
/**
 * RequestDesk Blog - Post Search
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Model;

use Magento\Framework\App\ResourceConnection;
use RequestDesk\Blog\Api\Data\PostInterface;

/**
 * Finds published posts for the sidebar's "Search the blog" box.
 *
 * A substring match on title and body. Every word has to appear somewhere in
 * the post, so "hyva
 * checkout" finds a post about Hyva checkout rather than every post that
 * mentions either word.
 */
class PostSearch
{
    /** Same cap as the search box's maxlength. */
    public const MAX_QUERY_LENGTH = 100;

    /** Words past this are ignored, so a pasted paragraph cannot build a huge query. */
    private const MAX_WORDS = 10;

    /**
     * @param ResourceConnection $resource
     */
    public function __construct(
        private readonly ResourceConnection $resource
    ) {
    }

    /**
     * The search box's text, trimmed and capped, or '' for nothing usable.
     *
     * @param mixed $raw
     * @return string
     */
    public static function normalizeQuery($raw): string
    {
        if (!is_string($raw)) {
            return '';
        }

        return trim(mb_substr(trim($raw), 0, self::MAX_QUERY_LENGTH));
    }

    /**
     * Ids of the published posts that contain every word of the query.
     *
     * @param string $query already normalised
     * @return int[] empty when the query is empty or nothing matches
     */
    public function findPostIds(string $query): array
    {
        $words = array_slice(preg_split('/\s+/u', $query, -1, PREG_SPLIT_NO_EMPTY) ?: [], 0, self::MAX_WORDS);
        if ($words === []) {
            return [];
        }

        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName('requestdesk_blog_post'), ['post_id'])
            ->where('status = ?', PostInterface::STATUS_PUBLISHED);

        foreach ($words as $word) {
            $like = '%' . addcslashes($word, '%_\\') . '%';
            $select->where(
                $connection->quoteInto('title LIKE ?', $like)
                . ' OR '
                . $connection->quoteInto('content LIKE ?', $like)
            );
        }

        return array_map('intval', $connection->fetchCol($select));
    }
}
