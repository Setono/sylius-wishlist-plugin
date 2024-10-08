<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Repository;

use Setono\SyliusWishlistPlugin\Model\GuestWishlistInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;

/**
 * @extends RepositoryInterface<GuestWishlistInterface>
 */
interface GuestWishlistRepositoryInterface extends RepositoryInterface
{
    public function findOneByClientId(string $clientId): ?GuestWishlistInterface;
}
