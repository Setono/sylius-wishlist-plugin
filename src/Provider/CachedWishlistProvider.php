<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Provider;

use Setono\SyliusWishlistPlugin\Model\WishlistInterface;

final class CachedWishlistProvider implements WishlistProviderInterface
{
    /** @var list<WishlistInterface>|null */
    private ?array $wishlists = null;

    /** @var list<WishlistInterface>|null */
    private ?array $preSelectedWishlists = null;

    public function __construct(private readonly WishlistProviderInterface $decorated)
    {
    }

    public function getWishlists(): array
    {
        if (null === $this->wishlists) {
            $this->wishlists = $this->decorated->getWishlists();
        }

        return $this->wishlists;
    }

    public function getPreSelectedWishlists(): array
    {
        if (null === $this->preSelectedWishlists) {
            $this->preSelectedWishlists = $this->decorated->getWishlists();
        }

        return $this->preSelectedWishlists;
    }
}
