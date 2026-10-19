<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Tests\Unit\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusWishlistPlugin\Controller\ShowWishlistAction;
use Setono\SyliusWishlistPlugin\Form\Type\WishlistType;
use Setono\SyliusWishlistPlugin\Model\GuestWishlist;
use Setono\SyliusWishlistPlugin\Model\UserWishlist;
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
    use ProphecyTrait;

    #[Test]
    public function it_throws_not_found_when_the_wishlist_does_not_exist(): void
    {
        $action = new ShowWishlistAction(
            $this->repository(null)->reveal(),
            $this->formFactory($this->form(false)->reveal()),
            $this->authorizationChecker(true),
            $this->twig(),
            $this->urlGenerator(),
        );

        $this->expectException(NotFoundHttpException::class);

        $action(new Request(), 'missing');
    }

    #[Test]
    #[DataProvider('provideMethods')]
    public function it_redirects_the_owner_from_a_merged_guest_wishlist_to_the_user_wishlist(string $method): void
    {
        $userWishlist = new UserWishlist();
        $guestWishlist = new GuestWishlist();
        $guestWishlist->setMergedInto($userWishlist);

        $form = $this->form(true);
        $form->handleRequest(Argument::cetera())->shouldNotBeCalled();

        $authorizationChecker = $this->prophesize(AuthorizationCheckerInterface::class);
        $authorizationChecker
            ->isGranted(WishlistVoter::EDIT, $userWishlist)
            ->shouldBeCalledOnce()
            ->willReturn(true)
        ;

        $urlGenerator = $this->prophesize(UrlGeneratorInterface::class);
        $urlGenerator
            ->generate('setono_sylius_wishlist_shop_wishlist_show', ['uuid' => $userWishlist->getUuid()])
            ->shouldBeCalledOnce()
            ->willReturn('/wishlists/user-wishlist')
        ;

        $action = new ShowWishlistAction(
            $this->repository($guestWishlist)->reveal(),
            $this->formFactory($form->reveal()),
            $authorizationChecker->reveal(),
            $this->twig(),
            $urlGenerator->reveal(),
        );

        $response = $action(Request::create('/wishlists/uuid', $method), 'uuid');

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/wishlists/user-wishlist', $response->getTargetUrl());
    }

    #[Test]
    #[DataProvider('provideMethods')]
    public function it_throws_not_found_to_anyone_else_viewing_a_merged_guest_wishlist(string $method): void
    {
        $userWishlist = new UserWishlist();
        $guestWishlist = new GuestWishlist();
        $guestWishlist->setMergedInto($userWishlist);

        $authorizationChecker = $this->prophesize(AuthorizationCheckerInterface::class);
        $authorizationChecker
            ->isGranted(WishlistVoter::EDIT, $userWishlist)
            ->shouldBeCalledOnce()
            ->willReturn(false)
        ;

        $action = new ShowWishlistAction(
            $this->repository($guestWishlist)->reveal(),
            $this->formFactory($this->form(false)->reveal()),
            $authorizationChecker->reveal(),
            $this->twig(),
            $this->urlGenerator(),
        );

        $this->expectException(NotFoundHttpException::class);

        $action(Request::create('/wishlists/uuid', $method), 'uuid');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideMethods(): iterable
    {
        yield 'GET' => ['GET'];
        yield 'PATCH' => ['PATCH'];
    }

    #[Test]
    public function it_renders_a_guest_wishlist_that_has_not_been_merged(): void
    {
        $guestWishlist = new GuestWishlist();

        $twig = $this->prophesize(Environment::class);
        $twig
            ->render('@SetonoSyliusWishlistPlugin/shop/wishlist/show.html.twig', Argument::that(
                static fn (array $context): bool => $context['wishlist'] === $guestWishlist,
            ))
            ->shouldBeCalledOnce()
            ->willReturn('rendered')
        ;

        $action = new ShowWishlistAction(
            $this->repository($guestWishlist)->reveal(),
            $this->formFactory($this->form(false)->reveal()),
            $this->authorizationChecker(false),
            $twig->reveal(),
            $this->urlGenerator(),
        );

        self::assertSame('rendered', $action(new Request(), 'uuid')->getContent());
    }

    #[Test]
    public function it_saves_the_wishlist_and_redirects_with_a_flash_when_the_owner_submits_a_valid_form(): void
    {
        $wishlist = $this->prophesize(WishlistInterface::class)->reveal();

        $repository = $this->repository($wishlist);
        $repository->add($wishlist)->shouldBeCalledOnce();

        $urlGenerator = $this->prophesize(UrlGeneratorInterface::class);
        $urlGenerator
            ->generate('setono_sylius_wishlist_shop_wishlist_show', ['uuid' => 'uuid'])
            ->shouldBeCalledOnce()
            ->willReturn('/wishlists/uuid')
        ;

        $action = new ShowWishlistAction(
            $repository->reveal(),
            $this->formFactory($this->form(true)->reveal()),
            $this->authorizationChecker(true),
            $this->twig(),
            $urlGenerator->reveal(),
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
        $wishlist = $this->prophesize(WishlistInterface::class)->reveal();

        $repository = $this->repository($wishlist);
        $repository->add(Argument::cetera())->shouldNotBeCalled();

        $form = $this->form(true);
        $form->handleRequest(Argument::cetera())->shouldNotBeCalled();

        $authorizationChecker = $this->prophesize(AuthorizationCheckerInterface::class);
        $authorizationChecker
            ->isGranted(WishlistVoter::EDIT, $wishlist)
            ->shouldBeCalledOnce()
            ->willReturn(false)
        ;

        $action = new ShowWishlistAction(
            $repository->reveal(),
            $this->formFactory($form->reveal()),
            $authorizationChecker->reveal(),
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
        $wishlist = $this->prophesize(WishlistInterface::class)->reveal();

        $formFactory = $this->prophesize(FormFactoryInterface::class);
        $formFactory
            ->create(WishlistType::class, $wishlist, ['method' => 'PATCH'])
            ->shouldBeCalledOnce()
            ->willReturn($this->form(false)->reveal())
        ;

        $action = new ShowWishlistAction(
            $this->repository($wishlist)->reveal(),
            $formFactory->reveal(),
            $this->authorizationChecker(true),
            $this->twig(),
            $this->urlGenerator(),
        );

        $action(new Request(), 'uuid');
    }

    #[Test]
    public function it_handles_the_request_and_renders_the_wishlist_with_its_form(): void
    {
        $wishlist = $this->prophesize(WishlistInterface::class)->reveal();
        $request = new Request();
        $formView = new FormView();

        $form = $this->prophesize(FormInterface::class);
        $form->handleRequest($request)->shouldBeCalledOnce()->willReturn($form);
        $form->isSubmitted()->willReturn(false);
        $form->createView()->willReturn($formView);

        $twig = $this->prophesize(Environment::class);
        $twig
            ->render('@SetonoSyliusWishlistPlugin/shop/wishlist/show.html.twig', [
                'wishlist' => $wishlist,
                'form' => $formView,
            ])
            ->shouldBeCalledOnce()
            ->willReturn('rendered')
        ;

        $action = new ShowWishlistAction(
            $this->repository($wishlist)->reveal(),
            $this->formFactory($form->reveal()),
            $this->authorizationChecker(true),
            $twig->reveal(),
            $this->urlGenerator(),
        );

        self::assertSame('rendered', $action($request, 'uuid')->getContent());
    }

    #[Test]
    public function it_neither_authorizes_nor_saves_on_a_safe_request(): void
    {
        $repository = $this->repository($this->prophesize(WishlistInterface::class)->reveal());
        $repository->add(Argument::cetera())->shouldNotBeCalled();

        $authorizationChecker = $this->prophesize(AuthorizationCheckerInterface::class);
        $authorizationChecker->isGranted(Argument::cetera())->shouldNotBeCalled();

        $action = new ShowWishlistAction(
            $repository->reveal(),
            $this->formFactory($this->form(false)->reveal()),
            $authorizationChecker->reveal(),
            $this->twig(),
            $this->urlGenerator(),
        );

        $action(new Request(), 'uuid');
    }

    /**
     * @return ObjectProphecy<WishlistRepositoryInterface<WishlistInterface>>
     */
    private function repository(?WishlistInterface $wishlist): ObjectProphecy
    {
        /** @var ObjectProphecy<WishlistRepositoryInterface<WishlistInterface>> $repository */
        $repository = $this->prophesize(WishlistRepositoryInterface::class);
        $repository->findOneByUuid(Argument::any())->willReturn($wishlist);

        return $repository;
    }

    /**
     * @return ObjectProphecy<FormInterface<mixed>>
     */
    private function form(bool $submitted): ObjectProphecy
    {
        /** @var ObjectProphecy<FormInterface<mixed>> $form */
        $form = $this->prophesize(FormInterface::class);
        $form->handleRequest(Argument::any())->willReturn($form);
        $form->isSubmitted()->willReturn($submitted);
        $form->isValid()->willReturn(true);
        $form->createView()->willReturn(new FormView());

        return $form;
    }

    /**
     * @param FormInterface<mixed> $form
     */
    private function formFactory(FormInterface $form): FormFactoryInterface
    {
        $formFactory = $this->prophesize(FormFactoryInterface::class);
        $formFactory->create(Argument::cetera())->willReturn($form);

        return $formFactory->reveal();
    }

    private function authorizationChecker(bool $granted): AuthorizationCheckerInterface
    {
        $authorizationChecker = $this->prophesize(AuthorizationCheckerInterface::class);
        $authorizationChecker->isGranted(Argument::cetera())->willReturn($granted);

        return $authorizationChecker->reveal();
    }

    private function twig(): Environment
    {
        $twig = $this->prophesize(Environment::class);
        $twig->render(Argument::cetera())->willReturn('');

        return $twig->reveal();
    }

    private function urlGenerator(): UrlGeneratorInterface
    {
        $urlGenerator = $this->prophesize(UrlGeneratorInterface::class);
        $urlGenerator->generate(Argument::cetera())->willReturn('/wishlists/uuid');

        return $urlGenerator->reveal();
    }
}
