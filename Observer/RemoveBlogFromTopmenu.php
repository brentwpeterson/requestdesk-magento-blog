<?php
/**
 * RequestDesk Blog - Remove the blog's entry from the core top menu
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Observer;

use Magento\Framework\Data\Tree\Node;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use RequestDesk\Blog\Model\MenuGate;

/**
 * Takes the blog's entry out of the menu Magento's own Topmenu block renders,
 * which is what Luma and every theme built on it use.
 *
 * An observer rather than a plugin, deliberately. Magento_Catalog fills the
 * tree from a beforeGetHtml plugin on the same block, so a second before
 * plugin would have to sort after that one to see anything, and sort order
 * between two modules' plugins is a fragile thing to depend on.
 * page_block_html_topmenu_gethtml_before is dispatched from inside the method,
 * once every before plugin has run, so the tree is always populated by the
 * time this sees it.
 *
 * Hyva does not use this block at all - it builds its own tree in
 * Hyva\Theme\Service\Navigation - so the Hyva menu is handled separately in
 * Plugin\HyvaNavigation.
 */
class RemoveBlogFromTopmenu implements ObserverInterface
{
    /**
     * @param MenuGate $menuGate
     */
    public function __construct(
        private readonly MenuGate $menuGate
    ) {
    }

    /**
     * Execute the action
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        if ($this->menuGate->showsBlogEntry()) {
            return;
        }

        $menu = $observer->getData('menu');
        if (!$menu instanceof Node) {
            return;
        }

        $this->prune($menu);
    }

    /**
     * Drop every child pointing into the blog, at any depth.
     *
     * Collected before deleting: the node collection is being iterated, and
     * removing from it mid-loop skips entries.
     *
     * @param Node $node
     * @return void
     */
    private function prune(Node $node): void
    {
        $children = $node->getChildren();
        $doomed = [];

        /** @var Node $child */
        foreach ($children as $child) {
            if ($this->menuGate->isBlogUrl((string) $child->getData('url'))) {
                $doomed[] = $child;
                continue;
            }

            $this->prune($child);
        }

        foreach ($doomed as $child) {
            $children->delete($child);
        }
    }
}
