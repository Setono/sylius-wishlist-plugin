<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Tests\Unit\Model;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Setono\SyliusWishlistPlugin\Model\GuestWishlist;

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
}
