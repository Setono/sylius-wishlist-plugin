<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Tests\Unit\Provider;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Setono\SyliusWishlistPlugin\Model\WishlistInterface;
use Setono\SyliusWishlistPlugin\Provider\CachedWishlistProvider;
use Setono\SyliusWishlistPlugin\Provider\WishlistProviderInterface;

final class CachedWishlistProviderTest extends TestCase
{
    #[Test]
    public function it_returns_the_wishlists_from_the_decorated_provider(): void
    {
        $wishlist = $this->createMock(WishlistInterface::class);

        $decorated = $this->createMock(WishlistProviderInterface::class);
        $decorated->method('getWishlists')->willReturn([$wishlist]);

        self::assertSame([$wishlist], (new CachedWishlistProvider($decorated))->getWishlists());
    }

    #[Test]
    public function it_only_asks_the_decorated_provider_for_the_wishlists_once(): void
    {
        $decorated = $this->createMock(WishlistProviderInterface::class);
        $decorated->expects(self::once())->method('getWishlists')->willReturn([]);

        $provider = new CachedWishlistProvider($decorated);
        $provider->getWishlists();
        $provider->getWishlists();
    }

    #[Test]
    public function it_only_asks_the_decorated_provider_for_the_preselected_wishlists_once(): void
    {
        $decorated = $this->createMock(WishlistProviderInterface::class);
        $decorated->expects(self::once())->method('getPreSelectedWishlists')->willReturn([]);

        $provider = new CachedWishlistProvider($decorated);
        $provider->getPreSelectedWishlists();
        $provider->getPreSelectedWishlists();
    }

    #[Test]
    public function it_asks_the_decorated_provider_again_after_a_reset(): void
    {
        $decorated = $this->createMock(WishlistProviderInterface::class);
        $decorated->expects(self::exactly(2))->method('getWishlists')->willReturn([]);
        $decorated->expects(self::exactly(2))->method('getPreSelectedWishlists')->willReturn([]);

        $provider = new CachedWishlistProvider($decorated);
        $provider->getWishlists();
        $provider->getPreSelectedWishlists();

        $provider->reset();

        $provider->getWishlists();
        $provider->getPreSelectedWishlists();
    }
}
