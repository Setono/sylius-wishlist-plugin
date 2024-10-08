<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Model;

use Sylius\Component\User\Model\UserInterface;

class UserWishlist extends Wishlist implements UserWishlistInterface
{
    protected ?UserInterface $user = null;

    public function getUser(): ?UserInterface
    {
        return $this->user;
    }

    public function setUser(?UserInterface $user): void
    {
        $this->user = $user;
    }
}
