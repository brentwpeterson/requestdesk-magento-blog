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
 * Drops grid bookmarks that still reference columns this module has since renamed.
 *
 * A ui_bookmark row stores a per-admin-user snapshot of a grid's column order and
 * visibility. Magento never migrates them, so when a listing renames a column the
 * saved view keeps pointing at the old name. The grid then renders from a layout
 * that no longer matches the ui_component: columns come back in the wrong order and
 * ones the stale view does not know about render empty. On the post grid that showed
 * up as an Action column with a header and no links in any row — the actions class
 * was fine the whole time, the saved view was simply from an older schema.
 *
 * Two renames caused it: is_active -> status, and sync_status ->
 * requestdesk_sync_status. Any bookmark whose config still mentions the old names
 * predates those and cannot render correctly.
 *
 * Deliberately narrow: only bookmarks that actually carry a stale column name are
 * removed, so an admin whose saved view is still valid keeps their column choices.
 * Deleting a bookmark only resets that grid to its default view; no content is lost.
 */
class ClearStaleGridBookmarks implements DataPatchInterface
{
    /**
     * Listing namespaces this module owns.
     */
    private const NAMESPACES = [
        'requestdesk_blog_post_listing',
        'requestdesk_blog_author_listing',
    ];

    /**
     * Column names that no longer exist in those listings.
     */
    private const STALE_COLUMNS = [
        'is_active',
        'sync_status',
        'admin_user_id',
    ];

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
        $table = $this->resource->getTableName('ui_bookmark');

        if (!$connection->isTableExists($table)) {
            return $this;
        }

        $stale = [];
        foreach (self::STALE_COLUMNS as $column) {
            // Match the JSON key, not a bare substring, so a legitimately named
            // column that merely contains one of these words is not swept up.
            $stale[] = $connection->quoteInto('config LIKE ?', '%"' . $column . '":%');
        }

        $connection->delete($table, [
            $connection->quoteInto('namespace IN (?)', self::NAMESPACES),
            '(' . implode(' OR ', $stale) . ')',
        ]);

        return $this;
    }
}
