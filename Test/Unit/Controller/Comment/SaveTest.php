<?php
/**
 * Copyright © RequestDesk. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace RequestDesk\Blog\Test\Unit\Controller\Comment;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Forward;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\UrlInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RequestDesk\Blog\Api\Data\PostInterface;
use RequestDesk\Blog\Api\PostRepositoryInterface;
use RequestDesk\Blog\Controller\Comment\Save;
use RequestDesk\Blog\Model\CommentManager;
use RequestDesk\Blog\Model\Config;
use RequestDesk\Blog\Model\StorefrontGate;

/**
 * The guest comment endpoint.
 *
 * This controller could not be covered end to end over HTTP: form key validation
 * rejects a raw POST before any of this runs, so an integration-style check would
 * pass for the wrong reason and prove nothing. Every rejection path below is
 * therefore exercised here, against the one assertion that actually matters -
 * that CommentManager::submit is never reached.
 *
 * The Allow Comment guard is the reason this file exists. Hiding the form in a
 * template is presentation, not enforcement: blog/comment/save is a plain POST,
 * so without a server-side check anyone could still file a comment against a
 * post whose comments are switched off.
 */
class SaveTest extends TestCase
{
    /** @var RequestInterface&MockObject */
    private RequestInterface $request;

    /** @var ManagerInterface&MockObject */
    private ManagerInterface $messageManager;

    /** @var CommentManager&MockObject */
    private CommentManager $commentManager;

    /** @var PostRepositoryInterface&MockObject */
    private PostRepositoryInterface $postRepository;

    /** @var StorefrontGate&MockObject */
    private StorefrontGate $storefrontGate;

    /** What the gate answers; a test that needs the blog off flips it. */
    private bool $blogReachable = true;

    /** @var Forward&MockObject */
    private Forward $forward;

    private Save $controller;

    protected function setUp(): void
    {
        $this->request = $this->createMock(RequestInterface::class);
        $this->messageManager = $this->createMock(ManagerInterface::class);
        $this->commentManager = $this->createMock(CommentManager::class);
        $this->postRepository = $this->createMock(PostRepositoryInterface::class);

        $redirect = $this->createMock(Redirect::class);
        // The controller sends the commenter back with setUrl(), on the post's
        // pretty URL or the id form when the post cannot be resolved. setPath()
        // is stubbed as well so a regression to it fails on the assertion that
        // matters rather than on a null return.
        $redirect->method('setPath')->willReturnSelf();
        $redirect->method('setUrl')->willReturnSelf();

        $redirectFactory = $this->createMock(RedirectFactory::class);
        $redirectFactory->method('create')->willReturn($redirect);

        $this->storefrontGate = $this->createMock(StorefrontGate::class);
        $this->storefrontGate->method('allows')->willReturnCallback(fn () => $this->blogReachable);
        $this->forward = $this->createMock(Forward::class);
        $this->forward->method('forward')->willReturnSelf();
        $forwardFactory = $this->createMock(ForwardFactory::class);
        $forwardFactory->method('create')->willReturn($this->forward);

        // The commenter is sent back to the post's address, which is built
        // from the URL builder on both the pretty and the id-form path.
        $urlBuilder = $this->createMock(UrlInterface::class);
        $urlBuilder->method('getUrl')->willReturn('https://example.test/blog/post/view/id/7/');

        $config = $this->createMock(Config::class);
        $config->method('getUrlPrefix')->willReturn('blog');

        $this->controller = new Save(
            $this->request,
            $redirectFactory,
            $this->messageManager,
            $this->commentManager,
            $this->postRepository,
            $urlBuilder,
            $this->storefrontGate,
            $forwardFactory,
            $config
        );
    }

    /**
     * @param array<string, string> $overrides
     */
    private function params(array $overrides = []): void
    {
        $params = array_merge([
            'post_id' => '7',
            'website' => '',
            'author_name' => 'Ada',
            'author_email' => 'ada@example.com',
            'content' => 'Nice post',
        ], $overrides);

        $this->request->method('getParam')->willReturnCallback(
            static fn (string $key) => $params[$key] ?? null
        );
    }

