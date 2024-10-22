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
            try {
                return $service->getWishlists();
            } catch (\RuntimeException) {
                continue;
            }
        }

        throw new \RuntimeException('No wishlists found');
    }

    public function getPreSelectedWishlists(): array
    {
        // todo implement
        return $this->getWishlists();
    }
}
