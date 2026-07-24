<?php
/**
 * RequestDesk Blog - No-op FAQ schema builder (free-tier default)
 *
 * Returns null so no FAQPage node is emitted. The paid RequestDesk Q&A
 * integration overrides RequestDesk\Blog\Api\FaqSchemaBuilderInterface.
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Model\Qa;

use RequestDesk\Blog\Api\FaqSchemaBuilderInterface;

class NullFaqSchemaBuilder implements FaqSchemaBuilderInterface
{
    /**
     * @inheritdoc
     */
    public function build(array $pairs, bool $withContext = true): ?array
    {
        return null;
    }
}
