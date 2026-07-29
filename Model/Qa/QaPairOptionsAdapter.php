<?php
/**
 * Copyright (c) 2025 Content Basis LLC
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available at https://opensource.org/licenses/OSL-3.0
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 * @author    Content Basis LLC
 * @copyright Copyright (c) 2025 Content Basis LLC
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License 3.0
 */
declare(strict_types=1);

namespace RequestDesk\Blog\Model\Qa;

use RequestDesk\Blog\Api\QaPairOptionsInterface;

/**
 * Lists the shared Q&A library on the post form when RequestDesk_Qa is
 * installed, and an empty list otherwise. See {@see QaBridge}.
 */
class QaPairOptionsAdapter implements QaPairOptionsInterface
{
    private const COLLECTION = \RequestDesk\Qa\Model\ResourceModel\QaPair\Collection::class;

    /**
     * Questions are up to 500 characters; a select is unusable at that width.
     */
    private const LABEL_LENGTH = 90;

    /**
     * @param QaBridge $bridge
     */
    public function __construct(
        private readonly QaBridge $bridge
    ) {
    }

    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        $collection = $this->bridge->create(self::COLLECTION);
        if ($collection === null) {
            return [];
        }

        $collection->setOrder('question', 'ASC');

        $options = [];
        foreach ($collection as $pair) {
            $question = (string) $pair->getData('question');
            $options[] = [
                'value' => (int) $pair->getData('qa_id'),
                'label' => mb_strlen($question) > self::LABEL_LENGTH
                    ? mb_substr($question, 0, self::LABEL_LENGTH) . '…'
                    : $question,
            ];
        }

        return $options;
    }
}
