<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Provider;

use Setono\SyliusWishlistPlugin\Repository\UserWishlistRepositoryInterface;
use Sylius\Component\User\Model\UserInterface;
use Symfony\Bundle\SecurityBundle\Security;

final class UserWishlistProvider implements WishlistProviderInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly UserWishlistRepositoryInterface $userWishlistRepository,
    ) {
    }

    public function getWishlists(): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof UserInterface) {
            throw new \RuntimeException('No wishlists found');
        }

        $wishlists = $this->userWishlistRepository->findByUser($user);

        if ([] === $wishlists) {
            throw new \RuntimeException('No wishlists found');
        }

        return $wishlists;
    }
}
