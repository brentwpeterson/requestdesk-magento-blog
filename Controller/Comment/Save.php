<?php
/**
 * RequestDesk Blog - Comment Submit Controller
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Controller\Comment;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Forward;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\UrlInterface;
use RequestDesk\Blog\Api\PostRepositoryInterface;
use RequestDesk\Blog\Block\BlogUrl;
use RequestDesk\Blog\Block\PostUrl;
use RequestDesk\Blog\Model\CommentManager;
use RequestDesk\Blog\Model\Config;
use RequestDesk\Blog\Model\StorefrontGate;

/**
 * Accepts a guest comment. New comments are stored pending and never shown until
 * an admin approves them. A honeypot field ("website") blocks basic bots. Form
 * key (CSRF) is validated automatically for this POST action.
 */
class Save implements HttpPostActionInterface
{
    /**
     * @param RequestInterface $request
     * @param RedirectFactory $redirectFactory
     * @param ManagerInterface $messageManager
     * @param CommentManager $commentManager
     * @param PostRepositoryInterface $postRepository
     * @param UrlInterface $urlBuilder
     * @param StorefrontGate $storefrontGate
     * @param ForwardFactory $forwardFactory
     * @param Config $config
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly RedirectFactory $redirectFactory,
        private readonly ManagerInterface $messageManager,
        private readonly CommentManager $commentManager,
        private readonly PostRepositoryInterface $postRepository,
        private readonly UrlInterface $urlBuilder,
        private readonly StorefrontGate $storefrontGate,
        private readonly ForwardFactory $forwardFactory,
        private readonly Config $config
    ) {
    }

    /**
     * @return Redirect|Forward
     */
    public function execute()
    {
        // A switched-off blog takes no comments either. The form is gone with
        // the post page, but this endpoint is a plain POST anyone can send.
        if (!$this->storefrontGate->allows($this->request)) {
            return $this->forwardFactory->create()->forward('noroute');
        }

        $redirect = $this->redirectFactory->create();
        $postId = (int) $this->request->getParam('post_id');
        $backToPost = $this->backToPost($redirect, $postId);

        // Honeypot: real users never fill this hidden field. Silently drop bots.
        if (trim((string) $this->request->getParam('website')) !== '') {
            return $backToPost;
        }

        $name = trim((string) $this->request->getParam('author_name'));
        $email = trim((string) $this->request->getParam('author_email'));
        $content = trim((string) $this->request->getParam('content'));

        if ($postId <= 0 || $name === '' || $content === '') {
            $this->messageManager->addErrorMessage(__('Please enter your name and a comment.'));
            return $backToPost;
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->messageManager->addErrorMessage(__('Please enter a valid email address.'));
            return $backToPost;
        }

        // Hiding the form is presentation, not enforcement. This endpoint is a
        // plain POST, so a comment can still be submitted against a post with
        // comments switched off unless the flag is checked server side too.
        try {
            if (!$this->postRepository->getById($postId)->getCommentsEnabled()) {
                $this->messageManager->addErrorMessage(__('Comments are closed for this post.'));
                return $backToPost;
            }
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage(__('Your comment could not be saved. Please try again.'));
            return $backToPost;
        }

        try {
            $this->commentManager->submit($postId, $name, $email ?: null, $content);
            $this->messageManager->addSuccessMessage(
                __('Thanks! Your comment was submitted and will appear once approved.')
            );
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage(__('Your comment could not be saved. Please try again.'));
        }

        return $backToPost;
    }

    /**
     * Send the commenter back to the post they were reading, on its pretty URL so
     * the address bar does not switch to the id form on the way back.
     *
     * @param Redirect $redirect
     * @param int $postId
     * @return Redirect
     */
    private function backToPost(Redirect $redirect, int $postId): Redirect
    {
        try {
            return $redirect->setUrl(
                PostUrl::resolve($this->postRepository->getById($postId), $this->urlBuilder, $this->config->getUrlPrefix())
            );
        } catch (\Throwable $e) {
            // No such post, or it could not be loaded. The id form still routes,
            // and a bad id lands on the same 404 it always did.
            return $redirect->setUrl(
                BlogUrl::resolve($this->config->getUrlPrefix(), 'post/view/id/' . $postId, [], $this->urlBuilder)
            );
        }
    }
}
