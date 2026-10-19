<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Checker;

use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Memoizes the checks for the current request. It's reset between requests (kernel.reset), so a long-running
 * worker (FrankenPHP, RoadRunner, Swoole, ...) never answers for one visitor with another visitor's wishlists.
 */
final class CachedWishlistChecker implements WishlistCheckerInterface, ResetInterface
{
    /** @var array<string, bool> */
    private array $cache = [];

    public function __construct(private readonly WishlistCheckerInterface $decorated)
    {
    }

    public function onWishlist(ProductInterface|ProductVariantInterface $product): bool
    {
        $key = spl_object_hash($product);

        if (!array_key_exists($key, $this->cache)) {
            $this->cache[$key] = $this->decorated->onWishlist($product);
        }

        return $this->cache[$key];
    }

    public function reset(): void
    {
        $this->cache = [];
    }
}
