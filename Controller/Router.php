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

namespace RequestDesk\Blog\Controller;

use Magento\Framework\App\ActionFactory;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\RouterInterface;

/**
 * Resolves /blog/<url-key> to a post.
 *
 * Posts have always carried a url_key, but nothing routed on it — the only way to
 * reach a post was /blog/post/view/id/235/. That put the primary key in every
 * public URL and threw away the one part of the address a search engine can read.
 *
 * This router runs only after Magento's standard router has failed to match, so it
 * cannot shadow anything: /blog, /blog/post/view/id/N and every other real
 * controller path still resolve exactly as before. It just gives the leftover
 * single-segment case a meaning.
 *
 * The id form keeps working on purpose. Nothing needs rewriting, old links stay
 * good, and there are no redirects to maintain.
 */
class Router implements RouterInterface
{
    /**
     * First path segments that belong to real controllers, so they must never be
     * mistaken for a post url_key.
     */
    private const RESERVED = [
        'author',
        'category',
        'comment',
        'index',
        'post',
        'tag',
    ];

    /**
     * @param ActionFactory $actionFactory
     * @param ResourceConnection $resource
     */
    public function __construct(
        private readonly ActionFactory $actionFactory,
        private readonly ResourceConnection $resource
    ) {
    }

    /**
     * @param RequestInterface $request
     * @return ActionInterface|null
     */
    public function match(RequestInterface $request): ?ActionInterface
    {
        $identifier = trim($request->getPathInfo(), '/');
        $parts = explode('/', $identifier);

        // Only /blog/<something> — one segment past the front name.
        if (count($parts) !== 2 || $parts[0] !== 'blog') {
            return null;
        }

        $urlKey = $parts[1];
        if ($urlKey === '' || in_array($urlKey, self::RESERVED, true)) {
            return null;
        }

        $postId = $this->findPostIdByUrlKey($urlKey);
        if ($postId === 0) {
            return null;
        }

        $request->setModuleName('blog')
            ->setControllerName('post')
            ->setActionName('view')
            ->setParam('id', $postId);

        // Keeps the pretty URL in the address bar instead of bouncing the visitor
        // to the id form.
        $request->setAlias(\Magento\Framework\Url::REWRITE_REQUEST_PATH_ALIAS, $identifier);

        return $this->actionFactory->create(\Magento\Framework\App\Action\Forward::class);
    }

    /**
     * Look up an active post by its url_key.
     *
     * @param string $urlKey
     * @return int 0 when there is no such post
     */
    private function findPostIdByUrlKey(string $urlKey): int
    {
        try {
            $connection = $this->resource->getConnection();
            $table = $this->resource->getTableName('requestdesk_blog_post');

            return (int) $connection->fetchOne(
                $connection->select()
                    ->from($table, ['post_id'])
                    ->where('url_key = ?', $urlKey)
                    ->where('status = ?', 1)
                    ->limit(1)
            );
        } catch (\Throwable $e) {
            // A router must never take the whole front end down; an unresolved
            // match simply falls through to Magento's 404.
            return 0;
        }
    }
}
