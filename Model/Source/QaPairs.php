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
use RequestDesk\Qa\Model\ResourceModel\QaPair\CollectionFactory;

/**
 * Existing shared Q&A pairs, for attaching to a post.
 */
class QaPairs implements OptionSourceInterface
{
    /**
     * @param CollectionFactory $qaPairCollectionFactory
     */
    public function __construct(
        private readonly CollectionFactory $qaPairCollectionFactory
    ) {
    }

    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        $collection = $this->qaPairCollectionFactory->create();
        $collection->setOrder('qa_id', 'ASC');

        $options = [];
        foreach ($collection as $pair) {
            $question = (string) $pair->getData('question');
            $label = mb_strlen($question) > 70 ? mb_substr($question, 0, 70) . '…' : $question;
            $options[] = ['value' => (int) $pair->getId(), 'label' => $label];
        }
        return $options;
    }
}
