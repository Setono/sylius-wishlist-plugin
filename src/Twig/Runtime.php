<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Twig;

use Setono\SyliusWishlistPlugin\Provider\WishlistProviderInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Twig\Extension\RuntimeExtensionInterface;

final class Runtime implements RuntimeExtensionInterface
{
    public function __construct(private readonly WishlistProviderInterface $wishlistProvider)
    {
    }

    public function onWishlist(ProductInterface $product): bool
    {
        // todo should be optimized
        foreach ($this->wishlistProvider->getWishlists() as $wishlist) {
            if ($wishlist->hasProduct($product)) {
                return true;
            }
        }

        return false;
    }
}
