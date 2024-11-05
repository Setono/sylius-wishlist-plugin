<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Twig;

use Setono\SyliusWishlistPlugin\Provider\WishlistProviderInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Twig\Extension\RuntimeExtensionInterface;

final class Runtime implements RuntimeExtensionInterface
{
    public function __construct(private readonly WishlistProviderInterface $wishlistProvider)
    {
    }

    public function onWishlist(ProductInterface|ProductVariantInterface $product): bool
    {
        // todo should be optimized
        foreach ($this->wishlistProvider->getWishlists() as $wishlist) {
            $hasProduct = $product instanceof ProductInterface ? $wishlist->hasProduct($product) : $wishlist->hasProductVariant($product);
            if ($hasProduct) {
                return true;
            }
        }

        return false;
    }
}
