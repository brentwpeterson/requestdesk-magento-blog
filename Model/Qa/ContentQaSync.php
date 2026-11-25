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

use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;
use RequestDesk\Blog\Api\Data\PostInterface;
use RequestDesk\Blog\Api\QaLinkResolverInterface;

/**
 * Moves a post body's Q&A section into the Q&A library, so the post gets its
 * FAQPage schema and its visible FAQ block from the same linked pairs.
 *
 * Two calls around the post save:
 *
 *   $qa = $sync->prepare($post);       // before save: pairs stored, body stripped
 *   $saved = $repository->save($post);
 *   $sync->finish((int) $saved->getPostId(), $qa);   // after save: pairs linked
 *
 * Pairs are created in prepare(), before the body loses its section, so a
 * failure there stops the save with the body intact. Only the link step needs
 * the post id.
 *
 * Without RequestDesk_Qa there is nowhere to store pairs and no FAQPage
 * builder. The body is then left as it is, so readers still see the Q&A, and
 * the result says 'qa_module_missing' so the caller reports it instead of the
 * post quietly shipping without schema.
 */
class ContentQaSync
{
    public const STATUS_NONE = 'none';
    public const STATUS_EMPTY_SECTION = 'empty_section';
    public const STATUS_QA_MODULE_MISSING = 'qa_module_missing';
    public const STATUS_PENDING_LINK = 'pending_link';
    public const STATUS_LINKED = 'linked';

    private const PAIR_MODEL = \RequestDesk\Qa\Model\QaPair::class;
    private const PAIR_RESOURCE = \RequestDesk\Qa\Model\ResourceModel\QaPair::class;
    private const PAIR_COLLECTION = \RequestDesk\Qa\Model\ResourceModel\QaPair\Collection::class;

    /**
     * requestdesk_qa_pair.question is varchar(500).
     */
    private const QUESTION_MAX = 500;

    /**
     * @param ContentQa $contentQa
     * @param QaBridge $bridge
     * @param QaLinkResolverInterface $qaLinkResolver
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly ContentQa $contentQa,
        private readonly QaBridge $bridge,
        private readonly QaLinkResolverInterface $qaLinkResolver,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Before save: store the body's Q&A pairs in the library and strip the
     * section from the body.
     *
     * @param PostInterface $post
     * @return array{status:string, qa_ids:int[], pairs:int}
     * @throws LocalizedException When a pair cannot be stored; the body is untouched.
     */
    public function prepare(PostInterface $post): array
    {
        $content = (string) $post->getContent();
        if (!$this->contentQa->hasSection($content)) {
            return ['status' => self::STATUS_NONE, 'qa_ids' => [], 'pairs' => 0];
        }

        $pairs = $this->contentQa->extractPairs($content);
        if ($pairs === []) {
            return ['status' => self::STATUS_EMPTY_SECTION, 'qa_ids' => [], 'pairs' => 0];
        }

        if (!$this->bridge->isAvailable()) {
            $this->logger->error(
                'RequestDesk Blog: post has a Q&A section but RequestDesk_Qa is not installed, '
                . 'so it gets no FAQPage schema. Install requestdesk/magento-qa.',
                ['post_id' => $post->getPostId(), 'title' => $post->getTitle(), 'pairs' => count($pairs)]
            );
            return ['status' => self::STATUS_QA_MODULE_MISSING, 'qa_ids' => [], 'pairs' => count($pairs)];
        }

        $qaIds = [];
        foreach ($pairs as $pair) {
            $qaIds[] = $this->findOrCreatePair($pair['question'], $pair['answer']);
        }

        $post->setContent($this->contentQa->stripSection($content));

        return ['status' => self::STATUS_PENDING_LINK, 'qa_ids' => $qaIds, 'pairs' => count($qaIds)];
    }

    /**
     * After save: link the stored pairs to the post, replacing its links.
     *
     * @param int $postId
     * @param array{status:string, qa_ids:int[], pairs:int} $prepared
     * @return array{status:string, qa_ids:int[], pairs:int}
     */
    public function finish(int $postId, array $prepared): array
    {
        if ($prepared['status'] !== self::STATUS_PENDING_LINK) {
            return $prepared;
        }

        $this->qaLinkResolver->syncForEntity(QaLinkResolverInterface::ENTITY_BLOG_POST, $postId, $prepared['qa_ids']);

        return ['status' => self::STATUS_LINKED] + $prepared;
    }

    /**
     * The id of the library pair with exactly this question and answer,
     * created when there is none. A pair is shared across entities, so an
     * existing pair is never edited: a changed answer becomes a new pair.
     *
     * @param string $question
     * @param string $answer
     * @return int
     * @throws LocalizedException
     */
    private function findOrCreatePair(string $question, string $answer): int
    {
        if (mb_strlen($question) > self::QUESTION_MAX) {
            throw new LocalizedException(__(
                'Q&A question is longer than %1 characters and cannot be stored: "%2"',
                self::QUESTION_MAX,
                mb_substr($question, 0, 80)
            ));
        }

        $collection = $this->bridge->create(self::PAIR_COLLECTION);
        $model = $this->bridge->create(self::PAIR_MODEL);
        $resource = $this->bridge->get(self::PAIR_RESOURCE);
        if ($collection === null || $model === null || $resource === null) {
            throw new LocalizedException(__('RequestDesk_Qa is enabled but its Q&A pair classes could not be loaded.'));
        }

        $collection->addFieldToFilter('question', $question)
            ->addFieldToFilter('answer', $answer)
            ->setPageSize(1);
        $existing = $collection->getFirstItem();
        if ($existing && (int) $existing->getData('qa_id') > 0) {
            return (int) $existing->getData('qa_id');
        }

        $model->setData('question', $question);
        $model->setData('answer', $answer);
        $resource->save($model);

        $qaId = (int) $model->getData('qa_id');
        if ($qaId <= 0) {
            throw new LocalizedException(__('Q&A pair was saved but returned no id: "%1"', mb_substr($question, 0, 80)));
        }

        return $qaId;
    }
}
