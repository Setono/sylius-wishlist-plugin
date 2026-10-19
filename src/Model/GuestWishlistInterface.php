<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Model;

interface GuestWishlistInterface extends WishlistInterface
{
    public function getClientId(): ?string;

    public function setClientId(?string $clientId): void;

    /**
     * The user wishlist this guest wishlist's items were copied onto when the guest logged in. Such a guest wishlist
     * is an empty placeholder that only exists so its (possibly bookmarked) URL can lead its owner to the user wishlist
     */
    public function getMergedInto(): ?UserWishlistInterface;

    public function setMergedInto(?UserWishlistInterface $mergedInto): void;
}
