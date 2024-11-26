<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Provider;

use Setono\SyliusWishlistPlugin\Model\WishlistInterface;

interface WishlistProviderInterface
{
    /**
     * @return list<WishlistInterface>
     */
    public function getWishlists(): array;

    /**
     * @return list<WishlistInterface>
     */
    public function getPreSelectedWishlists(): array;
}
