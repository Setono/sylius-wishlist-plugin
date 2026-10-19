<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Tests\Unit\Checker;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Setono\SyliusWishlistPlugin\Checker\WishlistChecker;
use Setono\SyliusWishlistPlugin\Model\WishlistInterface;
use Setono\SyliusWishlistPlugin\Provider\WishlistProviderInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;

final class WishlistCheckerTest extends TestCase
{
    #[Test]
    public function it_reports_a_product_on_a_wishlist(): void
    {
        $product = $this->createMock(ProductInterface::class);

        $wishlist = $this->createMock(WishlistInterface::class);
        $wishlist->method('hasProduct')->with($product)->willReturn(true);

        self::assertTrue($this->checker([$wishlist])->onWishlist($product));
    }

    #[Test]
    public function it_reports_a_product_not_on_any_wishlist(): void
    {
        $product = $this->createMock(ProductInterface::class);

        $wishlist = $this->createMock(WishlistInterface::class);
        $wishlist->method('hasProduct')->willReturn(false);

        self::assertFalse($this->checker([$wishlist])->onWishlist($product));
    }

    #[Test]
    public function it_reports_a_product_variant_on_a_wishlist(): void
    {
        $variant = $this->createMock(ProductVariantInterface::class);

        $wishlist = $this->createMock(WishlistInterface::class);
        $wishlist->method('hasProductVariant')->with($variant)->willReturn(true);

        self::assertTrue($this->checker([$wishlist])->onWishlist($variant));
    }

    #[Test]
    public function it_reports_false_when_there_are_no_wishlists(): void
    {
        self::assertFalse($this->checker([])->onWishlist($this->createMock(ProductInterface::class)));
    }

    /**
     * @param list<WishlistInterface> $wishlists
     */
    private function checker(array $wishlists): WishlistChecker
    {
        $provider = $this->createMock(WishlistProviderInterface::class);
        $provider->method('getWishlists')->willReturn($wishlists);

        return new WishlistChecker($provider);
    }
}
