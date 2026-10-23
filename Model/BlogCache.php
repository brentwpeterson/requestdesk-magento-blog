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

namespace RequestDesk\Blog\Model;

use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Indexer\CacheContextFactory;
use Psr\Log\LoggerInterface;

/**
 * Full page cache invalidation for the blog.
 *
 * Every storefront blog block (listings, post page, sidebar, posts widget)
 * reports TAG as its identity, so every page showing blog content carries it,
 * and every blog model reports it too, so Magento purges those pages on any
 * model save or delete. One tag for the whole blog on purpose: a post shows up
 * in listings, sidebars, related posts and widgets, and blog edits are rare, so
 * purging all of it is cheaper than getting a finer scheme wrong.
 *
 * Writes that bypass the models (direct SQL in the resolvers, mass actions, CLI
 * repairs) call clean() themselves. The event reaches both the built-in cache
 * and Varnish.
 */
class BlogCache
{
    public const TAG = 'rd_blog';

    /**
     * @param ManagerInterface $eventManager
     * @param CacheContextFactory $cacheContextFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly ManagerInterface $eventManager,
        private readonly CacheContextFactory $cacheContextFactory,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Purge every cached page that shows blog content.
     *
     * Never throws: the write it follows has already happened, and a cache
     * backend hiccup must not turn a saved post into an error.
     *
     * @return void
     */
    public function clean(): void
    {
        try {
            $context = $this->cacheContextFactory->create();
            $context->registerTags([self::TAG]);
            $this->eventManager->dispatch('clean_cache_by_tags', ['object' => $context]);
        } catch (\Throwable $e) {
            $this->logger->error('RequestDesk Blog: could not purge the blog page cache', ['exception' => $e]);
        }
    }
}
