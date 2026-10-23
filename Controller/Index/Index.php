<?php
/**
 * RequestDesk Blog - Frontend Index (post list)
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Controller\Index;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Forward;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use RequestDesk\Blog\Model\Config;
use RequestDesk\Blog\Model\ListingPageRange;
use RequestDesk\Blog\Model\StorefrontGate;

/**
 * Renders the blog listing at /<prefix>, /blog unless the store sets another.
 */
class Index implements HttpGetActionInterface
{
    /**
     * @param PageFactory $pageFactory
     * @param ForwardFactory $forwardFactory
     * @param RequestInterface $request
     * @param StorefrontGate $storefrontGate
     * @param ListingPageRange $listingPageRange
     * @param Config $config
     */
    public function __construct(
        private readonly PageFactory $pageFactory,
        private readonly ForwardFactory $forwardFactory,
        private readonly RequestInterface $request,
        private readonly StorefrontGate $storefrontGate,
        private readonly ListingPageRange $listingPageRange,
        private readonly Config $config
    ) {
    }

    /**
     * @inheritdoc
     *
     * @return Page|Forward
     */
    public function execute()
    {
        if (!$this->storefrontGate->allows($this->request)) {
            return $this->forwardFactory->create()->forward('noroute');
        }

        if ($this->listingPageRange->isOutOfRange()) {
            return $this->listingPageRange->notFound();
        }

        $page = $this->pageFactory->create();
        // The meta title and description from SEO Settings, which the
        // migration copies from Amasty's; the h1 stays the Blog Title.
        $pageConfig = $page->getConfig();
        $metaTitle = $this->config->getIndexMetaTitle();
        $pageConfig->getTitle()->set($metaTitle !== '' ? $metaTitle : $this->config->getBlogTitle());
        $description = $this->config->getIndexMetaDescription();
        if ($description !== '') {
            $pageConfig->setDescription($description);
        }
        return $page;
    }
}
