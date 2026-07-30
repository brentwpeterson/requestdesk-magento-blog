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

use Magento\Framework\App\ResourceConnection;
use RequestDesk\Blog\Model\PostContent;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Rewrites post bodies that are stored HTML-escaped inside a Page Builder shell.
 *
 * Posts imported before the editor field was fixed were written as escaped markup
 * inside a data-content-type="html" wrapper, so `<p>` was stored as `&lt;p&gt;`.
 * Rendering has been repaired on read for a while, which means these posts LOOK
 * fine on the site and in the admin while the stored value is still wrong. Anything
 * that reads the column directly - an export, a feed, a search index, another
 * integration - still gets the mangled text.
 *
 * This repairs the stored value once, using the exact same normalisation the read
 * path uses (PostContent::normalizeForStorage), so the two cannot drift.
 *
 * Dry run by default. Nothing is written without --execute.
 */
class RepairContentCommand extends Command
{
    private const OPT_EXECUTE = 'execute';

    /**
     * @param ResourceConnection $resource
     * @param PostContent $postContent
     * @param string|null $name
     */
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly PostContent $postContent,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    /**
     * @inheritdoc
     */
    protected function configure(): void
    {
        $this->setName('requestdesk:blog:repair-content')
            ->setDescription('Repair blog post bodies stored HTML-escaped inside a Page Builder wrapper')
            ->addOption(
                self::OPT_EXECUTE,
                null,
                InputOption::VALUE_NONE,
                'Write the repaired content. Without this the command only reports what it would change.'
            );

        parent::configure();
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $write = (bool) $input->getOption(self::OPT_EXECUTE);
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('requestdesk_blog_post');

        if (!$connection->isTableExists($table)) {
            $output->writeln('<error>requestdesk_blog_post does not exist.</error>');
            return Command::FAILURE;
        }

        $rows = $connection->fetchPairs(
            $connection->select()->from($table, ['post_id', 'content'])
        );

        $changed = 0;
        $scanned = 0;

        foreach ($rows as $postId => $content) {
            $scanned++;
            $repaired = $this->postContent->normalizeForStorage((string) $content);

            if ($repaired === (string) $content) {
                continue;
            }

            $changed++;

            if ($write) {
                $connection->update($table, ['content' => $repaired], ['post_id = ?' => (int) $postId]);
            }
        }

        $output->writeln(sprintf('Scanned %d post(s).', $scanned));

        if ($changed === 0) {
            $output->writeln('<info>Nothing to repair.</info>');
            return Command::SUCCESS;
        }

        if ($write) {
            $output->writeln(sprintf('<info>Repaired %d post(s).</info>', $changed));
            $output->writeln('Flush the cache so the front end picks the change up.');
            return Command::SUCCESS;
        }

        $output->writeln(sprintf('<comment>%d post(s) would be repaired.</comment>', $changed));
        $output->writeln('Re-run with --execute to write. Take a database backup first.');

        return Command::SUCCESS;
    }
}
