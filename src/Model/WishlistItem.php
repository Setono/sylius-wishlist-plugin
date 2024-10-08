<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Model;

use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;

class WishlistItem implements WishlistItemInterface
{
    protected ?int $id = null;

    protected ?WishlistInterface $wishlist = null;

    protected ?ProductVariantInterface $productVariant = null;

    protected int $quantity = 1;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getWishlist(): ?WishlistInterface
    {
        return $this->wishlist;
    }

    public function setWishlist(?WishlistInterface $wishlist): void
    {
        $this->wishlist = $wishlist;
    }

    public function getProductVariant(): ?ProductVariantInterface
    {
        return $this->productVariant;
    }

    public function setProductVariant(?ProductVariantInterface $productVariant): void
    {
        $this->productVariant = $productVariant;
    }

    public function getProduct(): ?ProductInterface
    {
        $product = $this->productVariant?->getProduct();

        if ($product instanceof ProductInterface) {
            return $product;
        }

        return null;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): void
    {
        $this->quantity = $quantity;
    }
}
