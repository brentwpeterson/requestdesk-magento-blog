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
 * Moves blog authors off Magento admin accounts and onto their own entity.
 *
 * Before: requestdesk_blog_author_profile was keyed by admin_user_id, and
 * requestdesk_blog_post.author_id held an admin_user id — so only staff with a
 * Magento login could be credited as an author.
 *
 * After: every author is a row in requestdesk_blog_author with its own id, and
 * admin_user_id is an optional link. Posts point at the new ids.
 *
 * The patch is idempotent: it skips authors it has already created, and only
 * remaps posts whose author_id still looks like an admin-user reference.
 */
class MigrateAuthorProfiles implements DataPatchInterface
{
    private const OLD_TABLE = 'requestdesk_blog_author_profile';
    private const NEW_TABLE = 'requestdesk_blog_author';
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
        return [];
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
        $newTable = $this->resource->getTableName(self::NEW_TABLE);
        $postTable = $this->resource->getTableName(self::POST_TABLE);

        if (!$connection->isTableExists($newTable)) {
            return $this;
        }

        $connection->beginTransaction();
        try {
            // admin_user_id => new author_id, for every author already migrated.
            $map = [];
            foreach ($connection->fetchAll(
                $connection->select()
                    ->from($newTable, ['author_id', 'admin_user_id'])
                    ->where('admin_user_id IS NOT NULL')
            ) as $row) {
                $map[(int) $row['admin_user_id']] = (int) $row['author_id'];
            }

            $map = $this->migrateProfiles($map);
            $map = $this->createAuthorsForOrphanPosts($map);
            $this->remapPosts($map);

            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }

        // Posts whose author never resolved keep their legacy free-text name; the
        // stale admin-user id would otherwise point at an unrelated author row.
        $connection->update($postTable, ['author_id' => null], ['author_id NOT IN (?)' => $this->authorIds()]);

        return $this;
    }

    /**
     * Copy each old profile into the new author table.
     *
     * @param array<int, int> $map
     * @return array<int, int>
     */
    private function migrateProfiles(array $map): array
    {
        $connection = $this->resource->getConnection();
        $oldTable = $this->resource->getTableName(self::OLD_TABLE);
        if (!$connection->isTableExists($oldTable)) {
            return $map;
        }

        $profiles = $connection->fetchAll(
            $connection->select()->from($oldTable)->join(
                ['u' => $this->resource->getTableName('admin_user')],
                'u.user_id = ' . $connection->quoteIdentifier($oldTable . '.admin_user_id'),
                ['firstname', 'lastname', 'username']
            )
        );

        foreach ($profiles as $profile) {
            $adminUserId = (int) $profile['admin_user_id'];
            if (isset($map[$adminUserId])) {
                continue;
            }

            $name = trim((string) ($profile['display_name'] ?? ''));
            if ($name === '') {
                $name = trim(($profile['firstname'] ?? '') . ' ' . ($profile['lastname'] ?? ''));
            }
            if ($name === '') {
                $name = (string) $profile['username'];
            }

            $map[$adminUserId] = $this->insertAuthor([
                'admin_user_id' => $adminUserId,
                'name' => $name,
                'bio' => $profile['bio'] ?? null,
                'avatar' => $profile['avatar'] ?? null,
                'url' => $profile['url'] ?? null,
            ]);
        }

        return $map;
    }

    /**
     * Some posts reference an admin user that never had a profile row. Give those
     * authors a record too, so the byline survives the migration.
     *
     * @param array<int, int> $map
     * @return array<int, int>
     */
    private function createAuthorsForOrphanPosts(array $map): array
    {
        $connection = $this->resource->getConnection();

        $referenced = array_map('intval', $connection->fetchCol(
            $connection->select()
                ->distinct()
                ->from($this->resource->getTableName(self::POST_TABLE), ['author_id'])
                ->where('author_id IS NOT NULL')
        ));

        $missing = array_diff($referenced, array_keys($map), array_values($map));
        if ($missing === []) {
            return $map;
        }

        $users = $connection->fetchAll(
            $connection->select()
                ->from($this->resource->getTableName('admin_user'), ['user_id', 'firstname', 'lastname', 'username'])
                ->where('user_id IN (?)', $missing)
        );

        foreach ($users as $user) {
            $name = trim(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? ''));
            if ($name === '') {
                $name = (string) $user['username'];
            }
            $map[(int) $user['user_id']] = $this->insertAuthor([
                'admin_user_id' => (int) $user['user_id'],
                'name' => $name,
            ]);
        }

        return $map;
    }

    /**
     * Repoint posts from admin-user ids to blog-author ids.
     *
     * @param array<int, int> $map
     * @return void
     */
    private function remapPosts(array $map): void
    {
        $connection = $this->resource->getConnection();
        $postTable = $this->resource->getTableName(self::POST_TABLE);

        foreach ($map as $adminUserId => $authorId) {
            $connection->update($postTable, ['author_id' => $authorId], ['author_id = ?' => $adminUserId]);
        }
    }

    /**
     * Insert an author, deriving a unique url_key from the name.
     *
     * @param array<string, mixed> $data
     * @return int
     */
    private function insertAuthor(array $data): int
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName(self::NEW_TABLE);

        $data['url_key'] = $this->uniqueUrlKey($this->slugify((string) $data['name']));
        $connection->insert($table, $data);

        return (int) $connection->lastInsertId($table);
    }

    /**
     * Every author id that exists.
     *
     * @return int[]
     */
    private function authorIds(): array
    {
        $connection = $this->resource->getConnection();
        $ids = array_map('intval', $connection->fetchCol(
            $connection->select()->from($this->resource->getTableName(self::NEW_TABLE), ['author_id'])
        ));
        // An empty IN () is invalid SQL; 0 never matches a real id.
        return $ids !== [] ? $ids : [0];
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
        $table = $this->resource->getTableName(self::NEW_TABLE);
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
