<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Tests\Unit\Model;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Setono\SyliusWishlistPlugin\Model\GuestWishlist;
use Setono\SyliusWishlistPlugin\Model\UserWishlist;

final class GuestWishlistTest extends TestCase
{
    #[Test]
    public function it_has_no_client_id_by_default(): void
    {
        self::assertNull((new GuestWishlist())->getClientId());
    }

    #[Test]
    public function it_sets_and_gets_the_client_id(): void
    {
        $wishlist = new GuestWishlist();
        $wishlist->setClientId('client-123');

        self::assertSame('client-123', $wishlist->getClientId());
    }

    #[Test]
    public function it_is_not_merged_by_default(): void
    {
        self::assertNull((new GuestWishlist())->getMergedInto());
    }

    #[Test]
    public function it_sets_and_gets_the_user_wishlist_it_was_merged_into(): void
    {
        $userWishlist = new UserWishlist();

        $wishlist = new GuestWishlist();
        $wishlist->setMergedInto($userWishlist);

        self::assertSame($userWishlist, $wishlist->getMergedInto());
    }
}
