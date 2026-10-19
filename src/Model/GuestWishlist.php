<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Model;

class GuestWishlist extends Wishlist implements GuestWishlistInterface
{
    protected ?string $clientId = null;

    protected ?UserWishlistInterface $mergedInto = null;

    public function getClientId(): ?string
    {
        return $this->clientId;
    }

    public function setClientId(?string $clientId): void
    {
        $this->clientId = $clientId;
    }

    public function getMergedInto(): ?UserWishlistInterface
    {
        return $this->mergedInto;
    }

    public function setMergedInto(?UserWishlistInterface $mergedInto): void
    {
        $this->mergedInto = $mergedInto;
    }
}
