<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Checker;

use Setono\SyliusWishlistPlugin\Provider\WishlistProviderInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;

final readonly class WishlistChecker implements WishlistCheckerInterface
{
    public function __construct(private WishlistProviderInterface $wishlistProvider)
    {
    }

    public function onWishlist(ProductInterface|ProductVariantInterface $product): bool
    {
        foreach ($this->wishlistProvider->getWishlists() as $wishlist) {
            $hasProduct = $product instanceof ProductInterface ? $wishlist->hasProduct($product) : $wishlist->hasProductVariant($product);
            if ($hasProduct) {
                return true;
            }
        }

        return false;
    }
}
