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

use RequestDesk\Blog\Api\FaqSchemaBuilderInterface;

/**
 * Builds FAQPage schema through RequestDesk_Qa when it is installed, and emits
 * nothing otherwise. See {@see QaBridge}.
 */
class FaqSchemaBuilderAdapter implements FaqSchemaBuilderInterface
{
    private const DELEGATE = \RequestDesk\Qa\Model\FaqSchemaBuilder::class;

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
    public function build(array $pairs, bool $withContext = true): ?array
    {
        $delegate = $this->bridge->get(self::DELEGATE);
        return $delegate === null ? null : $delegate->build($pairs, $withContext);
    }
}
