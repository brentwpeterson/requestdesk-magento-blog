<?php
/**
 * RequestDesk Blog - Copy the Amasty blog image files into the blog's media folder
 *
 * The other half of the media migration. requestdesk:blog:migrate-amasty moves
 * the database side: it rewrites featured_image and the {{media url=...}}
 * references in post bodies from amasty/blog/ to blog/. The files themselves
 * were until now a manual server-side copy, which is easy to get half right:
 * forgotten .renditions copies, files dropped at the wrong level, no record of
 * what was already copied. This command does that half the same way the
 * database half is done.
 *
 * Two folder pairs are copied, mirroring Model\AmastyMediaPath:
 *
 *   pub/media/amasty/blog            ->  pub/media/blog
 *   pub/media/.renditions/amasty/blog -> pub/media/.renditions/blog
 *
 * Existing files are never overwritten, so the command is idempotent and safe
 * to re-run at any time: it only ever adds what is missing and reports the
 * rest as already present.
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength.TooLong

namespace RequestDesk\Blog\Console\Command;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Driver\File;
use RequestDesk\Blog\Model\AmastyMediaPath;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class MigrateMediaCommand extends Command
{
    private const OPT_SOURCE = 'source';
    private const OPT_DRY_RUN = 'dry-run';

    /**
     * Folder names never copied. Amasty keeps its own resized copies under
     * amasty/blog/cache; the blog reads none of them, and copying them would
     * multiply the transfer for nothing.
     */
    private const EXCLUDED_DIRS = ['cache'];

    /**
     * @param Filesystem $filesystem
     * @param File $fileDriver works on absolute paths, which --source needs: it
     *        can point outside this install's pub/media
     */
    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly File $fileDriver
    ) {
        parent::__construct();
    }

    /**
     * @inheritdoc
     */
    protected function configure(): void
    {
        $this->setName('requestdesk:blog:migrate-media');
        $this->setDescription('Copy the Amasty blog image files into the blog\'s own media folder (amasty/blog -> blog, including .renditions).');
        $this->addOption(
            self::OPT_SOURCE,
            's',
            InputOption::VALUE_REQUIRED,
            'Path to the old site\'s media root (the folder containing amasty/blog) or to the amasty/blog folder itself. Default: this install\'s pub/media'
        );
        $this->addOption(self::OPT_DRY_RUN, null, InputOption::VALUE_NONE, 'Report what would be copied without writing anything');
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dryRun = (bool) $input->getOption(self::OPT_DRY_RUN);

        $mediaRoot = rtrim($this->filesystem->getDirectoryWrite(DirectoryList::MEDIA)->getAbsolutePath(), '/');
        $amastyBlog = rtrim(AmastyMediaPath::AMASTY_DIR, '/');
        $targetBlog = rtrim(AmastyMediaPath::TARGET_DIR, '/');

        $sourceRoot = $this->resolveSourceRoot((string) $input->getOption(self::OPT_SOURCE), $mediaRoot, $amastyBlog);
        if ($sourceRoot === null) {
            $output->writeln('<error>No amasty/blog folder found. Pass --source pointing at the old site\'s media root (the folder containing amasty/blog), or place the folder under pub/media first.</error>');
            return Command::FAILURE;
        }

        $copied = 0;
        $present = 0;
        $foundAny = false;

        foreach (['', '.renditions/'] as $variant) {
            $from = $sourceRoot . '/' . $variant . $amastyBlog;
            $to = $mediaRoot . '/' . $variant . $targetBlog;

            if (!$this->fileDriver->isDirectory($from)) {
                continue;
            }
            $foundAny = true;

            $output->writeln(sprintf('%s -> %s', $from, $to));
            $result = $this->copyTree($from, $to, $dryRun);
            $copied += $result['copied'];
            $present += $result['present'];

            $output->writeln(sprintf('  %s: %d file(s), %d already present', $dryRun ? 'would copy' : 'copied', $result['copied'], $result['present']));
        }

        if (!$foundAny) {
            $output->writeln(sprintf(
                '<error>No %s or .renditions/%s folder under %s - nothing to migrate.</error>',
                $amastyBlog,
                $amastyBlog,
                $sourceRoot
            ));
            return Command::FAILURE;
        }

        $output->writeln('');
        $output->writeln($dryRun ? '<info>DRY RUN - nothing written.</info>' : '<info>Media migration complete.</info>');
        $output->writeln(sprintf('  files copied:        %d', $copied));
        $output->writeln(sprintf('  files already here:  %d', $present));
        $output->writeln('');
        $output->writeln('Database references are moved by: bin/magento requestdesk:blog:migrate-amasty');

        return Command::SUCCESS;
    }

    /**
     * Work out which folder the old images are read from.
     *
     * Accepts the media root (contains amasty/blog) or the amasty/blog folder
     * itself, so a backup handed over either whole or extracted still works.
     * Renditions are always resolved relative to the media root, which both
     * accepted shapes identify.
     *
     * @param string $option the --source value, '' when omitted
     * @param string $mediaRoot this install's pub/media
     * @param string $amastyBlog "amasty/blog"
     * @return string|null the media root to read from, null when nothing matches
     */
    private function resolveSourceRoot(string $option, string $mediaRoot, string $amastyBlog): ?string
    {
        if ($option === '') {
            return $this->fileDriver->isDirectory($mediaRoot . '/' . $amastyBlog) ? $mediaRoot : null;
        }

        $source = rtrim($option, '/');

        if ($this->fileDriver->isDirectory($source . '/' . $amastyBlog)) {
            return $source;
        }

        // The amasty/blog folder itself: two levels up is the media root.
        if (str_ends_with($source, '/' . $amastyBlog) && $this->fileDriver->isDirectory($source)) {
            return $this->fileDriver->getParentDirectory($this->fileDriver->getParentDirectory($source));
        }

        return null;
    }

    /**
     * Copy a folder tree, keeping the subfolder structure (Amasty nests under
     * uploads/year/month). Files already at the target are left untouched, so
     * re-running after a partial copy continues where it stopped.
     *
     * @param string $from absolute source folder, no trailing slash
     * @param string $to absolute target folder, no trailing slash
     * @param bool $dryRun count only, write nothing
     * @return array{copied: int, present: int}
     * @throws \Magento\Framework\Exception\FileSystemException when a folder or file cannot be written
     */
    private function copyTree(string $from, string $to, bool $dryRun): array
    {
        $copied = 0;
        $present = 0;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveCallbackFilterIterator(
                new \RecursiveDirectoryIterator($from, \FilesystemIterator::SKIP_DOTS),
                fn (\SplFileInfo $current): bool =>
                    !($current->isDir() && in_array($current->getFilename(), self::EXCLUDED_DIRS, true))
            ),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isLink()) {
                continue;
            }

            $target = $to . substr($item->getPathname(), strlen($from));

            if ($item->isDir()) {
                if (!$dryRun && !$this->fileDriver->isDirectory($target)) {
                    $this->fileDriver->createDirectory($target, 0775);
                }
                continue;
            }

            if ($this->fileDriver->isExists($target)) {
                $present++;
                continue;
            }

            if (!$dryRun) {
                $parent = $this->fileDriver->getParentDirectory($target);
                if (!$this->fileDriver->isDirectory($parent)) {
                    $this->fileDriver->createDirectory($parent, 0775);
                }
                $this->fileDriver->copy($item->getPathname(), $target);
            }
            $copied++;
        }

        return ['copied' => $copied, 'present' => $present];
    }
}
