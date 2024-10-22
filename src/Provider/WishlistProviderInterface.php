<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Provider;

use Setono\SyliusWishlistPlugin\Model\WishlistInterface;

interface WishlistProviderInterface
{
    /**
     * @return non-empty-list<WishlistInterface>
     *
     * @throws \RuntimeException if no wishlists are found
     */
    public function getWishlists(): array;

    /**
     * @return non-empty-list<WishlistInterface>
     *
     * @throws \RuntimeException if no wishlists are found
     */
    public function getPreSelectedWishlists(): array;
}
