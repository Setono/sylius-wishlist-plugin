<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Factory;

use Setono\SyliusWishlistPlugin\Model\GuestWishlistInterface;
use Setono\SyliusWishlistPlugin\Model\UserWishlistInterface;
use Setono\SyliusWishlistPlugin\Model\WishlistInterface;
use Sylius\Component\User\Model\UserInterface;
use Sylius\Resource\Factory\FactoryInterface;

/**
 * @extends FactoryInterface<WishlistInterface>
 */
interface WishlistFactoryInterface extends FactoryInterface
{
    public function createForGuest(string $clientId): GuestWishlistInterface;

    public function createForUser(UserInterface $user): UserWishlistInterface;
}
