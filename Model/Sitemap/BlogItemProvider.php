<?php
/**
 * RequestDesk Blog - XML sitemap item provider
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength.TooLong

namespace RequestDesk\Blog\Model\Sitemap;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Sitemap\Model\ItemProvider\ItemProviderInterface;
use Magento\Sitemap\Model\SitemapItemInterface;
use Magento\Sitemap\Model\SitemapItemInterfaceFactory;
use Magento\Store\Model\ScopeInterface;
use RequestDesk\Blog\Model\Config;

/**
 * Puts the blog into Magento's XML sitemap.
 *
 * On a store moving off Amasty Blog, the blog's sitemap entries came from the
 * amasty/blog-sitemap add-on, so turning Amasty off would drop every post out of
 * the sitemap. This registers with Magento\Sitemap\Model\ItemProvider\Composite
 * (etc/di.xml), so the sitemap the store already generates - from the admin or
 * the sitemap cron - picks the blog up with nothing new to schedule.
 *
 * Emits /blog, each published post, and the category, tag and author archives
 * that have at least one published post. An archive with nothing in it is left
 * out, since it would only put an empty page into the index. URLs follow the
 * same rules as Block\PostUrl and Block\ArchiveUrl, including the id-form
 * fallback for a record with no url_key.
 *
 * Deliberately no try/catch: a failing query has to fail the sitemap run where
 * someone sees it, not produce a sitemap that is quietly missing the blog.
 */
class BlogItemProvider implements ItemProviderInterface
{
    private const XML_PATH_BLOG_ENABLED = 'requestdesk_blog/general/enabled';

    /** requestdesk_blog_post.status for a published post. */
    private const STATUS_PUBLISHED = 1;

    /** Default store scope; a post saved there shows on every store. */
    private const ALL_STORES = 0;

