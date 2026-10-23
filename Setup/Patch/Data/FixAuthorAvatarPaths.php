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

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Filesystem;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Repairs avatars saved as a bare file name.
 *
 * From 1.4.0 to 1.13.0 the author form stored an uploaded avatar as just its
 * file name ("1776855249324.jpeg"), while the file itself sits under
 * media/requestdesk/blog/author. The storefront resolves avatars against the
 * media root, so every uploaded avatar pointed at a file that is not there.
 *
 * Only rows with no slash at all are touched, and only when the file really is
 * in the uploader's folder, so URLs and imported paths are left alone. Safe to
 * run again: a repaired row has a slash in it.
 */
class FixAuthorAvatarPaths implements DataPatchInterface
{
    /**
     * Must match the AuthorImageUploader basePath in etc/di.xml.
     */
    private const AVATAR_DIR = 'requestdesk/blog/author';

    /**
     * @param ResourceConnection $resource
     * @param Filesystem $filesystem
     */
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly Filesystem $filesystem
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
        $table = $this->resource->getTableName('requestdesk_blog_author');
        if (!$connection->isTableExists($table)) {
            return $this;
        }

        $media = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA);
        $rows = $connection->fetchPairs(
            $connection->select()
                ->from($table, ['author_id', 'avatar'])
                ->where("avatar IS NOT NULL AND avatar <> '' AND avatar NOT LIKE '%/%'")
        );

        foreach ($rows as $authorId => $avatar) {
            $path = self::AVATAR_DIR . '/' . trim((string)$avatar);
            if ($media->isExist($path)) {
                $connection->update($table, ['avatar' => $path], ['author_id = ?' => (int)$authorId]);
            }
        }

        return $this;
    }
}
