<?php
/**
 * Copyright © RequestDesk. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace RequestDesk\Blog\Test\Unit\Controller;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\View\Page\Config as PageConfig;
use Magento\Framework\View\Page\Title;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RequestDesk\Blog\Controller\Author\View as AuthorView;
use RequestDesk\Blog\Controller\Tag\View as TagView;
use RequestDesk\Blog\Model\AuthorResolver;
use RequestDesk\Blog\Model\StorefrontGate;
use RequestDesk\Blog\Model\TagResolver;

/**
 * Author and tag pages say what they are in their title.
 *
 * Amasty Blog titled them "Articles by Name" and "Posts tagged 'Name'". The
 * module used the bare name, and the first Magento Association rehearsal showed
 * 171 archive titles changing.
 */
class ArchiveTitleTest extends TestCase
{
    /** @var Title&MockObject */
    private Title $title;

    /** @var PageConfig&MockObject */
    private PageConfig $pageConfig;

    private PageFactory $pageFactory;

    private StorefrontGate $gate;

    private RequestInterface $request;

    private ForwardFactory $forwardFactory;

    protected function setUp(): void
    {
        $this->title = $this->createMock(Title::class);
        $this->pageConfig = $this->createMock(PageConfig::class);
        $this->pageConfig->method('getTitle')->willReturn($this->title);

        $page = $this->createMock(Page::class);
        $page->method('getConfig')->willReturn($this->pageConfig);
        $this->pageFactory = $this->createMock(PageFactory::class);
        $this->pageFactory->method('create')->willReturn($page);

        $this->gate = $this->createMock(StorefrontGate::class);
        $this->gate->method('allows')->willReturn(true);
        $this->request = $this->createMock(RequestInterface::class);
        $this->request->method('getParam')->with('id')->willReturn('7');
        $this->forwardFactory = $this->createMock(ForwardFactory::class);
    }

    private function authorView(?array $author): AuthorView
    {
        $resolver = $this->createMock(AuthorResolver::class);
        $resolver->method('getAuthor')->willReturn($author);

        return new AuthorView($this->pageFactory, $this->forwardFactory, $this->request, $this->gate, $resolver);
    }

    public function testTheAuthorTitleSaysArticlesBy(): void
    {
        $this->title->expects($this->once())->method('set')->with('Articles by Mathias Schreiber');
        $this->pageConfig->expects($this->never())->method('setDescription');

        $this->authorView(['name' => 'Mathias Schreiber', 'bio' => ''])->execute();
    }

    public function testTheAuthorDescriptionComesFromTheBio(): void
    {
        $this->title->method('set');
        $this->pageConfig->expects($this->once())->method('setDescription')
            ->with('Gary Yang is the senior vice president of global partnerships.');

        $this->authorView([
            'name' => 'Gary Yang',
            'bio' => "<p>Gary Yang is the senior vice president\n of global   partnerships.</p>",
        ])->execute();
    }

    public function testTheTagTitleSaysPostsTagged(): void
    {
        $resolver = $this->createMock(TagResolver::class);
        $resolver->method('getTag')->willReturn(['name' => 'Magento']);
        $this->title->expects($this->once())->method('set')->with("Posts tagged 'Magento'");

        (new TagView($this->pageFactory, $this->forwardFactory, $this->request, $this->gate, $resolver))->execute();
    }
}
