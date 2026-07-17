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
 * _posts_category). Categories are counted but deferred (Amasty blog
 * categories vs native-category reuse is a separate decision).
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
     */
    public function __construct(
        private readonly State $appState,
        private readonly ResourceConnection $resource,
        private readonly PostRepositoryInterface $postRepository,
        private readonly PostFactory $postFactory,
        private readonly TagResolver $tagResolver
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
        $categoriesSeen = 0;

        foreach ($rows as $row) {
            $urlKey = (string) ($row['url_key'] ?? '');
            $title = (string) ($row['title'] ?? '');
            $srcId = (int) ($row['post_id'] ?? 0);

            if ($this->postExistsByUrlKey($urlKey)) {
                $output->writeln("  skip (exists): {$urlKey}");
                $skipped++;
                continue;
            }

            $categoriesSeen += $this->countCategories($srcId);

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

                $authorName = $this->authorName((int) ($row['author_id'] ?? 0));
                $post->setAuthor($authorName);
                $post->setAuthorId($this->resolveAuthorId($authorName));

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
        $output->writeln("  categories seen: {$categoriesSeen} (deferred — category mapping is phase 2)");

        return Command::SUCCESS;
    }

    /**
     * Amasty author name for the default store scope.
     *
     * @param int $authorId
     * @return string
     */
    private function authorName(int $authorId): string
    {
        if (!$authorId) {
            return '';
        }
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName('amasty_blog_author_store'), ['name'])
            ->where('author_id = ?', $authorId)
            ->where('store_id = ?', self::DEFAULT_STORE)
            ->limit(1);
        return trim((string) $connection->fetchOne($select));
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
     * How many Amasty categories a post is in (counted, not migrated yet).
     *
     * @param int $srcPostId
     * @return int
     */
    private function countCategories(int $srcPostId): int
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName('amasty_blog_posts_category'), ['COUNT(*)'])
            ->where('post_id = ?', $srcPostId);
        return (int) $connection->fetchOne($select);
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

    /**
     * Match an author name to a native admin_user (full name or username),
     * else null so the free-text byline stands.
     *
     * @param string $name
     * @return int|null
     */
    private function resolveAuthorId(string $name): ?int
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }
        $connection = $this->resource->getConnection();
        $fullName = new \Zend_Db_Expr("TRIM(CONCAT(COALESCE(firstname,''),' ',COALESCE(lastname,'')))");
        $select = $connection->select()
            ->from($this->resource->getTableName('admin_user'), ['user_id'])
            ->where('LOWER(' . $fullName . ') = ?', mb_strtolower($name))
            ->orWhere('LOWER(username) = ?', mb_strtolower($name))
            ->limit(1);
        $userId = (int) $connection->fetchOne($select);
        return $userId ?: null;
    }
}
