<?php
/**
 * RequestDesk Blog - Remove the blog's entry from the Hyva menu
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Plugin;

use RequestDesk\Blog\Model\MenuGate;

/**
 * Takes the blog's entry out of the menu Hyva renders.
 *
 * Hyva does not use Magento\Theme\Block\Html\Topmenu. Hyva\Theme\Service\
 * Navigation builds the same shape of tree from the category collection
 * itself, and the header templates read it through Hyva\Theme\ViewModel\
 * Navigation::getNavigation(). Nothing in that path dispatches an event, so
 * the core observer never runs and this side has to be a plugin.
 *
 * getNavigation returns a nested array keyed by node id, each entry carrying
 * url, name and childData, so pruning is the same rule applied to arrays
 * rather than to tree nodes.
 *
 * Typed loosely on purpose: naming Hyva\Theme\ViewModel\Navigation in a
 * signature would make this file unloadable on a store without Hyva, and the
 * module supports Luma-only stores.
 */
class HyvaNavigation
{
    /**
     * @param MenuGate $menuGate
     */
    public function __construct(
        private readonly MenuGate $menuGate
    ) {
    }

    /**
     * @param object $subject
     * @param array|false $result
     * @return array|false
     */
    public function afterGetNavigation($subject, $result)
    {
        if (!is_array($result) || $this->menuGate->showsBlogEntry()) {
            return $result;
        }

        return $this->prune($result);
    }

    /**
     * @param array<string, mixed> $items
     * @return array<string, mixed>
     */
    private function prune(array $items): array
    {
        $kept = [];

        foreach ($items as $id => $item) {
            if (is_array($item) && $this->menuGate->isBlogUrl((string) ($item['url'] ?? ''))) {
                continue;
            }

            if (is_array($item) && !empty($item['childData']) && is_array($item['childData'])) {
                $item['childData'] = $this->prune($item['childData']);
            }

            $kept[$id] = $item;
        }

        return $kept;
    }
}
