<?php
/**
 * Copyright © RequestDesk. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace RequestDesk\Blog\Test\Unit\ViewModel;

use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\UrlInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RequestDesk\Blog\Api\Data\PostInterface;
use RequestDesk\Blog\Api\FaqSchemaBuilderInterface;
use RequestDesk\Blog\Api\QaLinkResolverInterface;
use RequestDesk\Blog\Model\AuthorResolver;
use RequestDesk\Blog\Model\CommentManager;
use RequestDesk\Blog\Model\Config;
use RequestDesk\Blog\Model\TagResolver;
use RequestDesk\Blog\ViewModel\BlogSchema;

/**
 * A post's structured data carries a BreadcrumbList next to the BlogPosting.
 *
 * Amasty Blog printed one on every post; the first migration rehearsal on
 * Magento Association's data found it missing after the move.
 */
class BlogSchemaBreadcrumbTest extends TestCase
{
    private function schema(): BlogSchema
    {
        $urlBuilder = $this->createMock(UrlInterface::class);
        $urlBuilder->method('getUrl')->willReturnCallback(
            static fn (string $route, array $params) => 'https://example.test/' . $params['_direct']
        );
        $urlBuilder->method('getBaseUrl')->willReturn('https://example.test/');

        $store = $this->createMock(StoreInterface::class);
        $store->method('getName')->willReturn('Magento Association');
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $qaLinks = $this->createMock(QaLinkResolverInterface::class);
        $qaLinks->method('getPairsFor')->willReturn([]);
        $faq = $this->createMock(FaqSchemaBuilderInterface::class);
        $faq->method('build')->willReturn(null);

        $authors = $this->createMock(AuthorResolver::class);
        $authors->method('getAuthorForPost')->willReturn(null);
        $tags = $this->createMock(TagResolver::class);
        $tags->method('getTagNamesForPost')->willReturn([]);
        $comments = $this->createMock(CommentManager::class);
        $comments->method('getApprovedForPost')->willReturn([]);

        $config = $this->createMock(Config::class);
        $config->method('getUrlPrefix')->willReturn('commerce-co-op');
        $config->method('getUrlSuffix')->willReturn('.html');

        return new BlogSchema(
            $urlBuilder,
            $storeManager,
            new Json(),
            $this->createMock(LoggerInterface::class),
            $qaLinks,
            $faq,
            $authors,
            $tags,
            $comments,
            $config
        );
    }

    private function post(): PostInterface
    {
        $post = $this->createMock(PostInterface::class);
        $post->method('getPostId')->willReturn(218);
        $post->method('getUrlKey')->willReturn('episode-10-arron-moss');
        $post->method('getTitle')->willReturn('Episode 10: Arron Moss');
        $post->method('getMetaDescription')->willReturn('A conversation.');
        $post->method('getCreatedAt')->willReturn('2026-05-04 08:41:00');

        return $post;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function graph(): array
    {
        $decoded = json_decode($this->schema()->getJsonLd($this->post()), true);
        $this->assertSame('https://schema.org', $decoded['@context']);

        return $decoded['@graph'];
    }

    public function testThePostCarriesBothABlogPostingAndABreadcrumbList(): void
    {
        $types = array_column($this->graph(), '@type');

        $this->assertSame(['BlogPosting', 'BreadcrumbList'], $types);
    }

    public function testTheBreadcrumbRunsFromHomeThroughTheBlogToThePost(): void
    {
        $crumbs = $this->graph()[1]['itemListElement'];

        $this->assertSame(
            [
                [1, 'Home', 'https://example.test/'],
                [2, 'Blog', 'https://example.test/commerce-co-op.html'],
                [3, 'Episode 10: Arron Moss', 'https://example.test/commerce-co-op/episode-10-arron-moss.html'],
            ],
            array_map(static fn (array $c): array => [$c['position'], $c['name'], $c['item']], $crumbs)
        );
    }

    public function testThePostingUsesTheSuffixedAddress(): void
    {
        $this->assertSame(
            'https://example.test/commerce-co-op/episode-10-arron-moss.html',
            $this->graph()[0]['url']
        );
    }
}
