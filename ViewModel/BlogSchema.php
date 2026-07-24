<?php
/**
 * RequestDesk Blog - Blog Post Schema ViewModel (theme-agnostic)
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\ViewModel;

use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use RequestDesk\Blog\Api\Data\PostInterface;
use RequestDesk\Blog\Api\FaqSchemaBuilderInterface;
use RequestDesk\Blog\Api\QaLinkResolverInterface;
use RequestDesk\Blog\Block\ImageUrl;
use RequestDesk\Blog\Model\AuthorResolver;
use RequestDesk\Blog\Model\CommentManager;
use RequestDesk\Blog\Model\TagResolver;

/**
 * Builds the answer-engine JSON-LD for a blog post — a BlogPosting node on every
 * post by default, plus a FAQPage node whenever the post has Q&A pairs. Shared
 * by the Luma and Hyva templates so the schema logic lives in one place.
 */
class BlogSchema implements ArgumentInterface
{
    /**
     * @param UrlInterface $urlBuilder
     * @param StoreManagerInterface $storeManager
     * @param Json $json
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly UrlInterface $urlBuilder,
        private readonly StoreManagerInterface $storeManager,
        private readonly Json $json,
        private readonly LoggerInterface $logger,
        private readonly QaLinkResolverInterface $qaLinkResolver,
        private readonly FaqSchemaBuilderInterface $faqSchemaBuilder,
        private readonly AuthorResolver $authorResolver,
        private readonly TagResolver $tagResolver,
        private readonly CommentManager $commentManager
    ) {
    }

    /**
     * JSON-LD for the post, or '' if it can't be built.
     *
     * @param PostInterface $post
     * @return string
     */
    public function getJsonLd(PostInterface $post): string
    {
        try {
            $nodes = [$this->buildBlogPostingNode($post)];

            $faqNode = $this->buildFaqNode($post);
            if ($faqNode !== null) {
                $nodes[] = $faqNode;
            }

            $payload = count($nodes) === 1
                ? $nodes[0]
                : ['@context' => 'https://schema.org', '@graph' => $nodes];

            return $this->json->serialize($payload);
        } catch (\Throwable $e) {
            $this->logger->warning('RequestDesk Blog: schema build failed', [
                'post_id' => $post->getPostId(),
                'error' => $e->getMessage(),
            ]);
            return '';
        }
    }

    /**
     * Decoded FAQ pairs for rendering a visible FAQ section.
     *
     * @param PostInterface $post
     * @return array<int, array{question:string, answer:string}>
     */
    public function getFaqPairs(PostInterface $post): array
    {
        return $this->qaLinkResolver->getPairsFor(
            QaLinkResolverInterface::ENTITY_BLOG_POST,
            (int) $post->getPostId()
        );
    }

    /**
     * @param PostInterface $post
     * @return array<string, mixed>
     */
    private function buildBlogPostingNode(PostInterface $post): array
    {
        $url = $this->urlBuilder->getUrl('blog/post/view', ['id' => $post->getPostId()]);

        $node = [
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            'headline' => (string) $post->getTitle(),
            'url' => $url,
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $url],
        ];

        $description = trim((string) ($post->getMetaDescription()
            ?: preg_replace('/\s+/', ' ', strip_tags((string) $post->getContent()))));
        if ($description !== '') {
            $node['description'] = mb_substr($description, 0, 300);
        }

        $image = ImageUrl::resolve($post->getFeaturedImage(), $this->storeManager);
        if ($image !== '') {
            $node['image'] = $image;
        }

        if ($post->getCreatedAt()) {
            $node['datePublished'] = date('c', (int) strtotime((string) $post->getCreatedAt()));
        }
        if ($post->getUpdatedAt()) {
            $node['dateModified'] = date('c', (int) strtotime((string) $post->getUpdatedAt()));
        }
        $author = $this->authorResolver->getAuthorForPost($post);
        if ($author !== null && $author['name'] !== '') {
            $node['author'] = ['@type' => 'Person', 'name' => $author['name']];
        }

        $tagNames = $this->tagResolver->getTagNamesForPost((int) $post->getPostId());
        if ($tagNames !== []) {
            $node['keywords'] = implode(', ', $tagNames);
        }

        $comments = $this->commentManager->getApprovedForPost((int) $post->getPostId());
        if ($comments !== []) {
            $commentNodes = [];
            foreach ($comments as $comment) {
                $commentNodes[] = [
                    '@type' => 'Comment',
                    'text' => $comment->getContent(),
                    'author' => ['@type' => 'Person', 'name' => $comment->getAuthorName()],
                ];
            }
            $node['commentCount'] = count($commentNodes);
            $node['comment'] = $commentNodes;
        }

        try {
            $node['publisher'] = [
                '@type' => 'Organization',
                'name' => $this->storeManager->getStore()->getName(),
            ];
        } catch (\Throwable $e) {
            // store name unavailable — publisher omitted
        }

        return $node;
    }

    /**
     * @param PostInterface $post
     * @return array<string, mixed>|null
     */
    private function buildFaqNode(PostInterface $post): ?array
    {
        // Nested inside the @graph, so no @context on the FAQPage node.
        return $this->faqSchemaBuilder->build($this->getFaqPairs($post), false);
    }
}
