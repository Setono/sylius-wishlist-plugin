<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Tests\Unit\Provider;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusWishlistPlugin\Model\UserWishlistInterface;
use Setono\SyliusWishlistPlugin\Provider\UserWishlistProvider;
use Setono\SyliusWishlistPlugin\Repository\UserWishlistRepositoryInterface;
use Sylius\Component\User\Model\UserInterface;
use Symfony\Bundle\SecurityBundle\Security;

final class UserWishlistProviderTest extends TestCase
{
    use ProphecyTrait;

    #[Test]
    public function it_returns_no_wishlists_when_there_is_no_logged_in_user(): void
    {
        $security = $this->prophesize(Security::class);
        $security->getUser()->willReturn(null);

        $repository = $this->prophesize(UserWishlistRepositoryInterface::class);
        $repository->findByUser(Argument::cetera())->shouldNotBeCalled();

        self::assertSame([], (new UserWishlistProvider($security->reveal(), $repository->reveal()))->getWishlists());
    }

    #[Test]
    public function it_returns_the_wishlists_of_the_logged_in_user(): void
    {
        $user = $this->prophesize(UserInterface::class)->reveal();
        $wishlist = $this->prophesize(UserWishlistInterface::class)->reveal();

        $security = $this->prophesize(Security::class);
        $security->getUser()->willReturn($user);

        $repository = $this->prophesize(UserWishlistRepositoryInterface::class);
        $repository->findByUser($user)->willReturn([$wishlist]);

        $provider = new UserWishlistProvider($security->reveal(), $repository->reveal());

        self::assertSame([$wishlist], $provider->getWishlists());
        self::assertSame([$wishlist], $provider->getPreSelectedWishlists());
    }
}
