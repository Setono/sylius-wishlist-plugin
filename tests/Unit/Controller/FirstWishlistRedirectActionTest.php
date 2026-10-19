<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Tests\Unit\Controller;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusWishlistPlugin\Controller\FirstWishlistRedirectAction;
use Setono\SyliusWishlistPlugin\Model\WishlistInterface;
use Setono\SyliusWishlistPlugin\Provider\WishlistProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class FirstWishlistRedirectActionTest extends TestCase
{
    use ProphecyTrait;

    #[Test]
    public function it_redirects_to_the_first_wishlist(): void
    {
        $wishlist = $this->prophesize(WishlistInterface::class);
        $wishlist->getUuid()->willReturn('uuid-1');

        $provider = $this->prophesize(WishlistProviderInterface::class);
        $provider->getWishlists()->willReturn([$wishlist->reveal()]);

        $urlGenerator = $this->prophesize(UrlGeneratorInterface::class);
        $urlGenerator
            ->generate('setono_sylius_wishlist_shop_wishlist_show', ['uuid' => 'uuid-1'])
            ->shouldBeCalledOnce()
            ->willReturn('/en_US/wishlists/uuid-1')
        ;

        $response = (new FirstWishlistRedirectAction($provider->reveal(), $urlGenerator->reveal()))(new Request());

        self::assertSame('/en_US/wishlists/uuid-1', $response->getTargetUrl());
    }

    #[Test]
    public function it_redirects_to_the_referrer_and_flashes_when_there_are_no_wishlists(): void
    {
        $provider = $this->prophesize(WishlistProviderInterface::class);
        $provider->getWishlists()->willReturn([]);

        $urlGenerator = $this->prophesize(UrlGeneratorInterface::class);
        $urlGenerator->generate(Argument::cetera())->shouldNotBeCalled();

        $request = new Request();
        $request->headers->set('referer', '/en_US/products/foo');
        $request->setSession($this->sessionExpectingFlash());

        $response = (new FirstWishlistRedirectAction($provider->reveal(), $urlGenerator->reveal()))($request);

        self::assertSame('/en_US/products/foo', $response->getTargetUrl());
    }

    #[Test]
    public function it_redirects_to_the_homepage_when_there_are_no_wishlists_and_no_referrer(): void
    {
        $provider = $this->prophesize(WishlistProviderInterface::class);
        $provider->getWishlists()->willReturn([]);

        $urlGenerator = $this->prophesize(UrlGeneratorInterface::class);
        $urlGenerator->generate('sylius_shop_homepage')->willReturn('/en_US/');

        $request = new Request();
        $request->setSession($this->sessionExpectingFlash());

        $response = (new FirstWishlistRedirectAction($provider->reveal(), $urlGenerator->reveal()))($request);

        self::assertSame('/en_US/', $response->getTargetUrl());
    }

    private function sessionExpectingFlash(): FlashBagAwareSessionInterface
    {
        $flashBag = $this->prophesize(FlashBagInterface::class);
        $flashBag->add('info', 'setono_sylius_wishlist.no_wishlists')->shouldBeCalledOnce();

        $session = $this->prophesize(FlashBagAwareSessionInterface::class);
        $session->getFlashBag()->willReturn($flashBag->reveal());

        return $session->reveal();
    }
}
