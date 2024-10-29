<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Repository;

use Setono\SyliusWishlistPlugin\Model\UserWishlistInterface;
use Sylius\Component\User\Model\UserInterface;

/**
 * @extends WishlistRepositoryInterface<UserWishlistInterface>
 */
interface UserWishlistRepositoryInterface extends WishlistRepositoryInterface
{
    /**
     * @return list<UserWishlistInterface>
     */
    public function findByUser(UserInterface $user): array;
}
