<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Model;

use Sylius\Component\User\Model\UserInterface;

class GuestWishlist extends Wishlist implements GuestWishlistInterface
{
    protected ?string $clientId = null;

    public function getClientId(): ?string
    {
        return $this->clientId;
    }

    public function setClientId(?string $clientId): void
    {
        $this->clientId = $clientId;
    }

    public function convertToUserWishlist(UserInterface $user): void
    {
        $this->user = $user;
    }
}
