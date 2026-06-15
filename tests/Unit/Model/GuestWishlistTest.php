<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Tests\Unit\Model;

use PHPUnit\Framework\TestCase;
use Setono\SyliusWishlistPlugin\Model\GuestWishlist;
use Sylius\Component\User\Model\UserInterface;

final class GuestWishlistTest extends TestCase
{
    /** @test */
    public function it_has_no_client_id_by_default(): void
    {
        self::assertNull((new GuestWishlist())->getClientId());
    }

    /** @test */
    public function it_sets_and_gets_the_client_id(): void
    {
        $wishlist = new GuestWishlist();
        $wishlist->setClientId('client-123');

        self::assertSame('client-123', $wishlist->getClientId());
    }

    /** @test */
    public function it_converts_itself_to_a_user_wishlist_by_assigning_the_user(): void
    {
        $wishlist = new GuestWishlist();
        $user = $this->createMock(UserInterface::class);

        $wishlist->convertToUserWishlist($user);

        $property = new \ReflectionProperty(GuestWishlist::class, 'user');

        self::assertSame($user, $property->getValue($wishlist));
    }
}
