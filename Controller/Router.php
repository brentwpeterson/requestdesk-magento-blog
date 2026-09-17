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
use RequestDesk\Blog\Model\Config;

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
 * The same treatment now covers the three archives - /blog/category/<url-key>,
 * /blog/tag/<url-key> and /blog/author/<url-key> - which had been left on the id
 * form when posts moved off it.
 *
 * The id form keeps working on purpose. Nothing needs rewriting, old links stay
 * good, and there are no redirects to maintain.
 *
 * Blog URL Prefix. Everything above answers on the store's configured prefix,
 * /blog unless it is set to something else. On a custom prefix such as /news
 * the standard router cannot reach the controllers, because it only knows the
 * front name "blog", so this router also maps /news, /news/post/view/id/N,
 * /news/comment/save and the other controller paths onto them. Only the
 * controller and action pairs the module has are mapped. A forward to an
 * action that does not exist would come straight back here, and Magento stops
 * that loop after 100 passes with an exception rather than a 404. The old /blog
 * addresses on such a store are closed by Model\StorefrontGate.
 *
 * Enable Blog set to No makes this router match nothing.
 */
class Router implements RouterInterface
{
    /**
     * Archive front names this router resolves by url_key. Anything else in the
     * second segment is left to the standard router.
     */
    private const TYPE_CATEGORY = 'category';

    private const ARCHIVE_TYPES = [self::TYPE_CATEGORY, 'tag', 'author'];

    /** Own-table archives, keyed by type. Categories are native, so not here. */
    private const ARCHIVE_TABLES = [
        'tag' => ['requestdesk_blog_tag', 'tag_id'],
        'author' => ['requestdesk_blog_author', 'author_id'],
    ];

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
     * The module's storefront controllers and their actions, which is every
     * pair a custom prefix is allowed to reach. Keys match RESERVED.
     */
    private const CONTROLLER_ACTIONS = [
        'author' => ['view'],
        'category' => ['view'],
        'comment' => ['save'],
        'index' => ['index'],
        'post' => ['view'],
        'tag' => ['view'],
    ];

    /**
     * @param ActionFactory $actionFactory
     * @param ResourceConnection $resource
     * @param Config $config
     */
    public function __construct(
        private readonly ActionFactory $actionFactory,
        private readonly ResourceConnection $resource,
        private readonly Config $config
    ) {
    }

