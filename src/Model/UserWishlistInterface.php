<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Model;

use Sylius\Component\User\Model\UserInterface;

interface UserWishlistInterface extends WishlistInterface
{
    public function getUser(): ?UserInterface;

    public function setUser(?UserInterface $user): void;
}
