<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Provider;

use Setono\SyliusWishlistPlugin\Model\WishlistInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Memoizes the wishlists for the current request. It's reset between requests (kernel.reset), so a long-running
 * worker (FrankenPHP, RoadRunner, Swoole, ...) never serves one visitor's wishlists to the next.
 */
final class CachedWishlistProvider implements WishlistProviderInterface, ResetInterface
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

    public function reset(): void
    {
        $this->wishlists = null;
        $this->preSelectedWishlists = null;
    }
}
