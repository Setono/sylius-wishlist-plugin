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
        $this->wishlists ??= $this->decorated->getWishlists();

        return $this->wishlists;
    }

    public function getPreSelectedWishlists(): array
    {
        $this->preSelectedWishlists ??= $this->decorated->getPreSelectedWishlists();

        return $this->preSelectedWishlists;
    }
}
