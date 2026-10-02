<?php
/**
 * RequestDesk Blog - Post Search
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength.TooLong

namespace RequestDesk\Blog\Model;

use Magento\Framework\App\ResourceConnection;
use RequestDesk\Blog\Api\Data\PostInterface;

/**
 * Finds published posts for the sidebar's "Search the blog" box.
 *
 * A substring match on title, body and short description. Every word has to
 * appear somewhere in the post, so "hyva checkout" finds a post about Hyva
 * checkout rather than every post that mentions either word.
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
    public static function normalizeQuery($raw): string // phpcs:ignore Magento2.Functions.StaticFunction
    {
        if (!is_string($raw)) {
            return '';
        }

        return trim(mb_substr(trim($raw), 0, self::MAX_QUERY_LENGTH));
    }

    /**
     * The query's words, capped at MAX_WORDS. Every search matches all of them.
     *
     * @param string $query already normalised
     * @return string[]
     */
    public static function words(string $query): array // phpcs:ignore Magento2.Functions.StaticFunction
    {
        return array_slice(preg_split('/\s+/u', $query, -1, PREG_SPLIT_NO_EMPTY) ?: [], 0, self::MAX_WORDS);
    }

    /**
     * A LIKE condition per word on one column, all of which must hold.
     *
     * @param \Magento\Framework\DB\Adapter\AdapterInterface $connection
     * @param string $column
     * @param string[] $words
     * @return string[]
     */
    public static function likeAll($connection, string $column, array $words): array // phpcs:ignore Magento2.Functions.StaticFunction
    {
        return array_map(
            static fn (string $word): string => $connection->quoteInto(
                $column . ' LIKE ?',
                '%' . addcslashes($word, '%_\\') . '%' // phpcs:ignore Magento2.Functions.DiscouragedFunction
            ),
            $words
        );
    }

    /**
     * Ids of the published posts that contain every word of the query.
     *
     * @param string $query already normalised
     * @return int[] empty when the query is empty or nothing matches
     */
    public function findPostIds(string $query): array
    {
        $words = self::words($query);
        if ($words === []) {
            return [];
        }

        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName('requestdesk_blog_post'), ['post_id'])
            ->where('status = ?', PostInterface::STATUS_PUBLISHED);

        foreach ($words as $word) {
            $like = '%' . addcslashes($word, '%_\\') . '%'; // phpcs:ignore Magento2.Functions.DiscouragedFunction
            $select->where(
                $connection->quoteInto('title LIKE ?', $like)
                . ' OR '
                . $connection->quoteInto('content LIKE ?', $like)
                // Amasty searches its teaser too; without it a word only in
                // the short description found nothing here.
                . ' OR '
                . $connection->quoteInto('short_description LIKE ?', $like)
            );
        }

        return array_map('intval', $connection->fetchCol($select));
    }
}
