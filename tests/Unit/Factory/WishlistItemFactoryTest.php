<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Tests\Unit\Factory;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusWishlistPlugin\Factory\WishlistItemFactory;
use Setono\SyliusWishlistPlugin\Model\WishlistItem;
use Setono\SyliusWishlistPlugin\Model\WishlistItemInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Resource\Factory\FactoryInterface;

final class WishlistItemFactoryTest extends TestCase
{
    use ProphecyTrait;

    #[Test]
    public function it_creates_a_new_wishlist_item_via_the_decorated_factory(): void
    {
        $item = new WishlistItem();

        self::assertSame($item, $this->factory($item)->createNew());
    }

    #[Test]
    public function it_creates_an_item_for_a_configurable_product(): void
    {
        $product = $this->prophesize(ProductInterface::class);
        $product->isSimple()->willReturn(false);

        $item = $this->factory(new WishlistItem())->createWithProduct($product->reveal(), 3);

        self::assertSame($product->reveal(), $item->getProduct());
        self::assertNull($item->getVariant());
        self::assertSame(3, $item->getQuantity());
    }

    #[Test]
    public function it_creates_an_item_for_a_simple_product_using_its_first_variant(): void
    {
        $product = $this->prophesize(ProductInterface::class);
        $variant = $this->prophesize(ProductVariantInterface::class);
        $variant->getProduct()->willReturn($product->reveal());
        $product->isSimple()->willReturn(true);
        $product->getVariants()->willReturn(new ArrayCollection([$variant->reveal()]));

        $item = $this->factory(new WishlistItem())->createWithProduct($product->reveal());

        self::assertSame($variant->reveal(), $item->getVariant());
        self::assertSame(1, $item->getQuantity());
    }

    #[Test]
    public function it_creates_an_item_for_a_product_variant(): void
    {
        $product = $this->prophesize(ProductInterface::class);
        $variant = $this->prophesize(ProductVariantInterface::class);
        $variant->getProduct()->willReturn($product->reveal());

        $item = $this->factory(new WishlistItem())->createWithProductVariant($variant->reveal(), 4);

        self::assertSame($variant->reveal(), $item->getVariant());
        self::assertSame(4, $item->getQuantity());
    }

    #[Test]
    public function it_defaults_the_quantity_to_one_for_a_product_variant(): void
    {
        $variant = $this->prophesize(ProductVariantInterface::class);
        $variant->getProduct()->willReturn($this->prophesize(ProductInterface::class)->reveal());

        $item = $this->factory(new WishlistItem())->createWithProductVariant($variant->reveal());

        self::assertSame(1, $item->getQuantity());
    }

    #[Test]
    public function it_rejects_a_simple_product_without_variants(): void
    {
        $product = $this->prophesize(ProductInterface::class);
        $product->isSimple()->willReturn(true);
        $product->getVariants()->willReturn(new ArrayCollection());

        $this->expectException(\InvalidArgumentException::class);

        $this->factory(new WishlistItem())->createWithProduct($product->reveal());
    }

    private function factory(WishlistItemInterface $item): WishlistItemFactory
    {
        $decorated = $this->prophesize(FactoryInterface::class);
        $decorated->createNew()->willReturn($item);

        return new WishlistItemFactory($decorated->reveal());
    }
}
