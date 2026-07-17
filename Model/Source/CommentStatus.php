<?php
/**
 * RequestDesk Blog - Comment Status Source
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;
use RequestDesk\Blog\Model\Comment;

/**
 * Options for the comment status column/filter.
 */
class CommentStatus implements OptionSourceInterface
{
    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => Comment::STATUS_PENDING, 'label' => __('Pending')],
            ['value' => Comment::STATUS_APPROVED, 'label' => __('Approved')],
            ['value' => Comment::STATUS_SPAM, 'label' => __('Spam')],
        ];
    }
}
