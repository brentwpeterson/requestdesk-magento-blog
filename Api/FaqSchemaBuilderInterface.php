<?php
/**
 * RequestDesk Blog - FAQ schema builder contract (optional integration seam)
 *
 * The free blog defines this contract and binds a no-op default (returns null,
 * so no FAQPage node is emitted). The paid RequestDesk Q&A integration overrides
 * the binding to build FAQPage JSON-LD from a post's Q&A pairs. Signature mirrors
 * RequestDesk\Qa\Model\FaqSchemaBuilder.
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Api;

interface FaqSchemaBuilderInterface
{
    /**
     * Build a schema.org FAQPage node from Q&A pairs, or null when there are none.
     *
     * @param array<int, array{question:string, answer:string}> $pairs
     * @param bool $withContext Include the @context key (false when nested in a @graph)
     * @return array<string, mixed>|null
     */
    public function build(array $pairs, bool $withContext = true): ?array;
}
