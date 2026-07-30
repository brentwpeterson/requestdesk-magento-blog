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

namespace RequestDesk\Blog\Setup\Patch\Data;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Backfills blog authors from the byline string carried on each post.
 *
 * MigrateAuthorProfiles only ever read requestdesk_blog_author_profile. On any
 * install where that table was empty — which is every install where authors were
 * imported from RequestDesk rather than created from Magento admin accounts — it
 * reported success and migrated nothing: requestdesk_blog_author stayed empty and
 * no post got an author_id. The real author data was sitting in the
 * requestdesk_blog_post.author VARCHAR the whole time, which that patch never read.
 *
 * The visible symptom was a post form whose Author select had nothing to pick and
 * always read "-- None --", while the grid still showed a name because the grid
 * reads the legacy VARCHAR. That masked the problem.
 *
 * This patch creates one author per distinct non-empty byline and points the posts
 * at it. It is idempotent: an existing author with the same name is reused, and
 * posts that already carry an author_id are left alone. The legacy VARCHAR is
 * deliberately NOT dropped here — it stays as the fallback the grid and templates
 * still read, and goes away with the follow-up release that adds the author_id FK.
 */
class BackfillAuthorsFromPosts implements DataPatchInterface
{
    private const AUTHOR_TABLE = 'requestdesk_blog_author';
    private const POST_TABLE = 'requestdesk_blog_post';

    /**
     * @param ResourceConnection $resource
     */
    public function __construct(
        private readonly ResourceConnection $resource
    ) {
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies(): array
    {
        return [MigrateAuthorProfiles::class];
    }

    /**
     * @inheritdoc
     */
    public function getAliases(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function apply(): self
    {
        $connection = $this->resource->getConnection();
        $authorTable = $this->resource->getTableName(self::AUTHOR_TABLE);
        $postTable = $this->resource->getTableName(self::POST_TABLE);

        if (!$connection->isTableExists($authorTable) || !$connection->isTableExists($postTable)) {
            return $this;
        }

        // Only posts that still have no author_id but do carry a byline.
        $bylines = $connection->fetchCol(
            $connection->select()
                ->distinct()
                ->from($postTable, ['author'])
                ->where('author IS NOT NULL')
                ->where('TRIM(author) <> ?', '')
                ->where('author_id IS NULL')
        );

        foreach ($bylines as $byline) {
            $name = trim((string) $byline);
            if ($name === '') {
                continue;
            }

            $authorId = $this->findOrCreateAuthor($name);
            if ($authorId === 0) {
                continue;
            }

            $connection->update(
                $postTable,
                ['author_id' => $authorId],
                ['author_id IS NULL', 'TRIM(author) = ?' => $name]
            );
        }

        return $this;
    }

    /**
     * Reuse an author with this exact name, otherwise create one.
     *
     * @param string $name
     * @return int
     */
    private function findOrCreateAuthor(string $name): int
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName(self::AUTHOR_TABLE);

        $existing = (int) $connection->fetchOne(
            $connection->select()
                ->from($table, ['author_id'])
                ->where('name = ?', $name)
                ->limit(1)
        );
        if ($existing) {
            return $existing;
        }

        $connection->insert($table, [
            'name' => $name,
            'admin_user_id' => null,
            'url_key' => $this->uniqueUrlKey($this->slugify($name)),
        ]);

        return (int) $connection->lastInsertId($table);
    }

    /**
     * Normalize a name into a URL key.
     *
     * @param string $value
     * @return string
     */
    private function slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = (string) preg_replace('/[^a-z0-9]+/', '-', $value);
        $value = trim($value, '-');
        return $value !== '' ? $value : 'author';
    }

    /**
     * Suffix -2, -3, ... until the url_key is free.
     *
     * @param string $base
     * @return string
     */
    private function uniqueUrlKey(string $base): string
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName(self::AUTHOR_TABLE);
        $candidate = $base;
        $i = 2;
        while ((int) $connection->fetchOne(
            $connection->select()->from($table, ['author_id'])->where('url_key = ?', $candidate)->limit(1)
        )) {
            $candidate = $base . '-' . $i++;
        }
        return $candidate;
    }
}
