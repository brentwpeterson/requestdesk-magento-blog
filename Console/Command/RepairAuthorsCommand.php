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

namespace RequestDesk\Blog\Console\Command;

use Magento\Framework\App\Area;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\State;
use RequestDesk\Blog\Model\AuthorResolver;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Repairs post-to-author links left broken by the Amasty migration before 1.6.4.
 *
 * That migration matched the Amasty byline to an admin_user row and wrote the
 * admin user_id into requestdesk_blog_post.author_id, which is a foreign key
 * onto requestdesk_blog_author.author_id. Two states came out of that, and this
 * command repairs both:
 *
 *  - author_id is NULL, because no admin account matched the byline.
 *  - author_id holds an admin_user.user_id that matches no author record. On an
 *    install that predates the foreign key nothing rejected it, so the value
 *    simply dangles.
 *
 * `setup:upgrade` does NOT catch either state. Declarative schema runs its DDL
 * with foreign_key_checks disabled, so it adds the author foreign key over the
 * top of violating rows without complaining - verified on 2.4.7-p3 against 80
 * deliberately broken posts. The constraint then exists while the data under it
 * does not satisfy it, which is the worst of both: the Author grid and author
 * pages resolve nothing for those posts, and the breakage is silent.
 *
 * So run this whenever posts were migrated by a pre-1.6.4 build, upgrade or no
 * upgrade:
 *
 *     bin/magento requestdesk:blog:repair-authors
 *     bin/magento setup:upgrade
 *
 * The one-time BackfillAuthorsFromPosts data patch covers the same ground for
 * posts that existed when it ran, but it is recorded in patch_list and never
 * runs again. Anything migrated afterwards needs this. Safe and idempotent on a
 * healthy install: it reports zero broken links and changes nothing.
 */
class RepairAuthorsCommand extends Command
{
    private const POST_TABLE = 'requestdesk_blog_post';
    private const AUTHOR_TABLE = 'requestdesk_blog_author';
    private const OPTION_DRY_RUN = 'dry-run';

    /**
     * @param State $appState
     * @param ResourceConnection $resource
     * @param AuthorResolver $authorResolver
     * @param string|null $name
     */
    public function __construct(
        private readonly State $appState,
        private readonly ResourceConnection $resource,
        private readonly AuthorResolver $authorResolver,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    /**
     * @inheritdoc
     */
    protected function configure(): void
    {
        $this->setName('requestdesk:blog:repair-authors')
            ->setDescription(
                'Rebuild post-to-author links for posts migrated before 1.6.4 (run before setup:upgrade)'
            )
            ->addOption(
                self::OPTION_DRY_RUN,
                null,
                InputOption::VALUE_NONE,
                'Report what would change without writing anything'
            );

        parent::configure();
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $connection = $this->resource->getConnection();
        $postTable = $this->resource->getTableName(self::POST_TABLE);
        $authorTable = $this->resource->getTableName(self::AUTHOR_TABLE);

        if (!$connection->isTableExists($postTable) || !$connection->isTableExists($authorTable)) {
            $output->writeln('<error>Blog tables are not installed - nothing to repair.</error>');
            return Command::FAILURE;
        }

        try {
            $this->appState->setAreaCode(Area::AREA_ADMINHTML);
        } catch (\Exception $e) {
            // area already set - fine
        }

        $dryRun = (bool) $input->getOption(self::OPTION_DRY_RUN);

        // Posts whose author_id resolves to no author record, minus the ones
        // already in their correct final state.
        //
        // A post with no author_id AND no byline is not broken - there is
        // nothing to rebuild from and nothing to clear - so it is excluded here
        // rather than filtered later. Leaving it in made the command report
        // "Found 1 post with no valid author link" on every run of a healthy
        // install and then change nothing, which reads like a repair that keeps
        // failing.
        $select = $connection->select()
            ->from(['p' => $postTable], ['post_id', 'author', 'author_id'])
            ->joinLeft(
                ['a' => $authorTable],
                'a.author_id = p.author_id',
                []
            )
            ->where('a.author_id IS NULL')
            ->where('p.author_id IS NOT NULL OR TRIM(COALESCE(p.author, ?)) <> ?', '', '')
            ->order('p.post_id ASC');

        $broken = $connection->fetchAll($select);

        if (!$broken) {
            $output->writeln('<info>Every post already points at a valid author. Nothing to repair.</info>');
            return Command::SUCCESS;
        }

        $output->writeln(sprintf('Found %d post(s) with no valid author link.', count($broken)));
        $output->writeln('');

        $repaired = 0;
        $noByline = 0;
        $cleared = 0;
        $authorsTouched = [];

        foreach ($broken as $row) {
            $postId = (int) $row['post_id'];
            $byline = trim((string) ($row['author'] ?? ''));
            $danglingId = $row['author_id'] === null ? 'NULL' : (string) (int) $row['author_id'];

            // No byline means nothing to rebuild from. Null the dangling id so the
            // foreign key can be added; the post keeps rendering with no byline,
            // which is what it already showed.
            if ($byline === '') {
                $noByline++;
                if ($row['author_id'] !== null) {
                    $output->writeln(
                        sprintf('  post %d: author_id=%s dangling, no byline to rebuild from - clearing', $postId, $danglingId)
                    );
                    if (!$dryRun) {
                        $connection->update($postTable, ['author_id' => null], ['post_id = ?' => $postId]);
                    }
                    $cleared++;
                }
                continue;
            }

            if ($dryRun) {
                $output->writeln(sprintf('  post %d: author_id=%s -> would link "%s"', $postId, $danglingId, $byline));
                $repaired++;
                continue;
            }

            $authorId = $this->authorResolver->getOrCreateByName($byline);
            if ($authorId === 0) {
                $noByline++;
                continue;
            }

            $connection->update($postTable, ['author_id' => $authorId], ['post_id = ?' => $postId]);
            $authorsTouched[$authorId] = true;
            $repaired++;

            $output->writeln(sprintf('  post %d: author_id=%s -> %d ("%s")', $postId, $danglingId, $authorId, $byline));
        }

        $output->writeln('');
        $output->writeln($dryRun ? '<info>DRY RUN - nothing written.</info>' : '<info>Repair complete.</info>');
        $output->writeln('  posts relinked:      ' . $repaired);
        $output->writeln('  authors involved:    ' . count($authorsTouched));
        $output->writeln('  posts with no byline: ' . $noByline . ($cleared ? " ({$cleared} dangling id cleared)" : ''));

        if (!$dryRun) {
            $output->writeln('');
            $output->writeln('Now run: bin/magento setup:upgrade');
        }

        return Command::SUCCESS;
    }
}