    /**
     * @param ResourceConnection $resource
     * @param SitemapItemInterfaceFactory $itemFactory
     * @param BlogConfigReader $configReader
     * @param ScopeConfigInterface $scopeConfig
     * @param Config $config
     */
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly SitemapItemInterfaceFactory $itemFactory,
        private readonly BlogConfigReader $configReader,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly Config $config
    ) {
    }

    /**
     * @inheritdoc
     *
     * @return SitemapItemInterface[]
     */
    public function getItems($storeId)
    {
        $storeId = (int) $storeId;

        if (!$this->configReader->isEnabled($storeId)
            || !$this->scopeConfig->isSetFlag(self::XML_PATH_BLOG_ENABLED, ScopeInterface::SCOPE_STORE, $storeId)
        ) {
            return [];
        }

        $posts = $this->fetchPosts($storeId);
        if ($posts === []) {
            return [];
        }

        // The store's own prefix: the sitemap lists the addresses the storefront
        // serves, and on a store that moved the blog to /news, /blog 404s.
        $prefix = $this->config->getUrlPrefix($storeId);

        $items = [$this->item($prefix, max(array_column($posts, 'updated_at')), $storeId)];

        foreach ($posts as $post) {
            $urlKey = trim((string) $post['url_key']);
            $url = $urlKey !== '' ? $prefix . '/' . $urlKey : $prefix . '/post/view/id/' . (int) $post['post_id'];
            $items[] = $this->item($url, $post['updated_at'], $storeId);
        }

        foreach ([
            'category' => $this->fetchCategoryArchives($storeId),
            'tag' => $this->fetchTagArchives($storeId),
            'author' => $this->fetchAuthorArchives($storeId),
        ] as $type => $archives) {
            foreach ($this->archiveUrls($prefix, $type, $archives) as $url => $updatedAt) {
                $items[] = $this->item($url, $updatedAt, $storeId);
            }
        }

        return $items;
    }

    /**
     * Published posts visible on this store.
     *
     * @param int $storeId
     * @return array<int, array{post_id:string, url_key:?string, updated_at:string}>
     */
    private function fetchPosts(int $storeId): array
    {
        $connection = $this->resource->getConnection();

        return $connection->fetchAll(
            $connection->select()
                ->from($this->resource->getTableName('requestdesk_blog_post'), ['post_id', 'url_key', 'updated_at'])
                ->where('status = ?', self::STATUS_PUBLISHED)
                ->where('store_id IN (?)', [self::ALL_STORES, $storeId])
                ->order('post_id ASC')
        );
    }

    /**
     * Fetch tag archives
     *
     * @param int $storeId
     * @return array<int, array{id:string, url_key:?string, updated_at:string}>
     */
    private function fetchTagArchives(int $storeId): array
    {
        $connection = $this->resource->getConnection();

        return $connection->fetchAll(
            $this->publishedPostSelect($storeId)
                ->join(['l' => $this->resource->getTableName('requestdesk_blog_post_tag')], 'l.post_id = p.post_id', [])
                ->join(
                    ['t' => $this->resource->getTableName('requestdesk_blog_tag')],
                    't.tag_id = l.tag_id',
                    ['id' => 't.tag_id', 'url_key' => 't.url_key', 'updated_at' => new \Zend_Db_Expr('MAX(p.updated_at)')]
                )
                ->group('t.tag_id')
                ->order('t.tag_id ASC')
        );
    }

    /**
     * Fetch author archives
     *
     * @param int $storeId
     * @return array<int, array{id:string, url_key:?string, updated_at:string}>
     */
    private function fetchAuthorArchives(int $storeId): array
    {
        $connection = $this->resource->getConnection();

        return $connection->fetchAll(
            $this->publishedPostSelect($storeId)
                ->join(
                    ['a' => $this->resource->getTableName('requestdesk_blog_author')],
                    'a.author_id = p.author_id',
                    ['id' => 'a.author_id', 'url_key' => 'a.url_key', 'updated_at' => new \Zend_Db_Expr('MAX(p.updated_at)')]
                )
                ->group('a.author_id')
                ->order('a.author_id ASC')
        );
    }

    /**
     * Enabled blog categories with a published post.
     *
     * @param int $storeId
     * @return array<int, array{id:string, url_key:?string, updated_at:string}>
     */
    private function fetchCategoryArchives(int $storeId): array
    {
        $connection = $this->resource->getConnection();

        return $connection->fetchAll(
            $this->publishedPostSelect($storeId)
                ->join(
                    ['l' => $this->resource->getTableName('requestdesk_blog_category_post')],
                    'l.post_id = p.post_id',
                    []
                )
                ->join(
                    ['c' => $this->resource->getTableName('requestdesk_blog_category')],
                    'c.category_id = l.category_id',
                    ['id' => 'c.category_id', 'url_key' => 'c.url_key', 'updated_at' => new \Zend_Db_Expr('MAX(p.updated_at)')]
                )
                ->where('c.status = ?', 1)
                ->group('c.category_id')
                ->order('c.category_id ASC')
        );
    }

    /**
     * The published posts on this store, as the root of an archive query.
     *
     * @param int $storeId
     * @return \Magento\Framework\DB\Select
     */
    private function publishedPostSelect(int $storeId)
    {
        return $this->resource->getConnection()->select()
            ->from(['p' => $this->resource->getTableName('requestdesk_blog_post')], [])
            ->where('p.status = ?', self::STATUS_PUBLISHED)
            ->where('p.store_id IN (?)', [self::ALL_STORES, $storeId]);
    }

    /**
     * Archive rows to sitemap URLs.
     *
     * Rows arrive ordered by id. Controller\Router resolves a url_key shared by
     * two archives to the lowest id, so only that one can use the pretty URL; a
     * later record with the same key would point search engines at the wrong
     * archive and gets its id form instead, which the router serves exactly.
     *
     * @param string $prefix
     * @param string $type
     * @param array $rows array<int, array{id:string, url_key:?string, updated_at:string}>
     * @return array<string, string> url => updated_at
     */
    private function archiveUrls(string $prefix, string $type, array $rows): array
    {
        $urls = [];
        $claimedKeys = [];

        foreach ($rows as $row) {
            $urlKey = trim((string) ($row['url_key'] ?? ''));

            if ($urlKey !== '' && !isset($claimedKeys[$urlKey])) {
                $claimedKeys[$urlKey] = true;
                $url = $prefix . '/' . $type . '/' . $urlKey;
            } else {
                $url = $prefix . '/' . $type . '/view/id/' . (int) $row['id'];
            }

            $urls[$url] = (string) $row['updated_at'];
        }

        return $urls;
    }

    /**
     * Item
     *
     * @param string $url store-relative
     * @param string|null $updatedAt
     * @param int $storeId
     * @return SitemapItemInterface
     */
    private function item(string $url, ?string $updatedAt, int $storeId): SitemapItemInterface
    {
        return $this->itemFactory->create([
            'url' => $url,
            'updatedAt' => $updatedAt,
            'priority' => $this->configReader->getPriority($storeId),
            'changeFrequency' => $this->configReader->getChangeFrequency($storeId),
        ]);
    }
}
