<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Tests\Unit\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Setono\SyliusWishlistPlugin\Controller\ShowWishlistAction;
use Setono\SyliusWishlistPlugin\Form\Type\WishlistType;
use Setono\SyliusWishlistPlugin\Model\WishlistInterface;
use Setono\SyliusWishlistPlugin\Repository\WishlistRepositoryInterface;
use Setono\SyliusWishlistPlugin\Security\Voter\WishlistVoter;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Twig\Environment;

final class ShowWishlistActionTest extends TestCase
{
    #[Test]
    public function it_throws_not_found_when_the_wishlist_does_not_exist(): void
    {
        $action = new ShowWishlistAction(
            $this->repository(null),
            $this->formFactory($this->form(false)),
            $this->authorizationChecker(true),
            $this->twig(),
            $this->urlGenerator(),
        );

        $this->expectException(NotFoundHttpException::class);

        $action(new Request(), 'missing');
    }

    #[Test]
    public function it_saves_the_wishlist_and_redirects_with_a_flash_when_the_owner_submits_a_valid_form(): void
    {
        $wishlist = $this->createMock(WishlistInterface::class);

        $repository = $this->repository($wishlist);
        $repository->expects(self::once())->method('add')->with($wishlist);

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator
            ->expects(self::once())
            ->method('generate')
            ->with('setono_sylius_wishlist_shop_wishlist_show', ['uuid' => 'uuid'])
            ->willReturn('/wishlists/uuid')
        ;

        $action = new ShowWishlistAction(
            $repository,
            $this->formFactory($this->form(true)),
            $this->authorizationChecker(true),
            $this->twig(),
            $urlGenerator,
        );

        $session = new Session(new MockArraySessionStorage());
        $request = Request::create('/wishlists/uuid', 'PATCH');
        $request->setSession($session);

        $response = $action($request, 'uuid');

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/wishlists/uuid', $response->getTargetUrl());
        self::assertSame(['setono_sylius_wishlist.wishlist_updated'], $session->getFlashBag()->peek('success'));
    }

    #[Test]
    #[DataProvider('provideUnsafeMethods')]
    public function it_refuses_a_non_owner_submission_before_the_form_touches_the_wishlist(string $method): void
    {
        $wishlist = $this->createMock(WishlistInterface::class);

        $repository = $this->repository($wishlist);
        $repository->expects(self::never())->method('add');

        $form = $this->form(true);
        $form->expects(self::never())->method('handleRequest');

        $authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authorizationChecker
            ->expects(self::once())
            ->method('isGranted')
            ->with(WishlistVoter::EDIT, $wishlist)
            ->willReturn(false)
        ;

        $action = new ShowWishlistAction(
            $repository,
            $this->formFactory($form),
            $authorizationChecker,
            $this->twig(),
            $this->urlGenerator(),
        );

        $this->expectException(NotFoundHttpException::class);

        $action(Request::create('/wishlists/uuid', $method), 'uuid');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideUnsafeMethods(): iterable
    {
        yield 'PATCH' => ['PATCH'];
        yield 'POST' => ['POST'];
    }

    #[Test]
    public function it_builds_the_form_for_the_wishlist_with_the_patch_method(): void
    {
        $wishlist = $this->createMock(WishlistInterface::class);

        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory
            ->expects(self::once())
            ->method('create')
            ->with(WishlistType::class, $wishlist, ['method' => 'PATCH'])
            ->willReturn($this->form(false))
        ;

        $action = new ShowWishlistAction(
            $this->repository($wishlist),
            $formFactory,
            $this->authorizationChecker(true),
            $this->twig(),
            $this->urlGenerator(),
        );

        $action(new Request(), 'uuid');
    }

    #[Test]
    public function it_handles_the_request_and_renders_the_wishlist_with_its_form(): void
    {
        $wishlist = $this->createMock(WishlistInterface::class);
        $request = new Request();
        $formView = new FormView();

        $form = $this->createMock(FormInterface::class);
        $form->expects(self::once())->method('handleRequest')->with($request);
        $form->method('isSubmitted')->willReturn(false);
        $form->method('createView')->willReturn($formView);

        $twig = $this->createMock(Environment::class);
        $twig
            ->expects(self::once())
            ->method('render')
            ->with('@SetonoSyliusWishlistPlugin/shop/wishlist/show.html.twig', [
                'wishlist' => $wishlist,
                'form' => $formView,
            ])
            ->willReturn('rendered')
        ;

        $action = new ShowWishlistAction(
            $this->repository($wishlist),
            $this->formFactory($form),
            $this->authorizationChecker(true),
            $twig,
            $this->urlGenerator(),
        );

        self::assertSame('rendered', $action($request, 'uuid')->getContent());
    }

    #[Test]
    public function it_neither_authorizes_nor_saves_on_a_safe_request(): void
    {
        $repository = $this->repository($this->createMock(WishlistInterface::class));
        $repository->expects(self::never())->method('add');

        $authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authorizationChecker->expects(self::never())->method('isGranted');

        $action = new ShowWishlistAction(
            $repository,
            $this->formFactory($this->form(false)),
            $authorizationChecker,
            $this->twig(),
            $this->urlGenerator(),
        );

        $action(new Request(), 'uuid');
    }

    /**
     * @return MockObject&WishlistRepositoryInterface<WishlistInterface>
     */
    private function repository(?WishlistInterface $wishlist): WishlistRepositoryInterface
    {
        /** @var MockObject&WishlistRepositoryInterface<WishlistInterface> $repository */
        $repository = $this->createMock(WishlistRepositoryInterface::class);
        $repository->method('findOneByUuid')->willReturn($wishlist);

        return $repository;
    }

    /**
     * @return FormInterface<mixed>&MockObject
     */
    private function form(bool $submitted): FormInterface
    {
        $form = $this->createMock(FormInterface::class);
        $form->method('isSubmitted')->willReturn($submitted);
        $form->method('isValid')->willReturn(true);
        $form->method('createView')->willReturn(new FormView());

        return $form;
    }

    /**
     * @param FormInterface<mixed> $form
     */
    private function formFactory(FormInterface $form): FormFactoryInterface
    {
        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->method('create')->willReturn($form);

        return $formFactory;
    }

    private function authorizationChecker(bool $granted): AuthorizationCheckerInterface
    {
        $authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authorizationChecker->method('isGranted')->willReturn($granted);

        return $authorizationChecker;
    }

    private function twig(): Environment
    {
        $twig = $this->createMock(Environment::class);
        $twig->method('render')->willReturn('');

        return $twig;
    }

    private function urlGenerator(): UrlGeneratorInterface
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/wishlists/uuid');

        return $urlGenerator;
    }
}