    private function postWithComments(bool $enabled): void
    {
        $post = $this->createMock(PostInterface::class);
        $post->method('getCommentsEnabled')->willReturn($enabled);
        $this->postRepository->method('getById')->with(7)->willReturn($post);
    }

    // ------------------------------------------------------- the Allow Comment guard

    public function testCommentIsRejectedWhenThePostHasCommentsDisabled(): void
    {
        $this->params();
        $this->postWithComments(false);

        $this->commentManager->expects($this->never())->method('submit');
        $this->messageManager->expects($this->once())->method('addErrorMessage');

        $this->controller->execute();
    }

    public function testCommentIsAcceptedWhenThePostHasCommentsEnabled(): void
    {
        $this->params();
        $this->postWithComments(true);

        $this->commentManager->expects($this->once())
            ->method('submit')
            ->with(7, 'Ada', 'ada@example.com', 'Nice post');
        $this->messageManager->expects($this->once())->method('addSuccessMessage');

        $this->controller->execute();
    }

    /**
     * A post that cannot be loaded is not an open post. Letting a repository
     * failure fall through to submit would turn "post does not exist" into an
     * orphaned comment row.
     */
    public function testRepositoryFailureRejectsTheComment(): void
    {
        $this->params();
        $this->postRepository->method('getById')
            ->willThrowException(new \RuntimeException('gone'));

        $this->commentManager->expects($this->never())->method('submit');
        $this->messageManager->expects($this->once())->method('addErrorMessage');

        $this->controller->execute();
    }

    // ------------------------------------------------------------- the other guards

    /**
     * The honeypot rejects silently. Telling a bot why it failed just teaches it
     * to fill the field in next time.
     */
    public function testFilledHoneypotIsRejectedWithoutAMessage(): void
    {
        $this->params(['website' => 'http://spam.example']);

        $this->commentManager->expects($this->never())->method('submit');
        $this->messageManager->expects($this->never())->method('addErrorMessage');
        $this->messageManager->expects($this->never())->method('addSuccessMessage');

        $this->controller->execute();
    }

    public function testMissingNameIsRejected(): void
    {
        $this->params(['author_name' => '   ']);

        $this->commentManager->expects($this->never())->method('submit');
        $this->messageManager->expects($this->once())->method('addErrorMessage');

        $this->controller->execute();
    }

    public function testMissingContentIsRejected(): void
    {
        $this->params(['content' => '']);

        $this->commentManager->expects($this->never())->method('submit');
        $this->messageManager->expects($this->once())->method('addErrorMessage');

        $this->controller->execute();
    }

    public function testMissingPostIdIsRejected(): void
    {
        $this->params(['post_id' => '0']);

        $this->commentManager->expects($this->never())->method('submit');
        $this->messageManager->expects($this->once())->method('addErrorMessage');

        $this->controller->execute();
    }

    public function testMalformedEmailIsRejected(): void
    {
        $this->params(['author_email' => 'not-an-email']);

        $this->commentManager->expects($this->never())->method('submit');
        $this->messageManager->expects($this->once())->method('addErrorMessage');

        $this->controller->execute();
    }

    /**
     * Email is optional. An empty one must reach submit as null, not as ''.
     */
    public function testEmptyEmailIsAllowedAndPassedAsNull(): void
    {
        $this->params(['author_email' => '']);
        $this->postWithComments(true);

        $this->commentManager->expects($this->once())
            ->method('submit')
            ->with(7, 'Ada', null, 'Nice post');

        $this->controller->execute();
    }

    // ------------------------------------------------------- Enable Blog

    /**
     * With the blog switched off the post page 404s and its form is gone, but
     * this endpoint is a plain POST. Without the gate a comment could still be
     * filed against a blog the store has taken down.
     */
    public function testNothingIsAcceptedWhileTheBlogIsSwitchedOff(): void
    {
        $this->blogReachable = false;
        $this->params();
        $this->postWithComments(true);

        $this->commentManager->expects($this->never())->method('submit');
        $this->messageManager->expects($this->never())->method('addSuccessMessage');
        $this->forward->expects($this->once())->method('forward')->with('noroute');

        $this->assertSame($this->forward, $this->controller->execute());
    }
}
