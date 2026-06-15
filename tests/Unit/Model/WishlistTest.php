<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Tests\Unit\Model;

use PHPUnit\Framework\TestCase;
use Setono\SyliusWishlistPlugin\Model\UserWishlist;
use Setono\SyliusWishlistPlugin\Model\WishlistInterface;
use Setono\SyliusWishlistPlugin\Model\WishlistItem;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV7;

final class WishlistTest extends TestCase
{
    /** @test */
    public function it_generates_a_v7_uuid_on_construction(): void
    {
        $wishlist = $this->createWishlist();

        self::assertTrue(Uuid::isValid($wishlist->getUuid()));
        self::assertInstanceOf(UuidV7::class, Uuid::fromString($wishlist->getUuid()));
    }

    /** @test */
    public function it_generates_a_unique_uuid_per_instance(): void
    {
        self::assertNotSame($this->createWishlist()->getUuid(), $this->createWishlist()->getUuid());
    }

    /** @test */
    public function it_sets_and_gets_the_name(): void
    {
        $wishlist = $this->createWishlist();
        self::assertNull($wishlist->getName());

        $wishlist->setName('Christmas');
        self::assertSame('Christmas', $wishlist->getName());
    }

    /** @test */
    public function it_has_no_items_by_default(): void
    {
        $wishlist = $this->createWishlist();

        self::assertFalse($wishlist->hasItems());
        self::assertCount(0, $wishlist->getItems());
    }

    /** @test */
    public function it_adds_an_item_and_assigns_itself_to_it(): void
    {
        $wishlist = $this->createWishlist();
        $item = new WishlistItem();

        $wishlist->addItem($item);

        self::assertTrue($wishlist->hasItems());
        self::assertTrue($wishlist->hasItem($item));
        self::assertCount(1, $wishlist->getItems());
        self::assertSame($wishlist, $item->getWishlist());
    }

    /** @test */
    public function it_does_not_add_the_same_item_twice(): void
    {
        $wishlist = $this->createWishlist();
        $item = new WishlistItem();

        $wishlist->addItem($item);
        $wishlist->addItem($item);

        self::assertCount(1, $wishlist->getItems());
    }

    /** @test */
    public function it_removes_an_item_by_reference(): void
    {
        $wishlist = $this->createWishlist();
        $item = new WishlistItem();
        $wishlist->addItem($item);

        $wishlist->removeItem($item);

        self::assertFalse($wishlist->hasItem($item));
        self::assertCount(0, $wishlist->getItems());
        self::assertNull($item->getWishlist());
    }

    /** @test */
    public function it_removes_an_item_by_id(): void
    {
        $wishlist = $this->createWishlist();
        $kept = $this->itemWithId(1);
        $removed = $this->itemWithId(2);
        $wishlist->addItem($kept);
        $wishlist->addItem($removed);

        $wishlist->removeItem(2);

        self::assertTrue($wishlist->hasItem($kept));
        self::assertFalse($wishlist->hasItem($removed));
        self::assertNull($removed->getWishlist());
    }

    /** @test */
    public function it_does_not_remove_anything_when_no_item_matches_the_id(): void
    {
        $wishlist = $this->createWishlist();
        $wishlist->addItem($this->itemWithId(1));

        $wishlist->removeItem(999);

        self::assertCount(1, $wishlist->getItems());
    }

    /** @test */
    public function it_knows_whether_it_contains_a_product(): void
    {
        $wishlist = $this->createWishlist();
        $product = $this->createProduct(10);
        $item = new WishlistItem();
        $item->setProduct($product);
        $wishlist->addItem($item);

        self::assertTrue($wishlist->hasProduct($product));
        self::assertFalse($wishlist->hasProduct($this->createProduct(11)));
    }

    /** @test */
    public function it_removes_items_matching_a_product(): void
    {
        $wishlist = $this->createWishlist();
        $product = $this->createProduct(10);

        $item = new WishlistItem();
        $item->setProduct($product);
        $wishlist->addItem($item);

        $other = new WishlistItem();
        $other->setProduct($this->createProduct(11));
        $wishlist->addItem($other);

        $wishlist->removeProduct($product);

        self::assertFalse($wishlist->hasProduct($product));
        self::assertCount(1, $wishlist->getItems());
    }

    /** @test */
    public function it_knows_whether_it_contains_a_product_variant(): void
    {
        $wishlist = $this->createWishlist();
        $variant = $this->createVariant(20, 10);
        $item = new WishlistItem();
        $item->setVariant($variant);
        $wishlist->addItem($item);

        self::assertTrue($wishlist->hasProductVariant($variant));
        self::assertFalse($wishlist->hasProductVariant($this->createVariant(21, 10)));
    }

    /** @test */
    public function it_removes_items_matching_a_product_variant(): void
    {
        $wishlist = $this->createWishlist();
        $variant = $this->createVariant(20, 10);
        $item = new WishlistItem();
        $item->setVariant($variant);
        $wishlist->addItem($item);

        $wishlist->removeProductVariant($variant);

        self::assertFalse($wishlist->hasProductVariant($variant));
        self::assertCount(0, $wishlist->getItems());
    }

    /** @test */
    public function it_sums_the_quantity_of_all_items(): void
    {
        $wishlist = $this->createWishlist();

        $first = new WishlistItem();
        $first->setQuantity(2);
        $wishlist->addItem($first);

        $second = new WishlistItem();
        $second->setQuantity(3);
        $wishlist->addItem($second);

        self::assertSame(5, $wishlist->getQuantity());
    }

    private function createWishlist(): WishlistInterface
    {
        return new UserWishlist();
    }

    private function itemWithId(int $id): WishlistItem
    {
        $item = new WishlistItem();

        $property = new \ReflectionProperty(WishlistItem::class, 'id');
        $property->setValue($item, $id);

        return $item;
    }

    private function createProduct(int $id): ProductInterface
    {
        $product = $this->createMock(ProductInterface::class);
        $product->method('getId')->willReturn($id);

        return $product;
    }

    private function createVariant(int $variantId, int $productId): ProductVariantInterface
    {
        $variant = $this->createMock(ProductVariantInterface::class);
        $variant->method('getId')->willReturn($variantId);
        $variant->method('getProduct')->willReturn($this->createProduct($productId));

        return $variant;
    }
}
