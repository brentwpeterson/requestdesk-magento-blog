<?php
/**
 * RequestDesk Blog - canonical link tag
 *
 * A blog page can be reached by more than one address: the pretty one, the id
 * form (/blog/post/view/id/12), the extensionless form on a store with a URL
 * suffix, and the index with or without ?p=1. Without a canonical tag search
 * engines have to guess which is the real one. This block names it: the same
 * address the module's own links and the sitemap use.
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Block\Head;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use RequestDesk\Blog\Api\Data\PostInterface;
use RequestDesk\Blog\Api\PostRepositoryInterface;
use RequestDesk\Blog\Block\ArchiveUrl;
use RequestDesk\Blog\Block\BlogUrl;
use RequestDesk\Blog\Block\PostUrl;
use RequestDesk\Blog\Model\Config;

class Canonical extends Template
{
    /** Request parameter the blog index uses for the page number. */
    private const PAGE_PARAM = 'p';

    /**
     * Archive tables by controller name: [table, id column].
     */
    private const ARCHIVE_TABLES = [
        ArchiveUrl::TYPE_CATEGORY => ['requestdesk_blog_category', 'category_id'],
        ArchiveUrl::TYPE_TAG => ['requestdesk_blog_tag', 'tag_id'],
        ArchiveUrl::TYPE_AUTHOR => ['requestdesk_blog_author', 'author_id'],
    ];

    /**
     * @param Context $context
     * @param PostRepositoryInterface $postRepository
     * @param ResourceConnection $resource
     * @param Config $config
     * @param array $data
     */
    public function __construct(
        Context $context,
        private readonly PostRepositoryInterface $postRepository,
        private readonly ResourceConnection $resource,
        private readonly Config $config,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * The address of the page being rendered, or an empty string when it cannot
     * be worked out (the tag is then left out rather than guessed).
     *
     * @return string
     */
    public function getCanonicalUrl(): string
    {
        $prefix = $this->config->getUrlPrefix();
        $suffix = $this->config->getUrlSuffix();
        $controller = (string) $this->getRequest()->getControllerName();

        try {
            if ($controller === 'index') {
                $page = (int) $this->getRequest()->getParam(self::PAGE_PARAM, 1);

                // Page 1 carries no parameter; later pages are their own canonical.
                return BlogUrl::resolve(
                    $prefix,
                    '',
                    [self::PAGE_PARAM => $page > 1 ? $page : null],
                    $this->_urlBuilder,
                    $suffix
                );
            }

            $id = (int) $this->getRequest()->getParam('id');
            if ($id <= 0) {
                return '';
            }

            if ($controller === 'post') {
                $post = $this->postRepository->getById($id);
                if ((int) $post->getStatus() !== PostInterface::STATUS_PUBLISHED) {
                    return '';
                }

                return PostUrl::resolve($post, $this->_urlBuilder, $prefix, $suffix);
            }

            if (isset(self::ARCHIVE_TABLES[$controller])) {
                [$table, $idColumn] = self::ARCHIVE_TABLES[$controller];
                $connection = $this->resource->getConnection();
                $urlKey = $connection->fetchOne(
                    $connection->select()
                        ->from($this->resource->getTableName($table), ['url_key'])
                        ->where($idColumn . ' = ?', $id)
                        ->limit(1)
                );

                return ArchiveUrl::resolve($controller, $id, $urlKey ?: null, $this->_urlBuilder, $prefix, $suffix);
            }
        } catch (\Throwable $e) {
            $this->_logger->warning('RequestDesk Blog: canonical link not built', ['error' => $e->getMessage()]);
        }

        return '';
    }
}
