<?php
/**
 * RequestDesk Blog - Live search suggestions
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength.TooLong

namespace RequestDesk\Blog\Controller\Search;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Forward;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Psr\Log\LoggerInterface;
use RequestDesk\Blog\Block\SearchView;
use RequestDesk\Blog\Model\BlogSearch;
use RequestDesk\Blog\Model\Config;
use RequestDesk\Blog\Model\PostSearch;
use RequestDesk\Blog\Model\StorefrontGate;

/**
 * JSON for the search box's dropdown: /<prefix>/search/suggest?query=...
 *
 * Amasty posts to its live search; this is a GET, so it needs no form key and
 * a repeated query can be cached by the browser for the session. Below the
 * configured minimum length it answers with no groups rather than an error,
 * so a script that fires early just shows nothing.
 *
 * Response: {"groups": [{"type", "label", "items": [{"title", "url"}]}], "message"}
 */
class Suggest implements HttpGetActionInterface
{
    /**
     * @param JsonFactory $jsonFactory
     * @param ForwardFactory $forwardFactory
     * @param RequestInterface $request
     * @param StorefrontGate $storefrontGate
     * @param BlogSearch $blogSearch
     * @param Config $config
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly JsonFactory $jsonFactory,
        private readonly ForwardFactory $forwardFactory,
        private readonly RequestInterface $request,
        private readonly StorefrontGate $storefrontGate,
        private readonly BlogSearch $blogSearch,
        private readonly Config $config,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Execute the action
     *
     * @return Json|Forward
     */
    public function execute()
    {
        if (!$this->storefrontGate->allows($this->request)) {
            return $this->forwardFactory->create()->forward('noroute');
        }

        $query = PostSearch::normalizeQuery($this->request->getParam(SearchView::QUERY_VAR_NAME));
        $groups = [];
        $searched = mb_strlen($query) >= $this->config->getSearchMinCharacters();

        if ($searched) {
            try {
                foreach ($this->blogSearch->suggest($query, $this->config->getSearchItemsPerGroup()) as $type => $items) {
                    $groups[] = [
                        'type' => $type,
                        'label' => (string) SearchView::tabLabel($type),
                        'items' => $items,
                    ];
                }
            } catch (\Throwable $e) {
                // The dropdown is a convenience; Enter still reaches the full
                // results page, so log and answer empty rather than 500.
                $this->logger->error('[RequestDesk_Blog] live search failed: ' . $e->getMessage());
            }
        }

        return $this->jsonFactory->create()->setData([
            'groups' => $groups,
            // Below the minimum nothing was searched, so "no results" would be untrue.
            'message' => $searched && $groups === []
                ? (string) __('Your live search returned no results. Press Enter to see the full search results.')
                : '',
        ]);
    }
}
