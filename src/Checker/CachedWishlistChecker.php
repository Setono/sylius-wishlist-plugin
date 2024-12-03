<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Checker;

use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;

final class CachedWishlistChecker implements WishlistCheckerInterface
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
}
