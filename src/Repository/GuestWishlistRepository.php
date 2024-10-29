<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Repository;

use Setono\SyliusWishlistPlugin\Model\GuestWishlistInterface;
use Webmozart\Assert\Assert;

class GuestWishlistRepository extends WishlistRepository implements GuestWishlistRepositoryInterface
{
    public function findOneByClientId(string $clientId): ?GuestWishlistInterface
    {
        $obj = $this->findOneBy(['clientId' => $clientId]);
        Assert::nullOrIsInstanceOf($obj, GuestWishlistInterface::class);

        return $obj;
    }
}
