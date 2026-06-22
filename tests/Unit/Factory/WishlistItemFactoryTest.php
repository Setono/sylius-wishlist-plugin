<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Tests\Unit\Factory;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Setono\SyliusWishlistPlugin\Factory\WishlistItemFactory;
use Setono\SyliusWishlistPlugin\Model\WishlistItem;
use Setono\SyliusWishlistPlugin\Model\WishlistItemInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Resource\Factory\FactoryInterface;

final class WishlistItemFactoryTest extends TestCase
{
    #[Test]
    public function it_creates_a_new_wishlist_item_via_the_decorated_factory(): void
    {
        $item = new WishlistItem();

        self::assertSame($item, $this->factory($item)->createNew());
    }

    #[Test]
    public function it_creates_an_item_for_a_configurable_product(): void
    {
        $product = $this->createMock(ProductInterface::class);
        $product->method('isSimple')->willReturn(false);

        $item = $this->factory(new WishlistItem())->createWithProduct($product, 3);

        self::assertSame($product, $item->getProduct());
        self::assertNull($item->getVariant());
        self::assertSame(3, $item->getQuantity());
    }

    #[Test]
    public function it_creates_an_item_for_a_simple_product_using_its_first_variant(): void
    {
        $product = $this->createMock(ProductInterface::class);
        $variant = $this->createMock(ProductVariantInterface::class);
        $variant->method('getProduct')->willReturn($product);
        $product->method('isSimple')->willReturn(true);
        $product->method('getVariants')->willReturn(new ArrayCollection([$variant]));

        $item = $this->factory(new WishlistItem())->createWithProduct($product);

        self::assertSame($variant, $item->getVariant());
        self::assertSame(1, $item->getQuantity());
    }

    #[Test]
    public function it_creates_an_item_for_a_product_variant(): void
    {
        $product = $this->createMock(ProductInterface::class);
        $variant = $this->createMock(ProductVariantInterface::class);
        $variant->method('getProduct')->willReturn($product);

        $item = $this->factory(new WishlistItem())->createWithProductVariant($variant, 4);

        self::assertSame($variant, $item->getVariant());
        self::assertSame(4, $item->getQuantity());
    }

    private function factory(WishlistItemInterface $item): WishlistItemFactory
    {
        /** @var MockObject&FactoryInterface<WishlistItemInterface> $decorated */
        $decorated = $this->createMock(FactoryInterface::class);
        $decorated->method('createNew')->willReturn($item);

        return new WishlistItemFactory($decorated);
    }
}
