<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Tests\Unit\Controller;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Setono\SyliusWishlistPlugin\Controller\FirstWishlistRedirectAction;
use Setono\SyliusWishlistPlugin\Model\WishlistInterface;
use Setono\SyliusWishlistPlugin\Provider\WishlistProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class FirstWishlistRedirectActionTest extends TestCase
{
    #[Test]
    public function it_redirects_to_the_first_wishlist(): void
    {
        $wishlist = $this->createMock(WishlistInterface::class);
        $wishlist->method('getUuid')->willReturn('uuid-1');

        $provider = $this->createMock(WishlistProviderInterface::class);
        $provider->method('getWishlists')->willReturn([$wishlist]);

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects(self::once())
            ->method('generate')
            ->with('setono_sylius_wishlist_shop_wishlist_show', ['uuid' => 'uuid-1'])
            ->willReturn('/en_US/wishlists/uuid-1')
        ;

        $response = (new FirstWishlistRedirectAction($provider, $urlGenerator))(new Request());

        self::assertSame('/en_US/wishlists/uuid-1', $response->getTargetUrl());
    }

    #[Test]
    public function it_redirects_to_the_referrer_and_flashes_when_there_are_no_wishlists(): void
    {
        $provider = $this->createMock(WishlistProviderInterface::class);
        $provider->method('getWishlists')->willReturn([]);

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects(self::never())->method('generate');

        $request = new Request();
        $request->headers->set('referer', '/en_US/products/foo');
        $request->setSession($this->sessionExpectingFlash());

        $response = (new FirstWishlistRedirectAction($provider, $urlGenerator))($request);

        self::assertSame('/en_US/products/foo', $response->getTargetUrl());
    }

    #[Test]
    public function it_redirects_to_the_homepage_when_there_are_no_wishlists_and_no_referrer(): void
    {
        $provider = $this->createMock(WishlistProviderInterface::class);
        $provider->method('getWishlists')->willReturn([]);

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->with('sylius_shop_homepage')->willReturn('/en_US/');

        $request = new Request();
        $request->setSession($this->sessionExpectingFlash());

        $response = (new FirstWishlistRedirectAction($provider, $urlGenerator))($request);

        self::assertSame('/en_US/', $response->getTargetUrl());
    }

    private function sessionExpectingFlash(): FlashBagAwareSessionInterface
    {
        $flashBag = $this->createMock(FlashBagInterface::class);
        $flashBag->expects(self::once())
            ->method('add')
            ->with('info', 'setono_sylius_wishlist.no_wishlists')
        ;

        $session = $this->createMock(FlashBagAwareSessionInterface::class);
        $session->method('getFlashBag')->willReturn($flashBag);

        return $session;
    }
}
