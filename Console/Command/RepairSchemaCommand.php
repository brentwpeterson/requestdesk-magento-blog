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
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Repairs blog tables left inconsistent by the move off the module's own
 * category taxonomy onto native Magento categories.
 *
 * When requestdesk_blog_category was dropped, the foreign key on
 * requestdesk_blog_post_category that referenced it survived. Two things break
 * while it is present:
 *
 *  - Assigning any category to a post fails with a 1452 integrity-constraint
 *    error, because the referenced table no longer exists.
 *  - `setup:upgrade` aborts in SchemaBuilder, which cannot resolve the missing
 *    table. That is why this is a console command and not a schema patch: on an
 *    affected install, declarative schema never gets far enough to run patches.
 *
 * Run this once, before `setup:upgrade`:
 *
 *     bin/magento requestdesk:blog:repair-schema
 */
class RepairSchemaCommand extends Command
{
    private const LINK_TABLE = 'requestdesk_blog_post_category';
    private const OPTION_DRY_RUN = 'dry-run';

    /**
     * @param ResourceConnection $resource
     * @param string|null $name
     */
    public function __construct(
        private readonly ResourceConnection $resource,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    /**
     * @inheritdoc
     */
    protected function configure(): void
    {
        $this->setName('requestdesk:blog:repair-schema')
            ->setDescription('Drop RequestDesk Blog foreign keys that reference tables which no longer exist')
            ->addOption(
                self::OPTION_DRY_RUN,
                null,
                InputOption::VALUE_NONE,
                'Report what would be dropped without changing anything'
            );

        parent::configure();
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dryRun = (bool) $input->getOption(self::OPTION_DRY_RUN);
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName(self::LINK_TABLE);

        if (!$connection->isTableExists($table)) {
            $output->writeln('<info>Nothing to repair: ' . $table . ' does not exist.</info>');
            return Command::SUCCESS;
        }

        $dropped = 0;
        foreach ($connection->getForeignKeys($table) as $foreignKey) {
            $referencedTable = (string) ($foreignKey['REF_TABLE_NAME'] ?? '');
            if ($referencedTable === '' || $connection->isTableExists($referencedTable)) {
                continue;
            }

            $constraintName = (string) $foreignKey['FK_NAME'];
            $output->writeln(sprintf(
                '%s %s (references missing table %s)',
                $dryRun ? 'Would drop' : 'Dropping',
                $constraintName,
                $referencedTable
            ));

            if (!$dryRun) {
                $connection->dropForeignKey($table, $constraintName);
            }
            $dropped++;
        }

        if ($dropped === 0) {
            $output->writeln('<info>No orphaned foreign keys found.</info>');
            return Command::SUCCESS;
        }

        $output->writeln($dryRun
            ? '<comment>Dry run: re-run without --dry-run to apply.</comment>'
            : '<info>Repaired ' . $dropped . ' foreign key(s). Run bin/magento setup:upgrade next.</info>');

        return Command::SUCCESS;
    }
}
