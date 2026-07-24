<?php
/**
 * RequestDesk Blog - No-op Q&A pair options (free-tier default)
 *
 * Returns an empty option list. The paid RequestDesk Q&A integration overrides
 * RequestDesk\Blog\Api\QaPairOptionsInterface to list the shared Q&A library.
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Model\Qa;

use RequestDesk\Blog\Api\QaPairOptionsInterface;

class NullQaPairOptions implements QaPairOptionsInterface
{
    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        return [];
    }
}
