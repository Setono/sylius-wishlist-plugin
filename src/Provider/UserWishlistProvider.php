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
            return [];
        }

        return $this->userWishlistRepository->findByUser($user);
    }

    public function getPreSelectedWishlists(): array
    {
        // todo implement
        return $this->getWishlists();
    }
}
