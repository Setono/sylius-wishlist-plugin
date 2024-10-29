<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Repository;

use Setono\SyliusWishlistPlugin\Model\WishlistInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;

/**
 * @template T of WishlistInterface
 * @extends RepositoryInterface<T>
 */
interface WishlistRepositoryInterface extends RepositoryInterface
{
    /**
     * @return T|null
     */
    public function findOneByUuid(string $uuid): ?WishlistInterface;
}