    /**
     * Resolve a storefront path under the blog's prefix.
     *
     * @param RequestInterface $request
     * @return ActionInterface|null
     */
    public function match(RequestInterface $request): ?ActionInterface
    {
        // Already forwarded once and the standard router still found nothing to
        // run. Matching again would forward again, so let the 404 happen.
        if ($request->getModuleName() === Config::ROUTE_FRONT_NAME) {
            return null;
        }

        if (!$this->config->isBlogEnabled()) {
            return null;
        }

        $prefix = $this->config->getUrlPrefix();
        $identifier = trim($request->getPathInfo(), '/');
        $parts = explode('/', $identifier);

        if (($parts[0] ?? '') !== $prefix) {
            return null;
        }

        // /<prefix>/<type>/<url-key> — a category, tag or author archive.
        if (count($parts) === 3 && in_array($parts[1], self::ARCHIVE_TYPES, true)) {
            $archive = $this->matchArchive($request, $identifier, $parts[1], $parts[2]);
            if ($archive !== null || $prefix === Config::ROUTE_FRONT_NAME) {
                return $archive;
            }
        }

        // On a custom prefix, the controller paths the standard router would
        // have matched under /blog.
        if ($prefix !== Config::ROUTE_FRONT_NAME
            && (count($parts) === 1 || in_array($parts[1], self::RESERVED, true))
        ) {
            return $this->matchControllerPath($request, $identifier, $parts);
        }

        // Only /blog/<something> — one segment past the front name.
        if (count($parts) !== 2) {
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

        $request->setModuleName(Config::ROUTE_FRONT_NAME)
            ->setControllerName('post')
            ->setActionName('view')
            ->setParam('id', $postId);

        // Keeps the pretty URL in the address bar instead of bouncing the visitor
        // to the id form.
        $request->setAlias(\Magento\Framework\Url::REWRITE_REQUEST_PATH_ALIAS, $identifier);

        return $this->actionFactory->create(\Magento\Framework\App\Action\Forward::class);
    }

    /**
     * Forward a controller path under a custom prefix onto the blog's controllers.
     *
     * A store whose prefix is not the route's front name cannot reach them
     * through the standard router.
     *
     * The parameters after the action (id/77) are read from the path by the
     * standard router when it picks up the forward, the same way it reads them
     * for /blog/category/view/id/77.
     *
     * @param RequestInterface $request
     * @param string $identifier the full path, for the address-bar alias
     * @param string[] $parts
     * @return ActionInterface|null null for a pair the module does not have
     */
    private function matchControllerPath(
        RequestInterface $request,
        string $identifier,
        array $parts
    ): ?ActionInterface {
        $controller = $parts[1] ?? 'index';
        $controller = $controller !== '' ? $controller : 'index';
        $action = $parts[2] ?? 'index';

        if (!in_array($action, self::CONTROLLER_ACTIONS[$controller] ?? [], true)) {
            return null;
        }

        $request->setModuleName(Config::ROUTE_FRONT_NAME)
            ->setControllerName($controller)
            ->setActionName($action);

        for ($i = 3, $count = count($parts); $i < $count; $i += 2) {
            $request->setParam($parts[$i], isset($parts[$i + 1]) ? urldecode($parts[$i + 1]) : '');
        }

        $request->setAlias(\Magento\Framework\Url::REWRITE_REQUEST_PATH_ALIAS, $identifier);

        return $this->actionFactory->create(\Magento\Framework\App\Action\Forward::class);
    }

    /**
     * Forward /<prefix>/<type>/<url-key> to the archive controller for that record.
     *
     * @param RequestInterface $request
     * @param string $identifier the full path, for the address-bar alias
     * @param string $type one of self::ARCHIVE_TYPES
     * @param string $urlKey
     * @return ActionInterface|null null when nothing matches, so the standard 404 runs
     */
    private function matchArchive(
        RequestInterface $request,
        string $identifier,
        string $type,
        string $urlKey
    ): ?ActionInterface {
        // /blog/category/view and friends belong to the real controllers. They
        // are matched by the standard router long before this one runs, but a
        // guard here keeps that true if a route is ever renamed.
        if ($urlKey === '' || in_array($urlKey, self::RESERVED, true) || $urlKey === 'view') {
            return null;
        }

        $id = $type === self::TYPE_CATEGORY
            ? $this->findBlogCategoryIdByUrlKey($urlKey)
            : $this->findArchiveIdByUrlKey($type, $urlKey);

        if ($id === 0) {
            return null;
        }

        $request->setModuleName(Config::ROUTE_FRONT_NAME)
            ->setControllerName($type)
            ->setActionName('view')
            ->setParam('id', $id);

        $request->setAlias(\Magento\Framework\Url::REWRITE_REQUEST_PATH_ALIAS, $identifier);

        return $this->actionFactory->create(\Magento\Framework\App\Action\Forward::class);
    }

    /**
     * Look up a tag or author by its url_key.
     *
     * Both tables carry a unique index on url_key, so at most one row matches.
     *
     * @param string $type
     * @param string $urlKey
     * @return int 0 when there is no such record
     */
    private function findArchiveIdByUrlKey(string $type, string $urlKey): int
    {
        [$table, $idColumn] = self::ARCHIVE_TABLES[$type];

        try {
            $connection = $this->resource->getConnection();

            return (int) $connection->fetchOne(
                $connection->select()
                    ->from($this->resource->getTableName($table), [$idColumn])
                    ->where('url_key = ?', $urlKey)
                    ->limit(1)
            );
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Look up a native Magento category by url_key, among those the blog uses.
     *
     * Blog categories are native catalog categories, and Magento enforces
     * url_key uniqueness only among siblings - this store has two catalog
     * categories called "ecommerce" and two called "hyva". A plain url_key
     * lookup across the whole tree would be a coin flip.
     *
     * Restricting the search to categories with at least one post attached
     * settles it: that is the only set whose archive has anything to show, and
     * within it the keys are distinct. A genuine tie is resolved by lowest id
     * rather than left to row order, so the same URL always opens the same
     * archive, and /blog/category/view/id/N stays available to address the other
     * one exactly.
     *
     * @param string $urlKey
     * @return int 0 when no blog category has that key
     */
    private function findBlogCategoryIdByUrlKey(string $urlKey): int
    {
        try {
            $connection = $this->resource->getConnection();

            $select = $connection->select()
                ->from(['e' => $this->resource->getTableName('catalog_category_entity')], ['entity_id'])
                ->join(
                    ['v' => $this->resource->getTableName('catalog_category_entity_varchar')],
                    'v.' . $this->categoryLinkField() . ' = e.' . $this->categoryLinkField()
                    . ' AND v.store_id = 0',
                    []
                )
                ->join(
                    ['a' => $this->resource->getTableName('eav_attribute')],
                    'a.attribute_id = v.attribute_id',
                    []
                )
                ->join(
                    ['t' => $this->resource->getTableName('eav_entity_type')],
                    't.entity_type_id = a.entity_type_id',
                    []
                )
                ->where('a.attribute_code = ?', 'url_key')
                ->where('t.entity_type_code = ?', 'catalog_category')
                ->where('v.value = ?', $urlKey)
                ->where(
                    'e.entity_id IN (?)',
                    new \Zend_Db_Expr(
                        (string) $connection->select()->from(
                            $this->resource->getTableName('requestdesk_blog_post_category'),
                            ['category_id']
                        )
                    )
                )
                ->order('e.entity_id ASC')
                ->limit(1);

            return (int) $connection->fetchOne($select);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * The column catalog_category_entity_varchar joins on.
     *
     * Open Source keys EAV value rows on entity_id; Commerce keys them on
     * row_id for staging. Reading it from the table rather than hard-coding
     * entity_id keeps this working on both.
     *
     * @return string
     */
    private function categoryLinkField(): string
    {
        $connection = $this->resource->getConnection();
        $columns = $connection->describeTable(
            $this->resource->getTableName('catalog_category_entity_varchar')
        );

        return isset($columns['row_id']) ? 'row_id' : 'entity_id';
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
