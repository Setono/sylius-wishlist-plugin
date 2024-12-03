<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Provider;

use Setono\CompositeCompilerPass\CompositeService;

/**
 * @extends CompositeService<WishlistProviderInterface>
 */
final class CompositeWishlistProvider extends CompositeService implements WishlistProviderInterface
{
    public function getWishlists(): array
    {
        foreach ($this->services as $service) {
            $wishlists = $service->getWishlists();
            if ([] !== $wishlists) {
                return $wishlists;
            }
        }

        return [];
    }

    public function getPreSelectedWishlists(): array
    {
        foreach ($this->services as $service) {
            $wishlists = $service->getPreSelectedWishlists();
            if ([] !== $wishlists) {
                return $wishlists;
            }
        }

        return [];
    }
}
