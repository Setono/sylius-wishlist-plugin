<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Twig;

use Setono\SyliusWishlistPlugin\Checker\WishlistCheckerInterface;
use Setono\SyliusWishlistPlugin\Provider\WishlistProviderInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Twig\Extension\RuntimeExtensionInterface;

final class Runtime implements RuntimeExtensionInterface
{
    public function __construct(
        private readonly WishlistProviderInterface $wishlistProvider,
        private readonly WishlistCheckerInterface $wishlistChecker,
    ) {
    }

    public function onWishlist(ProductInterface|ProductVariantInterface $product): bool
    {
        return $this->wishlistChecker->onWishlist($product);
    }

    public function hasWishlist(): bool
    {
        return $this->wishlistProvider->getWishlists() !== [];
    }
}
