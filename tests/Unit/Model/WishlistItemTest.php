<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Tests\Unit\Model;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Setono\SyliusWishlistPlugin\Model\UserWishlist;
use Setono\SyliusWishlistPlugin\Model\WishlistItem;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;

final class WishlistItemTest extends TestCase
{
    #[Test]
    public function it_has_no_id_by_default(): void
    {
        self::assertNull((new WishlistItem())->getId());
    }

    #[Test]
    public function it_defaults_the_quantity_to_one(): void
    {
        self::assertSame(1, (new WishlistItem())->getQuantity());
    }

    #[Test]
    public function it_sets_and_gets_the_quantity(): void
    {
        $item = new WishlistItem();
        $item->setQuantity(5);

        self::assertSame(5, $item->getQuantity());
    }

    #[Test]
    public function it_sets_and_gets_the_wishlist(): void
    {
        $item = new WishlistItem();
        self::assertNull($item->getWishlist());

        $wishlist = new UserWishlist();
        $item->setWishlist($wishlist);
        self::assertSame($wishlist, $item->getWishlist());

        $item->setWishlist(null);
        self::assertNull($item->getWishlist());
    }

    #[Test]
    public function it_sets_and_gets_the_product(): void
    {
        $item = new WishlistItem();
        self::assertNull($item->getProduct());

        $product = $this->createMock(ProductInterface::class);
        $item->setProduct($product);

        self::assertSame($product, $item->getProduct());
    }

    #[Test]
    public function it_derives_the_product_from_the_variant(): void
    {
        $product = $this->createMock(ProductInterface::class);
        $variant = $this->createMock(ProductVariantInterface::class);
        $variant->method('getProduct')->willReturn($product);

        $item = new WishlistItem();
        $item->setVariant($variant);

        self::assertSame($variant, $item->getVariant());
        self::assertSame($product, $item->getProduct());
    }

    #[Test]
    public function it_keeps_the_product_when_the_variant_is_unset(): void
    {
        $product = $this->createMock(ProductInterface::class);

        $item = new WishlistItem();
        $item->setProduct($product);
        $item->setVariant(null);

        self::assertNull($item->getVariant());
        self::assertSame($product, $item->getProduct());
    }
}
