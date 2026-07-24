<?php
/**
 * RequestDesk Blog - Q&A Pairs Source
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;
use RequestDesk\Blog\Api\QaPairOptionsInterface;

/**
 * Options for the "attach existing Q&A pair" field on the post form. Delegates
 * to the optional Q&A integration seam: the free blog returns an empty list, and
 * the paid RequestDesk Q&A bridge supplies the shared Q&A library.
 */
class QaPairs implements OptionSourceInterface
{
    /**
     * @param QaPairOptionsInterface $options
     */
    public function __construct(
        private readonly QaPairOptionsInterface $options
    ) {
    }

    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        return $this->options->toOptionArray();
    }
}
