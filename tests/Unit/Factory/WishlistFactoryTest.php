<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Tests\Unit\Factory;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Setono\Client\Client;
use Setono\ClientBundle\Context\ClientContextInterface;
use Setono\SyliusWishlistPlugin\Factory\WishlistFactory;
use Setono\SyliusWishlistPlugin\Model\GuestWishlist;
use Setono\SyliusWishlistPlugin\Model\GuestWishlistInterface;
use Setono\SyliusWishlistPlugin\Model\UserWishlist;
use Setono\SyliusWishlistPlugin\Model\UserWishlistInterface;
use Sylius\Component\User\Model\UserInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Contracts\Translation\TranslatorInterface;

final class WishlistFactoryTest extends TestCase
{
    #[Test]
    public function it_creates_a_guest_wishlist(): void
    {
        $wishlist = $this->factory()->createForGuest('client-1');

        self::assertSame('client-1', $wishlist->getClientId());
        self::assertSame('My wishlist', $wishlist->getName());
    }

    #[Test]
    public function it_creates_a_user_wishlist(): void
    {
        $user = $this->createMock(UserInterface::class);

        $wishlist = $this->factory()->createForUser($user);

        self::assertSame($user, $wishlist->getUser());
        self::assertSame('My wishlist', $wishlist->getName());
    }

    #[Test]
    public function it_creates_a_user_wishlist_when_a_user_is_logged_in(): void
    {
        $user = $this->createMock(UserInterface::class);

        $wishlist = $this->factory($user)->createNew();

        self::assertInstanceOf(UserWishlistInterface::class, $wishlist);
        self::assertSame($user, $wishlist->getUser());
    }

    #[Test]
    public function it_creates_a_guest_wishlist_when_no_user_is_logged_in(): void
    {
        $wishlist = $this->factory(null, 'client-9')->createNew();

        self::assertInstanceOf(GuestWishlistInterface::class, $wishlist);
        self::assertSame('client-9', $wishlist->getClientId());
    }

    private function factory(?UserInterface $user = null, string $clientId = 'client-1'): WishlistFactory
    {
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $clientContext = $this->createMock(ClientContextInterface::class);
        $clientContext->method('getClient')->willReturn(new Client($clientId));

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturn('My wishlist');

        return new WishlistFactory($security, $clientContext, $translator, GuestWishlist::class, UserWishlist::class);
    }
}
