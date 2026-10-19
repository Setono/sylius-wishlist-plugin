<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Tests\Unit\Provider;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusWishlistPlugin\Model\WishlistInterface;
use Setono\SyliusWishlistPlugin\Provider\CachedWishlistProvider;
use Setono\SyliusWishlistPlugin\Provider\WishlistProviderInterface;

final class CachedWishlistProviderTest extends TestCase
{
    use ProphecyTrait;

    #[Test]
    public function it_returns_the_wishlists_from_the_decorated_provider(): void
    {
        $wishlist = $this->prophesize(WishlistInterface::class)->reveal();

        $decorated = $this->prophesize(WishlistProviderInterface::class);
        $decorated->getWishlists()->willReturn([$wishlist]);

        self::assertSame([$wishlist], (new CachedWishlistProvider($decorated->reveal()))->getWishlists());
    }

    #[Test]
    public function it_only_asks_the_decorated_provider_for_the_wishlists_once(): void
    {
        $decorated = $this->prophesize(WishlistProviderInterface::class);
        $decorated->getWishlists()->shouldBeCalledOnce()->willReturn([]);

        $provider = new CachedWishlistProvider($decorated->reveal());
        $provider->getWishlists();
        $provider->getWishlists();
    }

    #[Test]
    public function it_only_asks_the_decorated_provider_for_the_preselected_wishlists_once(): void
    {
        $decorated = $this->prophesize(WishlistProviderInterface::class);
        $decorated->getPreSelectedWishlists()->shouldBeCalledOnce()->willReturn([]);

        $provider = new CachedWishlistProvider($decorated->reveal());
        $provider->getPreSelectedWishlists();
        $provider->getPreSelectedWishlists();
    }

    #[Test]
    public function it_asks_the_decorated_provider_again_after_a_reset(): void
    {
        $decorated = $this->prophesize(WishlistProviderInterface::class);
        $decorated->getWishlists()->shouldBeCalledTimes(2)->willReturn([]);
        $decorated->getPreSelectedWishlists()->shouldBeCalledTimes(2)->willReturn([]);

        $provider = new CachedWishlistProvider($decorated->reveal());
        $provider->getWishlists();
        $provider->getPreSelectedWishlists();

        $provider->reset();

        $provider->getWishlists();
        $provider->getPreSelectedWishlists();
    }
}
