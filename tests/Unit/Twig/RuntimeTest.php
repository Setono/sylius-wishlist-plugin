<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Tests\Unit\Twig;

use PHPUnit\Framework\TestCase;
use Setono\SyliusWishlistPlugin\Checker\WishlistCheckerInterface;
use Setono\SyliusWishlistPlugin\Model\WishlistInterface;
use Setono\SyliusWishlistPlugin\Provider\WishlistProviderInterface;
use Setono\SyliusWishlistPlugin\Twig\Runtime;
use Sylius\Component\Core\Model\ProductInterface;

final class RuntimeTest extends TestCase
{
    /** @test */
    public function it_delegates_on_wishlist_to_the_checker(): void
    {
        $product = $this->createMock(ProductInterface::class);

        $checker = $this->createMock(WishlistCheckerInterface::class);
        $checker->method('onWishlist')->with($product)->willReturn(true);

        self::assertTrue($this->runtime($this->createMock(WishlistProviderInterface::class), $checker)->onWishlist($product));
    }

    /** @test */
    public function it_has_a_wishlist_when_the_provider_returns_at_least_one(): void
    {
        $provider = $this->createMock(WishlistProviderInterface::class);
        $provider->method('getWishlists')->willReturn([$this->createMock(WishlistInterface::class)]);

        self::assertTrue($this->runtime($provider, $this->createMock(WishlistCheckerInterface::class))->hasWishlist());
    }

    /** @test */
    public function it_has_no_wishlist_when_the_provider_returns_none(): void
    {
        $provider = $this->createMock(WishlistProviderInterface::class);
        $provider->method('getWishlists')->willReturn([]);

        self::assertFalse($this->runtime($provider, $this->createMock(WishlistCheckerInterface::class))->hasWishlist());
    }

    private function runtime(WishlistProviderInterface $provider, WishlistCheckerInterface $checker): Runtime
    {
        return new Runtime($provider, $checker);
    }
}
