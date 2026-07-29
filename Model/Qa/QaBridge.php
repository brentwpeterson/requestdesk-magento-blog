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

use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Framework\ObjectManagerInterface;

/**
 * Resolves services from the optional RequestDesk_Qa module.
 *
 * RequestDesk_Blog is sequenced *after* RequestDesk_Qa, so the blog's own di.xml
 * preferences always load last and win. That makes the usual approach — letting
 * the paid module override a preference — impossible: Qa cannot sequence Blog
 * back without creating a circular dependency.
 *
 * So the blog binds its Q&A seam to adapters, and those adapters ask this bridge
 * for the real implementation at runtime. When Q&A is absent or disabled they
 * fall back to the no-op behaviour of the free tier.
 *
 * Resolving through the object manager is deliberate: the target classes do not
 * exist when the Q&A module is not installed, so they cannot be constructor
 * dependencies.
 */
class QaBridge
{
    private const QA_MODULE = 'RequestDesk_Qa';

    /**
     * @var array<string, object|null>
     */
    private array $resolved = [];

    /**
     * @param ModuleManager $moduleManager
     * @param ObjectManagerInterface $objectManager
     */
    public function __construct(
        private readonly ModuleManager $moduleManager,
        private readonly ObjectManagerInterface $objectManager
    ) {
    }

    /**
     * Whether the Q&A module is installed and enabled.
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        return $this->moduleManager->isEnabled(self::QA_MODULE);
    }

    /**
     * A shared instance of a Q&A class, or null when Q&A is unavailable.
     *
     * @param string $className
     * @return object|null
     */
    public function get(string $className): ?object
    {
        if (array_key_exists($className, $this->resolved)) {
            return $this->resolved[$className];
        }

        $instance = null;
        if ($this->isAvailable() && class_exists($className)) {
            try {
                $instance = $this->objectManager->get($className);
            } catch (\Throwable $e) {
                // A broken optional dependency must not take the blog down.
                $instance = null;
            }
        }

        return $this->resolved[$className] = $instance;
    }

    /**
     * A fresh instance of a Q&A class, or null when Q&A is unavailable.
     *
     * Use this for stateful objects such as collections, which must not be
     * shared between callers.
     *
     * @param string $className
     * @return object|null
     */
    public function create(string $className): ?object
    {
        if (!$this->isAvailable() || !class_exists($className)) {
            return null;
        }

        try {
            return $this->objectManager->create($className);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
