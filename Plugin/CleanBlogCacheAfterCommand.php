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

namespace RequestDesk\Blog\Plugin;

use RequestDesk\Blog\Model\BlogCache;
use Symfony\Component\Console\Command\Command;

/**
 * Purges the blog page cache once a data-changing blog CLI command finishes.
 *
 * The migrate and repair commands write straight to the tables, bypassing the
 * models that would otherwise purge. A plugin rather than a constructor
 * argument keeps their constructors - which the unit tests call directly - as
 * they are, and purges once per run instead of once per row. Wired to each
 * command in etc/di.xml.
 */
class CleanBlogCacheAfterCommand
{
    /**
     * @param BlogCache $blogCache
     */
    public function __construct(
        private readonly BlogCache $blogCache
    ) {
    }

    /**
     * Purge after the command has run, whatever its exit code: a failed run may
     * still have written some rows.
     *
     * @param Command $subject
     * @param int $result
     * @return int
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterRun(Command $subject, int $result): int
    {
        $this->blogCache->clean();
        return $result;
    }
}
