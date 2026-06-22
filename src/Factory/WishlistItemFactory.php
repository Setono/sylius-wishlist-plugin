<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Factory;

use Setono\SyliusWishlistPlugin\Model\WishlistItemInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Webmozart\Assert\Assert;

final readonly class WishlistItemFactory implements WishlistItemFactoryInterface
{
    /**
     * @param FactoryInterface<WishlistItemInterface> $decorated
     */
    public function __construct(private FactoryInterface $decorated)
    {
    }

    public function createNew(): WishlistItemInterface
    {
        return $this->decorated->createNew();
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
