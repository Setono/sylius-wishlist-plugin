<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Model;

use Sylius\Component\User\Model\UserInterface;

interface GuestWishlistInterface extends WishlistInterface
{
    public function getClientId(): ?string;

    public function setClientId(?string $clientId): void;

    public function convertToUserWishlist(UserInterface $user): void;
}
