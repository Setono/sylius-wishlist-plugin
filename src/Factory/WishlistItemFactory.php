<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Factory;

use Setono\SyliusWishlistPlugin\Model\WishlistItemInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Core\Repository\ProductVariantRepositoryInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Webmozart\Assert\Assert;

final class WishlistItemFactory implements WishlistItemFactoryInterface
{
    public function __construct(
        private readonly FactoryInterface $decorated,
        private readonly ProductVariantRepositoryInterface $productVariantRepository,
    ) {
    }

    public function createNew(): WishlistItemInterface
    {
        $obj = $this->decorated->createNew();
        Assert::isInstanceOf($obj, WishlistItemInterface::class);

        return $obj;
    }

    public function createWithVariant(int|ProductVariantInterface $variant, int $quantity = 1): WishlistItemInterface
    {
        if (is_int($variant)) {
            $variant = $this->productVariantRepository->find($variant);
        }

        Assert::isInstanceOf($variant, ProductVariantInterface::class);

        $wishlistItem = $this->createNew();
        $wishlistItem->setProductVariant($variant);
        $wishlistItem->setQuantity($quantity);

        return $wishlistItem;
    }
}
