<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Tests\Unit\Model;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Setono\SyliusWishlistPlugin\Model\UserWishlist;
use Sylius\Component\User\Model\UserInterface;

final class UserWishlistTest extends TestCase
{
    #[Test]
    public function it_has_no_user_by_default(): void
    {
        self::assertNull((new UserWishlist())->getUser());
    }

    #[Test]
    public function it_sets_and_gets_the_user(): void
    {
        $wishlist = new UserWishlist();
        $user = $this->createMock(UserInterface::class);

        $wishlist->setUser($user);
        self::assertSame($user, $wishlist->getUser());

        $wishlist->setUser(null);
        self::assertNull($wishlist->getUser());
    }
}
