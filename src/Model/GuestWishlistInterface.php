<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Model;

interface GuestWishlistInterface extends WishlistInterface
{
    public function getClientId(): ?string;

    public function setClientId(?string $clientId): void;
}
