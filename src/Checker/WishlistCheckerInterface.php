<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Checker;

use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;

interface WishlistCheckerInterface
{
    /**
     * Returns true if the given product/variant is on _any_ wishlist
     */
    public function onWishlist(ProductInterface|ProductVariantInterface $product): bool;
}
