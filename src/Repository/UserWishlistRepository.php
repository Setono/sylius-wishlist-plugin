<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Repository;

use Setono\SyliusWishlistPlugin\Model\UserWishlistInterface;
use Sylius\Component\User\Model\UserInterface;
use Webmozart\Assert\Assert;

class UserWishlistRepository extends WishlistRepository implements UserWishlistRepositoryInterface
{
    public function findByUser(UserInterface $user): array
    {
        $objs = $this->findBy(['user' => $user]);
        Assert::allIsInstanceOf($objs, UserWishlistInterface::class);

        return $objs;
    }
}
