<?php
/**
 * RequestDesk Blog - Q&A pair option source contract (optional integration seam)
 *
 * Supplies the "attach existing Q&A pair" options on the post form. The free
 * blog binds a no-op default (empty list, so the field is present but offers
 * nothing). The paid RequestDesk Q&A integration overrides the binding to list
 * the shared Q&A library.
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Api;

use Magento\Framework\Data\OptionSourceInterface;

interface QaPairOptionsInterface extends OptionSourceInterface
{
}
