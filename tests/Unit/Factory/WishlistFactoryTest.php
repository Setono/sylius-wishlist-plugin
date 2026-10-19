<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Tests\Unit\Factory;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
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
    use ProphecyTrait;

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
        $user = $this->prophesize(UserInterface::class)->reveal();

        $wishlist = $this->factory()->createForUser($user);

        self::assertSame($user, $wishlist->getUser());
        self::assertSame('My wishlist', $wishlist->getName());
    }

    #[Test]
    public function it_creates_a_user_wishlist_when_a_user_is_logged_in(): void
    {
        $user = $this->prophesize(UserInterface::class)->reveal();

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
        $security = $this->prophesize(Security::class);
        $security->getUser()->willReturn($user);

        $clientContext = $this->prophesize(ClientContextInterface::class);
        $clientContext->getClient()->willReturn(new Client($clientId));

        $translator = $this->prophesize(TranslatorInterface::class);
        $translator->trans(Argument::cetera())->willReturn('My wishlist');

        return new WishlistFactory($security->reveal(), $clientContext->reveal(), $translator->reveal(), GuestWishlist::class, UserWishlist::class);
    }
}
