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
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use RequestDesk\Blog\Model\CommentManager;

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
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly RedirectFactory $redirectFactory,
        private readonly ManagerInterface $messageManager,
        private readonly CommentManager $commentManager
    ) {
    }

    /**
     * @return Redirect
     */
    public function execute(): Redirect
    {
        $redirect = $this->redirectFactory->create();
        $postId = (int) $this->request->getParam('post_id');
        $backToPost = $redirect->setPath('blog/post/view', ['id' => $postId]);

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
}
