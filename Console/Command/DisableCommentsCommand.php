<?php
/**
 * RequestDesk Blog - Switch comments off on every post
 *
 * New posts already start with comments off (1.12.0), but changing a column
 * default leaves existing rows alone, so every post migrated before then still
 * shows the comment form. This turns it off on all of them in one go.
 *
 * Only the per-post "Allow Comment" flag changes. Comments already left stay in
 * Content > RequestDesk Blog > Comments, and a post can be switched back on
 * from its edit form.
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Console\Command;

use Magento\Framework\App\ResourceConnection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DisableCommentsCommand extends Command
{
    private const OPT_DRY_RUN = 'dry-run';
    private const POST_TABLE = 'requestdesk_blog_post';

    /**
     * @param ResourceConnection $resource
     */
    public function __construct(
        private readonly ResourceConnection $resource
    ) {
        parent::__construct();
    }

    /**
     * @inheritdoc
     */
    protected function configure(): void
    {
        $this->setName('requestdesk:blog:disable-comments');
        $this->setDescription('Turn "Allow Comment" off on every blog post.');
        $this->addOption(self::OPT_DRY_RUN, null, InputOption::VALUE_NONE, 'Report how many posts would change');
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName(self::POST_TABLE);

        $enabled = (int) $connection->fetchOne(
            $connection->select()
                ->from($table, [new \Zend_Db_Expr('COUNT(*)')])
                ->where('comments_enabled = ?', 1)
        );

        if ($input->getOption(self::OPT_DRY_RUN)) {
            $output->writeln(sprintf('<info>DRY RUN - %d post(s) would have comments turned off.</info>', $enabled));
            return Command::SUCCESS;
        }

        // A direct UPDATE of the one column: a repository save would rewrite
        // every column and reset the RequestDesk sync fields on each post.
        $changed = $enabled > 0
            ? $connection->update($table, ['comments_enabled' => 0], ['comments_enabled = ?' => 1])
            : 0;

        $output->writeln(sprintf('<info>Comments turned off on %d post(s).</info>', $changed));

        return Command::SUCCESS;
    }
}
