<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Tests\Unit\Provider;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Setono\SyliusWishlistPlugin\Model\UserWishlistInterface;
use Setono\SyliusWishlistPlugin\Provider\UserWishlistProvider;
use Setono\SyliusWishlistPlugin\Repository\UserWishlistRepositoryInterface;
use Sylius\Component\User\Model\UserInterface;
use Symfony\Bundle\SecurityBundle\Security;

final class UserWishlistProviderTest extends TestCase
{
    #[Test]
    public function it_returns_no_wishlists_when_there_is_no_logged_in_user(): void
    {
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn(null);

        $repository = $this->createMock(UserWishlistRepositoryInterface::class);
        $repository->expects(self::never())->method('findByUser');

        self::assertSame([], (new UserWishlistProvider($security, $repository))->getWishlists());
    }

    #[Test]
    public function it_returns_the_wishlists_of_the_logged_in_user(): void
    {
        $user = $this->createMock(UserInterface::class);
        $wishlist = $this->createMock(UserWishlistInterface::class);

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $repository = $this->createMock(UserWishlistRepositoryInterface::class);
        $repository->method('findByUser')->with($user)->willReturn([$wishlist]);

        $provider = new UserWishlistProvider($security, $repository);

        self::assertSame([$wishlist], $provider->getWishlists());
        self::assertSame([$wishlist], $provider->getPreSelectedWishlists());
    }
}
