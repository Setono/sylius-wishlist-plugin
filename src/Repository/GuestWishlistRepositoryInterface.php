<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Repository;

use Setono\SyliusWishlistPlugin\Model\GuestWishlistInterface;

/**
 * @extends WishlistRepositoryInterface<GuestWishlistInterface>
 */
interface GuestWishlistRepositoryInterface extends WishlistRepositoryInterface
{
    public function findOneByClientId(string $clientId): ?GuestWishlistInterface;
}
