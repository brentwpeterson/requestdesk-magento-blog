<?php
/**
 * RequestDesk Blog - Frontend Author View
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Controller\Author;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Forward;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use RequestDesk\Blog\Model\AuthorResolver;
use RequestDesk\Blog\Model\StorefrontGate;

/**
 * Renders an author page at /blog/author/view/id/{admin_user_id}.
 */
class View implements HttpGetActionInterface
{
    /**
     * @param PageFactory $pageFactory
     * @param ForwardFactory $forwardFactory
     * @param RequestInterface $request
     * @param StorefrontGate $storefrontGate
     * @param AuthorResolver $authorResolver
     */
    public function __construct(
        private readonly PageFactory $pageFactory,
        private readonly ForwardFactory $forwardFactory,
        private readonly RequestInterface $request,
        private readonly StorefrontGate $storefrontGate,
        private readonly AuthorResolver $authorResolver
    ) {
    }

    /**
     * Execute the action
     *
     * @return Page|Forward
     */
    public function execute()
    {
        if (!$this->storefrontGate->allows($this->request)) {
            return $this->forwardFactory->create()->forward('noroute');
        }

        $id = (int) $this->request->getParam('id');
        $author = $id > 0 ? $this->authorResolver->getAuthor($id) : null;
        if ($author === null) {
            return $this->forwardFactory->create()->forward('noroute');
        }

        $page = $this->pageFactory->create();
        $pageConfig = $page->getConfig();
        // "Articles by Name", not the bare name: the title says what the page is,
        // and it is the wording Amasty Blog used, so a migrated store keeps it.
        $pageConfig->getTitle()->set((string) __('Articles by %1', $author['name']));

        // The bio is the best summary the page has. Without it search engines
        // write their own from whatever is first on the page.
        $summary = trim((string) preg_replace('/\s+/', ' ', strip_tags((string) ($author['bio'] ?? ''))));
        if ($summary !== '') {
            $pageConfig->setDescription(mb_substr($summary, 0, 300));
        }

        return $page;
    }
}
