<?php
/**
 * RequestDesk Blog - Migrate Amasty Blog categories into blog categories
 *
 * Two passes, both safe to re-run:
 *  1. every Amasty category becomes a requestdesk_blog_category row, tree kept;
 *  2. every Amasty post-to-category link is re-created on the migrated post,
 *     matched by url_key, the same key requestdesk:blog:migrate-amasty uses.
 *
 * Links are added, never removed, so categories assigned by hand since the
 * migration survive a re-run. Posts migrated before 1.13.0 were filed under
 * native catalog categories; this is what files them under blog ones.
 *
 * Reads Amasty's tables directly; Amasty does not need to be installed.
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength.TooLong

namespace RequestDesk\Blog\Console\Command;

use Magento\Framework\App\ResourceConnection;
use RequestDesk\Blog\Model\AmastyCategoryImporter;
use RequestDesk\Blog\Model\PostCategoryResolver;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class MigrateAmastyCategoriesCommand extends Command
{
    private const OPT_DRY_RUN = 'dry-run';
    private const OPT_SKIP_LINKS = 'skip-links';

    /**
     * @param ResourceConnection $resource
     * @param AmastyCategoryImporter $importer
     * @param PostCategoryResolver $postCategoryResolver
     */
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly AmastyCategoryImporter $importer,
        private readonly PostCategoryResolver $postCategoryResolver
    ) {
        parent::__construct();
    }

    /**
     * @inheritdoc
     */
    protected function configure(): void
    {
        $this->setName('requestdesk:blog:migrate-amasty-categories');
        $this->setDescription(
            'Copy Amasty Blog categories into blog categories and re-link migrated posts (reads Amasty DB tables directly).'
        );
        $this->addOption(self::OPT_DRY_RUN, null, InputOption::VALUE_NONE, 'Report what would happen without writing');
        $this->addOption(self::OPT_SKIP_LINKS, null, InputOption::VALUE_NONE, 'Import the categories only; leave post links alone');
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->importer->sourceExists()) {
            $output->writeln('<error>No amasty_blog_categories table in this database — nothing to migrate.</error>');
            return Command::FAILURE;
        }

        $dryRun = (bool) $input->getOption(self::OPT_DRY_RUN);
        $sourceIds = $this->importer->getAllSourceCategoryIds();

        $output->writeln(sprintf('<info>Categories: %d in Amasty.</info>', count($sourceIds)));

        $failed = 0;
        if (!$dryRun) {
            foreach ($sourceIds as $srcId) {
                try {
                    if ($this->importer->mapCategory($srcId) === null) {
                        $output->writeln("  <comment>skip: Amasty category {$srcId} has no row to read</comment>");
                        $failed++;
                    }
                } catch (\Exception $e) {
                    $output->writeln("  <error>failed: Amasty category {$srcId} — {$e->getMessage()}</error>");
                    $failed++;
                }
            }
        }

        $linked = 0;
        $postsMissing = [];
        $categoriesMissing = 0;
        $links = $input->getOption(self::OPT_SKIP_LINKS) ? [] : $this->importer->getSourcePostLinks();
        $mapping = $this->importer->getMapping();

        foreach ($links as $link) {
            $postId = $this->findPostIdByUrlKey($link['url_key']);
            if ($postId === 0) {
                $postsMissing[$link['url_key']] = true;
                continue;
            }
            if ($dryRun) {
                $linked++;
                continue;
            }

            $categoryId = $mapping[$link['category_id']] ?? null;
            if ($categoryId === null) {
                $categoriesMissing++;
                continue;
            }
            $this->postCategoryResolver->attach($postId, $categoryId);
            $linked++;
        }

        $output->writeln('');
        $output->writeln($dryRun ? '<info>DRY RUN — nothing written.</info>' : '<info>Category migration complete.</info>');
        if ($dryRun) {
            $output->writeln('  categories:      ' . count($sourceIds) . '  (would be created or matched)');
            $output->writeln("  post links:      {$linked}  (would be added)");
        } else {
            $output->writeln('  categories made: ' . $this->importer->getCreatedCount());
            $output->writeln('  already present: ' . (count($this->importer->getMapping()) - $this->importer->getCreatedCount()));
            $output->writeln("  failed:          {$failed}");
            $output->writeln("  post links:      {$linked}  (added or already there)");
            if ($categoriesMissing > 0) {
                $output->writeln("  links skipped:   {$categoriesMissing}  (their Amasty category could not be imported)");
            }
        }
        if (!$input->getOption(self::OPT_SKIP_LINKS)) {
            $output->writeln('  posts not found: ' . count($postsMissing)
                . '  (not migrated yet — run requestdesk:blog:migrate-amasty first)');
        }

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * The migrated post holding this url_key, or 0.
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

        return (int) $connection->fetchOne(
            $connection->select()
                ->from($this->resource->getTableName('requestdesk_blog_post'), ['post_id'])
                ->where('url_key = ?', $urlKey)
                ->limit(1)
        );
    }
}
