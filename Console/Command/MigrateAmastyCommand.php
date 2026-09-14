<?php
/**
 * RequestDesk Blog - Migrate Amasty Blog posts into RequestDesk_Blog
 *
 * Reads Amasty Blog content straight from its database tables (no Amasty code
 * required — the module does not even need to be installed) and creates
 * equivalent RequestDesk_Blog posts. This makes the migration robust: you can
 * migrate off Amasty and then remove it entirely.
 *
 * Source tables: amasty_blog_posts (+ _tag / _tags_store, _author_store,
 * _posts_category, _categories, _categories_store).
 *
 * Categories map onto NATIVE Magento categories rather than arriving as a second
 * taxonomy, so the blog reuses Magento's own admin, URL rewrites and store
 * scoping. Everything imported hangs off one dedicated parent with
 * include_in_menu and is_anchor off, so blog categories stay out of product
 * navigation. See AmastyCategoryMapper.
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Console\Command;

use Magento\Framework\App\Area;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\State;
use RequestDesk\Blog\Api\PostRepositoryInterface;
use RequestDesk\Blog\Model\AmastyCategoryMapper;
use RequestDesk\Blog\Model\AmastyMediaPath;
use RequestDesk\Blog\Model\AuthorResolver;
use RequestDesk\Blog\Model\PostCategoryResolver;
use RequestDesk\Blog\Model\PostFactory;
use RequestDesk\Blog\Model\TagResolver;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class MigrateAmastyCommand extends Command
{
    private const OPT_LIMIT = 'limit';
    private const OPT_DRY_RUN = 'dry-run';
    private const OPT_PARENT = 'parent-category';

    /** Amasty status: 2 = published/enabled. */
    private const AMASTY_STATUS_PUBLISHED = 2;

    /** Amasty stores localized text under store_id 0 for the default scope. */
    private const DEFAULT_STORE = 0;

    /**
     * Candidate names for Amasty's teaser column, most likely first. Probed the
     * same way as the author bio/avatar columns, and for the same reason: we read
     * Amasty's tables without its code installed and the spelling has moved
     * between releases, so a hard-coded name would fatal the whole migration on
     * a version that calls it something else.
     */
    private const AMASTY_SHORT_COLUMNS = ['short_content', 'short_description', 'post_teaser', 'teaser', 'excerpt'];

    /** Resolved once per run; null means the source has no such column. */
    private ?string $amastyShortColumn = null;

    /** Separate flag, because null is a real answer and not "not looked yet". */
    private bool $amastyShortColumnResolved = false;

    /**
     * @param State $appState
     * @param ResourceConnection $resource
     * @param PostRepositoryInterface $postRepository
     * @param PostFactory $postFactory
     * @param TagResolver $tagResolver
     * @param AuthorResolver $authorResolver
     */
    public function __construct(
        private readonly State $appState,
        private readonly ResourceConnection $resource,
        private readonly PostRepositoryInterface $postRepository,
        private readonly PostFactory $postFactory,
        private readonly TagResolver $tagResolver,
        private readonly AuthorResolver $authorResolver,
        private readonly AmastyCategoryMapper $categoryMapper,
        private readonly PostCategoryResolver $postCategoryResolver
    ) {
        parent::__construct();
    }

    /**
     * @inheritdoc
     */
    protected function configure(): void
    {
        $this->setName('requestdesk:blog:migrate-amasty');
        $this->setDescription('Migrate Amasty Blog posts into the RequestDesk blog (reads Amasty DB tables directly).');
        $this->addOption(self::OPT_LIMIT, 'l', InputOption::VALUE_REQUIRED, 'Max posts to migrate (default: all published)');
        $this->addOption(self::OPT_DRY_RUN, null, InputOption::VALUE_NONE, 'Report what would migrate without writing');
        $this->addOption(
            self::OPT_PARENT,
            'p',
            InputOption::VALUE_REQUIRED,
            'Native category id to create imported blog categories under (default: find or create "Blog" under the store root)'
        );
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $connection = $this->resource->getConnection();

        if (!$connection->isTableExists($this->resource->getTableName('amasty_blog_posts'))) {
            $output->writeln('<error>No amasty_blog_posts table in this database — nothing to migrate.</error>');
            return Command::FAILURE;
        }

        try {
            $this->appState->setAreaCode(Area::AREA_ADMINHTML);
        } catch (\Exception $e) {
            // area already set — fine
        }

        $limit = $input->getOption(self::OPT_LIMIT) !== null ? (int) $input->getOption(self::OPT_LIMIT) : 0;
        $dryRun = (bool) $input->getOption(self::OPT_DRY_RUN);

        // Resolve the one parent every imported blog category hangs under. Doing
        // this up front means a bad --parent-category fails before anything is
        // written, rather than half way through the run.
        $parentOption = $input->getOption(self::OPT_PARENT);
        $rootParentId = 0;
        if ($parentOption !== null) {
            if (!is_numeric($parentOption) || (int) $parentOption <= 0) {
                $output->writeln(sprintf(
                    '<error>--parent-category must be a positive category id, got "%s".</error>',
                    $parentOption
                ));
                return Command::FAILURE;
            }
            $rootParentId = (int) $parentOption;
            if (!$this->categoryMapper->categoryExists($rootParentId)) {
                $output->writeln(sprintf(
                    '<error>--parent-category %d does not exist.</error>',
                    $rootParentId
                ));
                return Command::FAILURE;
            }
        }
        $canMapCategories = $this->categoryMapper->sourceExists();
        if ($canMapCategories && !$dryRun && $rootParentId <= 0) {
            $rootParentId = (int) $this->categoryMapper->getOrCreateRootParent();
            if ($rootParentId <= 0) {
                $output->writeln('<error>Could not resolve or create the parent blog category.</error>');
                return Command::FAILURE;
            }
        }
        if (!$canMapCategories) {
            $output->writeln('<comment>No amasty_blog_categories table — categories will be skipped.</comment>');
        }

        $select = $connection->select()
            ->from($this->resource->getTableName('amasty_blog_posts'))
            ->where('status = ?', self::AMASTY_STATUS_PUBLISHED)
            ->order('post_id ASC');
        if ($limit > 0) {
            $select->limit($limit);
        }
        $rows = $connection->fetchAll($select);

        $migrated = 0;
        $skipped = 0;
        $backfilled = 0;
        $datesFixed = 0;
        $mediaFixed = 0;
        $failed = 0;
        $tagLinks = 0;
        $categoryLinks = 0;
        /** @var array<int,true> $previewCategoryIds distinct source categories a dry run would map */
        $previewCategoryIds = [];
        $previewCategoryLinks = 0;
        /** @var array<int,true> $authorsSeen author_ids touched, for the summary count */
        $authorsSeen = [];

        foreach ($rows as $row) {
            $urlKey = (string) ($row['url_key'] ?? '');
            $title = (string) ($row['title'] ?? '');
            $srcId = (int) ($row['post_id'] ?? 0);

            // A post migrated before short_description existed is already here but
            // has that column empty, and the old unconditional skip meant no
            // re-run could ever fill it - the only way to get the field was to
            // delete the post and import it again. So an existing post is now
            // examined rather than passed over: if the source has a teaser and
            // ours does not, that one column is filled in place. Everything else
            // about the post is left exactly as it is.
            $existingId = $this->findPostIdByUrlKey($urlKey);
            if ($existingId > 0) {
                $touched = false;

                $sourceShort = $this->shortDescriptionFrom($row);
                if ($sourceShort !== null && $this->backfillShortDescription($existingId, $sourceShort, $dryRun)) {
                    $output->writeln(
                        $dryRun
                            ? "  would backfill short description: {$urlKey}"
                            : "  backfilled short description: {$urlKey}"
                    );
                    $backfilled++;
                    $touched = true;
                }

                // Posts migrated before the date was carried across are all
                // stamped with the moment the import ran, which collapses years
                // of archive onto one or two days. The two backfills are
                // independent - this used to be an if/else, so a post that only
                // needed its date was reported as "skip (exists)" and left wrong.
                $sourcePublishedAt = $this->publishedAtFrom($row);
                if ($sourcePublishedAt !== null
                    && $this->backfillPublishDate($existingId, $sourcePublishedAt, $dryRun)
                ) {
                    $output->writeln(
                        $dryRun
                            ? "  would set publish date {$sourcePublishedAt}: {$urlKey}"
                            : "  set publish date {$sourcePublishedAt}: {$urlKey}"
                    );
                    $datesFixed++;
                    $touched = true;
                }

                // Posts migrated before 1.10.2 carry Amasty's image paths as-is,
                // which resolve to /media/<file> and 404 once the files live in
                // pub/media/blog. Same third independent backfill: featured image
                // and body links, repaired in place.
                if ($this->backfillMediaPaths($existingId, $row['post_thumbnail'] ?? null, $dryRun)) {
                    $output->writeln(
                        $dryRun
                            ? "  would move image paths to blog/: {$urlKey}"
                            : "  moved image paths to blog/: {$urlKey}"
                    );
                    $mediaFixed++;
                    $touched = true;
                }

                if (!$touched) {
                    $output->writeln("  skip (exists): {$urlKey}");
                    $skipped++;
                }
                continue;
            }

            if ($dryRun) {
                // Report the category work rather than silently skipping it.
                // This used to return here before categories were looked at,
                // so a dry run always printed zero categories and read as
                // "this will not migrate any" - which is not what it meant.
                // Reading the source is safe; mapCategory is what creates, and
                // it is deliberately not called here.
                $srcCategoryIds = $canMapCategories
                    ? $this->categoryMapper->getSourceCategoryIds($srcId)
                    : [];
                foreach ($srcCategoryIds as $srcCategoryId) {
                    $previewCategoryIds[$srcCategoryId] = true;
                }
                $previewCategoryLinks += count($srcCategoryIds);

                $output->writeln(sprintf(
                    '  would migrate: %s  [%s]%s',
                    $title,
                    $urlKey,
                    $srcCategoryIds === [] ? '' : sprintf('  (%d category link(s))', count($srcCategoryIds))
                ));
                $migrated++;
                continue;
            }

            // Resolve everything that CREATES shared records before the
            // transaction opens: tags, authors and categories are all
            // get-or-create keyed on name or url_key, so a leftover from a
            // failed post is harmless and gets reused rather than duplicated.
            // Holding them inside the transaction instead would drag catalog
            // category writes into a rollback, which is not something the
            // category repository is safe to be wrapped in.
            $tagIds = [];
            $categoryIds = [];
            try {
                foreach ($this->tagNames($srcId) as $name) {
                    $ourTagId = $this->tagResolver->getOrCreateByName($name);
                    if ($ourTagId) {
                        $tagIds[] = $ourTagId;
                    }
                }
                if ($canMapCategories) {
                    foreach ($this->categoryMapper->getSourceCategoryIds($srcId) as $srcCategoryId) {
                        $nativeId = $this->categoryMapper->mapCategory($srcCategoryId, $rootParentId);
                        if ($nativeId) {
                            $categoryIds[] = $nativeId;
                        }
                    }
                }
            } catch (\Exception $e) {
                $output->writeln("  <error>failed: {$urlKey} — {$e->getMessage()}</error>");
                $failed++;
                continue;
            }

            // The post and its links are one unit. Before this, a throw after
            // the save left the post written but unlinked, and because the
            // skip-if-exists check above then matched it, a re-run would pass
            // over it forever - a post silently stranded with no tags and no
            // categories. Rolling back means a failure leaves nothing behind
            // and the next run genuinely retries it.
            $connection->beginTransaction();
            try {
                $post = $this->postFactory->create();
                $post->setTitle($title !== '' ? $title : 'Untitled');
                $post->setContent(AmastyMediaPath::rewriteContent((string) ($row['full_content'] ?? '')));
                $post->setShortDescription($this->shortDescriptionFrom($row));
                $post->setUrlKey($urlKey);
                $post->setMetaTitle((string) ($row['meta_title'] ?: $title));
                $post->setMetaDescription((string) ($row['meta_description'] ?? ''));
                $post->setFeaturedImage(AmastyMediaPath::featuredImage($row['post_thumbnail'] ?? null));

                $amastyAuthor = $this->authorDetails((int) ($row['author_id'] ?? 0));
                $post->setAuthor($amastyAuthor['name']);
                $blogAuthorId = $this->authorResolver->getOrCreateByName(
                    $amastyAuthor['name'],
                    $amastyAuthor['bio'],
                    $amastyAuthor['avatar']
                );
                if ($blogAuthorId && !isset($authorsSeen[$blogAuthorId])) {
                    $authorsSeen[$blogAuthorId] = true;
                }
                $post->setAuthorId($blogAuthorId ?: null);

                $post->setStatus(1); // published
                $post->setStoreId(0);

                // Without this the row takes created_at's CURRENT_TIMESTAMP
                // default, so a ten-year archive imports as published today and
                // every freshness signal on the blog is wrong.
                $sourcePublishedAt = $this->publishedAtFrom($row);
                if ($sourcePublishedAt !== null) {
                    $post->setCreatedAt($sourcePublishedAt);
                }

                $saved = $this->postRepository->save($post);
                $savedId = (int) $saved->getPostId();

                if ($tagIds) {
                    $this->tagResolver->syncForPost($savedId, $tagIds);
                }

                if ($categoryIds) {
                    // syncForPost replaces rather than appends, which is the
                    // agreed behaviour: the Amasty data is the source of truth
                    // for a migrated post, not whatever was assigned before.
                    $this->postCategoryResolver->syncForPost($savedId, $categoryIds);
                }

                $connection->commit();

                // Counted only after the commit, so the summary reports what is
                // actually in the database rather than what was attempted.
                $tagLinks += count($tagIds);
                $categoryLinks += count($categoryIds);
                $output->writeln("  migrated: {$title}  [{$urlKey}]");
                $migrated++;
            } catch (\Exception $e) {
                $connection->rollBack();
                $output->writeln("  <error>failed: {$urlKey} — {$e->getMessage()}</error>");
                $failed++;
            }
        }

        $output->writeln('');
        $output->writeln($dryRun ? '<info>DRY RUN — nothing written.</info>' : '<info>Migration complete.</info>');
        $output->writeln("  posts migrated:  {$migrated}");
        $output->writeln("  posts skipped:   {$skipped}  (already present)");
        $output->writeln("  short descs:     {$backfilled}  (filled in on posts already present)");
        $output->writeln("  publish dates:   {$datesFixed}  (corrected on posts already present)");
        $output->writeln("  image paths:     {$mediaFixed}  (moved to blog/ on posts already present)");
        $output->writeln("  posts failed:    {$failed}  (rolled back, safe to re-run)");
        $output->writeln("  tag links:       {$tagLinks}");
        $output->writeln('  authors linked:  ' . count($authorsSeen));
        if ($dryRun) {
            $output->writeln("  category links:  {$previewCategoryLinks}  (would be created)");
            $output->writeln(
                '  categories:      ' . count($previewCategoryIds) . '  distinct Amasty category(ies) to map'
            );
        } else {
            $output->writeln("  category links:  {$categoryLinks}");
        }
        if (!$dryRun) {
            $output->writeln('  categories made: ' . count($this->categoryMapper->getMapping())
                . ($rootParentId > 0 ? " (under category {$rootParentId})" : ''));
        }

        return Command::SUCCESS;
    }

    /**
     * Amasty author name, bio and avatar for the default store scope.
     *
     * Columns are probed rather than assumed. We read Amasty's tables directly
     * without its code installed, and the bio/avatar column names have moved
     * between Amasty releases; a hard-coded SELECT would fatal the whole
     * migration on a version that spells them differently. Anything we cannot
     * find comes back empty and the author is still created from the name.
     *
     * @param int $authorId
     * @return array{name:string, bio:string, avatar:string}
     */
    private function authorDetails(int $authorId): array
    {
        $empty = ['name' => '', 'bio' => '', 'avatar' => ''];
        if (!$authorId) {
            return $empty;
        }

        $connection = $this->resource->getConnection();
        $storeTable = $this->resource->getTableName('amasty_blog_author_store');
        if (!$connection->isTableExists($storeTable)) {
            return $empty;
        }

        $storeColumns = array_keys($connection->describeTable($storeTable));
        $bioColumn = $this->firstExisting($storeColumns, ['description', 'bio', 'content']);

        $select = $connection->select()
            ->from($storeTable, array_values(array_filter(['name', $bioColumn])))
            ->where('author_id = ?', $authorId)
            ->where('store_id = ?', self::DEFAULT_STORE)
            ->limit(1);
        $row = $connection->fetchRow($select) ?: [];

        $result = [
            'name' => trim((string) ($row['name'] ?? '')),
            'bio' => $bioColumn ? trim((string) ($row[$bioColumn] ?? '')) : '',
            'avatar' => '',
        ];

        $authorTable = $this->resource->getTableName('amasty_blog_author');
        if ($connection->isTableExists($authorTable)) {
            $authorColumns = array_keys($connection->describeTable($authorTable));
            $avatarColumn = $this->firstExisting($authorColumns, ['image', 'avatar', 'thumbnail']);
            if ($avatarColumn) {
                $result['avatar'] = trim((string) $connection->fetchOne(
                    $connection->select()
                        ->from($authorTable, [$avatarColumn])
                        ->where('author_id = ?', $authorId)
                        ->limit(1)
                ));
            }
        }

        return $result;
    }

    /**
     * First candidate column that actually exists on the table.
     *
     * @param string[] $available
     * @param string[] $candidates
     * @return string|null
     */
    private function firstExisting(array $available, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (in_array($candidate, $available, true)) {
                return $candidate;
            }
        }
        return null;
    }

    /**
     * Amasty tag names on a post (default store scope).
     *
     * @param int $srcPostId
     * @return string[]
     */
    private function tagNames(int $srcPostId): array
    {
        $connection = $this->resource->getConnection();

        // Amasty's tag tables are not guaranteed to be there. A store that never
        // used tags, or a partial install, has the posts table without them, and
        // an unguarded join fails EVERY post rather than the one feature - which
        // is a whole migration lost to something nobody was even asking for.
        foreach (['amasty_blog_posts_tag', 'amasty_blog_tags_store'] as $table) {
            if (!$connection->isTableExists($this->resource->getTableName($table))) {
                return [];
            }
        }

        $select = $connection->select()
            ->from(['pt' => $this->resource->getTableName('amasty_blog_posts_tag')], [])
            ->join(
                ['ts' => $this->resource->getTableName('amasty_blog_tags_store')],
                'ts.tag_id = pt.tag_id AND ts.store_id = ' . self::DEFAULT_STORE,
                ['name']
            )
            ->where('pt.post_id = ?', $srcPostId);
        return array_filter(array_map('trim', $connection->fetchCol($select)));
    }


    /**
     * The RequestDesk post already holding this url_key, or 0. Keeps re-runs
     * idempotent, and gives the caller the id it needs to backfill in place.
     *
     * @param string $urlKey
     * @return int
     */
    private function findPostIdByUrlKey(string $urlKey): int
    {
        if ($urlKey === '') {
            return 0;
        }
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName('requestdesk_blog_post'), ['post_id'])
            ->where('url_key = ?', $urlKey)
            ->limit(1);
        return (int) $connection->fetchOne($select);
    }

    /**
     * Which column on amasty_blog_posts holds the teaser, if any.
     *
     * @return string|null
     */
    private function amastyShortColumn(): ?string
    {
        if (!$this->amastyShortColumnResolved) {
            $this->amastyShortColumnResolved = true;

            try {
                $connection = $this->resource->getConnection();
                $columns = array_keys(
                    $connection->describeTable($this->resource->getTableName('amasty_blog_posts'))
                );
                $this->amastyShortColumn = $this->firstExisting($columns, self::AMASTY_SHORT_COLUMNS);
            } catch (\Exception $e) {
                // No readable source table. The posts themselves are what this
                // command is for, so a missing teaser column must not stop it.
                $this->amastyShortColumn = null;
            }
        }

        return $this->amastyShortColumn;
    }

    /**
     * The teaser on one Amasty row, or null when there is none worth writing.
     *
     * @param array<string, mixed> $row
     * @return string|null
     */
    private function shortDescriptionFrom(array $row): ?string
    {
        $column = $this->amastyShortColumn();
        if ($column === null) {
            return null;
        }

        $value = trim((string) ($row[$column] ?? ''));

        return $value !== '' ? $value : null;
    }

    /**
     * The original publish date on one Amasty row, as 'Y-m-d H:i:s'.
     *
     * published_at is Amasty's own field for this and is what its front end
     * shows. created_at is the fallback for a row where published_at was never
     * set: still the truth about when the post appeared, and far closer than
     * the moment our import ran.
     *
     * @param array<string, mixed> $row
     * @return string|null null when the source has no usable date
     */
    private function publishedAtFrom(array $row): ?string
    {
        foreach (['published_at', 'created_at'] as $column) {
            $value = trim((string) ($row[$column] ?? ''));
            if ($value === '' || str_starts_with($value, '0000-00-00')) {
                continue;
            }

            $timestamp = strtotime($value);
            if ($timestamp !== false && $timestamp > 0) {
                return date('Y-m-d H:i:s', $timestamp);
            }
        }

        return null;
    }

    /**
     * Correct created_at on an existing post to the source's publish date.
     *
     * created_at can never be "empty" the way short_description can - the column
     * defaults to CURRENT_TIMESTAMP - so emptiness cannot be the test for
     * whether a value is ours to replace. The test is that ours is LATER than
     * the source: an import stamp always is, because it was written long after
     * the post was published. A date someone moved deliberately to an earlier
     * point is left alone, and once corrected the two match, so a re-run is a
     * no-op rather than a rewrite.
     *
     * A direct UPDATE for the same reason backfillShortDescription() uses one:
     * a full repository save would rewrite every other column and reset the
     * RequestDesk sync fields on a post someone may have edited.
     *
     * @param int $postId
     * @param string $publishedAt 'Y-m-d H:i:s'
     * @param bool $dryRun
     * @return bool true when the date was corrected, or would have been
     */
    private function backfillPublishDate(int $postId, string $publishedAt, bool $dryRun): bool
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('requestdesk_blog_post');

        $current = (string) $connection->fetchOne(
            $connection->select()
                ->from($table, ['created_at'])
                ->where('post_id = ?', $postId)
                ->limit(1)
        );

        $currentTs = $current !== '' ? strtotime($current) : false;
        $sourceTs = strtotime($publishedAt);
        if ($currentTs === false || $sourceTs === false || $currentTs <= $sourceTs) {
            return false;
        }

        if ($dryRun) {
            return true;
        }

        $connection->update($table, ['created_at' => $publishedAt], ['post_id = ?' => $postId]);

        return true;
    }

    /**
     * Move an existing post's image references from amasty/blog/ to blog/.
     *
     * The featured image is only ours to move while it still holds the Amasty
     * value: equal to the source post_thumbnail, or naming the Amasty folder
     * outright. Anything else was chosen by hand after the migration and is left
     * alone. Once moved it no longer matches the source, and the body no longer
     * contains an amasty/blog/ media link, so a re-run is a no-op.
     *
     * A direct UPDATE of the changed columns only, for the reason the other two
     * backfills give: a repository save would rewrite every column and reset the
     * RequestDesk sync fields.
     *
     * @param int $postId
     * @param string|null $sourceThumbnail post_thumbnail on the Amasty row
     * @param bool $dryRun
     * @return bool true when something was moved, or would have been
     */
    private function backfillMediaPaths(int $postId, ?string $sourceThumbnail, bool $dryRun): bool
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('requestdesk_blog_post');

        $row = $connection->fetchRow(
            $connection->select()
                ->from($table, ['featured_image', 'content'])
                ->where('post_id = ?', $postId)
                ->limit(1)
        ) ?: [];
        if ($row === []) {
            return false;
        }

        $changes = [];

        $currentImage = trim((string) ($row['featured_image'] ?? ''));
        if ($currentImage !== ''
            && ($currentImage === trim((string) $sourceThumbnail)
                || str_starts_with($currentImage, AmastyMediaPath::AMASTY_DIR))
        ) {
            $moved = AmastyMediaPath::featuredImage($currentImage);
            if ($moved !== $currentImage) {
                $changes['featured_image'] = $moved;
            }
        }

        $content = (string) ($row['content'] ?? '');
        $rewritten = AmastyMediaPath::rewriteContent($content);
        if ($rewritten !== $content) {
            $changes['content'] = $rewritten;
        }

        if ($changes === []) {
            return false;
        }

        if ($dryRun) {
            return true;
        }

        $connection->update($table, $changes, ['post_id = ?' => $postId]);

        return true;
    }

    /**
     * Fill short_description on an existing post, but only if it is still empty.
     *
     * Written as a direct UPDATE rather than a load-and-save through the
     * repository on purpose: this runs over posts someone may already have
     * edited, and a full save would rewrite every other column and reset the
     * RequestDesk sync fields. Only updated_at moves, which the column's
     * on_update does by itself.
     *
     * @param int $postId
     * @param string $shortDescription
     * @param bool $dryRun
     * @return bool true when a value was written, or would have been
     */
    private function backfillShortDescription(int $postId, string $shortDescription, bool $dryRun): bool
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('requestdesk_blog_post');

        $current = $connection->fetchOne(
            $connection->select()
                ->from($table, ['short_description'])
                ->where('post_id = ?', $postId)
                ->limit(1)
        );

        // Anything already there was either migrated earlier or typed by hand.
        // Neither is ours to overwrite.
        if (trim((string) $current) !== '') {
            return false;
        }

        if ($dryRun) {
            return true;
        }

        $connection->update(
            $table,
            ['short_description' => $shortDescription],
            ['post_id = ?' => $postId]
        );

        return true;
    }

    /*
     * resolveAuthorId() was removed here. It matched the Amasty byline against
     * admin_user and wrote that user_id into requestdesk_blog_post.author_id -
     * but that column is a foreign key onto requestdesk_blog_author.author_id,
     * so the value was wrong in both directions: it either broke the constraint
     * and failed the post save, or it pointed at whichever unrelated author held
     * that id. Either way no author record was ever created, which is why the
     * Author grid came up empty after a migration.
     *
     * AuthorResolver::getOrCreateByName() replaces it, and still links the admin
     * account when the names match - via requestdesk_blog_author.admin_user_id,
     * which is the column that actually means that.
     */
}
