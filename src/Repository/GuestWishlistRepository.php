<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Repository;

use Setono\SyliusWishlistPlugin\Model\GuestWishlistInterface;
use Webmozart\Assert\Assert;

class GuestWishlistRepository extends WishlistRepository implements GuestWishlistRepositoryInterface
{
    public function findOneByClientId(string $clientId): ?GuestWishlistInterface
    {
        // Before 2.0, logging in assigned the user to the guest wishlist. Such a wishlist belongs to that user
        // now, so it must not be handed to whoever uses the same browser next
        $obj = $this->findOneBy(['clientId' => $clientId, 'user' => null]);
        Assert::nullOrIsInstanceOf($obj, GuestWishlistInterface::class);

        return $obj;
    }
}
