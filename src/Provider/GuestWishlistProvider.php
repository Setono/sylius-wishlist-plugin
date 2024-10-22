<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Provider;

use Setono\ClientBundle\Context\ClientContextInterface;
use Setono\SyliusWishlistPlugin\Repository\GuestWishlistRepositoryInterface;

final class GuestWishlistProvider implements WishlistProviderInterface
{
    public function __construct(
        private readonly ClientContextInterface $clientContext,
        private readonly GuestWishlistRepositoryInterface $guestWishlistRepository,
    ) {
    }

    public function getWishlists(): array
    {
        $wishlist = $this->guestWishlistRepository->findOneByClientId($this->clientContext->getClient()->id);

        if (null === $wishlist) {
            throw new \RuntimeException('No wishlists found');
        }

        return [$wishlist];
    }

    public function getPreviouslyAddedToWishlists(): array
    {
        // todo implement
        return $this->getWishlists();
    }
}
