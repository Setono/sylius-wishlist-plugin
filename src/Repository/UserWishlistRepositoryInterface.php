<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Repository;

use Setono\SyliusWishlistPlugin\Model\UserWishlistInterface;
use Sylius\Component\User\Model\UserInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;

/**
 * @extends RepositoryInterface<UserWishlistInterface>
 */
interface UserWishlistRepositoryInterface extends RepositoryInterface
{
    /**
     * @return list<UserWishlistInterface>
     */
    public function findByUser(UserInterface $user): array;
}
