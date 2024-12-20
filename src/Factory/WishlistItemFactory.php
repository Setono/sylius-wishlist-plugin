<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Factory;

use Setono\SyliusWishlistPlugin\Model\WishlistItemInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Webmozart\Assert\Assert;

final class WishlistItemFactory implements WishlistItemFactoryInterface
{
    public function __construct(private readonly FactoryInterface $decorated)
    {
    }

    public function createNew(): WishlistItemInterface
    {
        $obj = $this->decorated->createNew();
        Assert::isInstanceOf($obj, WishlistItemInterface::class);

        return $obj;
    }

    public function createWithProduct(ProductInterface $product, int $quantity = 1): WishlistItemInterface
    {
        $wishlistItem = $this->createNew();
        $wishlistItem->setProduct($product);
        $wishlistItem->setQuantity($quantity);

        if ($product->isSimple()) {
            $variant = $product->getVariants()->first();
            Assert::isInstanceOf($variant, ProductVariantInterface::class);
            $wishlistItem->setVariant($variant);
        }

        return $wishlistItem;
    }

    public function createWithProductVariant(ProductVariantInterface $productVariant, int $quantity = 1): WishlistItemInterface
    {
        $wishlistItem = $this->createNew();
        $wishlistItem->setVariant($productVariant);
        $wishlistItem->setQuantity($quantity);

        return $wishlistItem;
    }
}
