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
        $rootParentId = $parentOption !== null ? (int) $parentOption : 0;
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
        $tagLinks = 0;
        $categoryLinks = 0;
        /** @var array<int,true> $authorsSeen author_ids touched, for the summary count */
        $authorsSeen = [];

        foreach ($rows as $row) {
            $urlKey = (string) ($row['url_key'] ?? '');
            $title = (string) ($row['title'] ?? '');
            $srcId = (int) ($row['post_id'] ?? 0);

            if ($this->postExistsByUrlKey($urlKey)) {
                $output->writeln("  skip (exists): {$urlKey}");
                $skipped++;
                continue;
            }

            if ($dryRun) {
                $output->writeln("  would migrate: {$title}  [{$urlKey}]");
                $migrated++;
                continue;
            }

            try {
                $post = $this->postFactory->create();
                $post->setTitle($title !== '' ? $title : 'Untitled');
                $post->setContent((string) ($row['full_content'] ?? ''));
                $post->setUrlKey($urlKey);
                $post->setMetaTitle((string) ($row['meta_title'] ?: $title));
                $post->setMetaDescription((string) ($row['meta_description'] ?? ''));
                $post->setFeaturedImage(!empty($row['post_thumbnail']) ? $row['post_thumbnail'] : null);

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

                $saved = $this->postRepository->save($post);
                $savedId = (int) $saved->getPostId();

                $tagIds = [];
                foreach ($this->tagNames($srcId) as $name) {
                    $ourTagId = $this->tagResolver->getOrCreateByName($name);
                    if ($ourTagId) {
                        $tagIds[] = $ourTagId;
                    }
                }
                if ($tagIds) {
                    $this->tagResolver->syncForPost($savedId, $tagIds);
                    $tagLinks += count($tagIds);
                }

                if ($canMapCategories) {
                    $categoryIds = [];
                    foreach ($this->categoryMapper->getSourceCategoryIds($srcId) as $srcCategoryId) {
                        $nativeId = $this->categoryMapper->mapCategory($srcCategoryId, $rootParentId);
                        if ($nativeId) {
                            $categoryIds[] = $nativeId;
                        }
                    }
                    if ($categoryIds) {
                        // syncForPost replaces rather than appends, which is the
                        // agreed behaviour: the Amasty data is the source of truth
                        // for a migrated post, not whatever was assigned before.
                        $this->postCategoryResolver->syncForPost($savedId, $categoryIds);
                        $categoryLinks += count($categoryIds);
                    }
                }

                $output->writeln("  migrated: {$title}  [{$urlKey}]");
                $migrated++;
            } catch (\Exception $e) {
                $output->writeln("  <error>failed: {$urlKey} — {$e->getMessage()}</error>");
                $skipped++;
            }
        }

        $output->writeln('');
        $output->writeln($dryRun ? '<info>DRY RUN — nothing written.</info>' : '<info>Migration complete.</info>');
        $output->writeln("  posts migrated:  {$migrated}");
        $output->writeln("  posts skipped:   {$skipped}");
        $output->writeln("  tag links:       {$tagLinks}");
        $output->writeln('  authors linked:  ' . count($authorsSeen));
        $output->writeln("  category links:  {$categoryLinks}");
        $output->writeln('  categories made: ' . count($this->categoryMapper->getMapping())
            . ($rootParentId > 0 ? " (under category {$rootParentId})" : ''));

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
     * Does a RequestDesk post already exist with this url_key? Keeps re-runs
     * idempotent.
     *
     * @param string $urlKey
     * @return bool
     */
    private function postExistsByUrlKey(string $urlKey): bool
    {
        if ($urlKey === '') {
            return false;
        }
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName('requestdesk_blog_post'), ['post_id'])
            ->where('url_key = ?', $urlKey)
            ->limit(1);
        return (bool) $connection->fetchOne($select);
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
