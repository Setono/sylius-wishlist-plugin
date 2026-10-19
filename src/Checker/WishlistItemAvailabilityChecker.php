<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Checker;

use Setono\SyliusWishlistPlugin\Model\WishlistItemInterface;
use Sylius\Component\Core\Model\ChannelInterface;

final class WishlistItemAvailabilityChecker implements WishlistItemAvailabilityCheckerInterface
{
    public function isAvailable(WishlistItemInterface $item, ChannelInterface $channel): bool
    {
        $product = $item->getProduct();
        if (null === $product || !$product->isEnabled() || !$product->hasChannel($channel)) {
            return false;
        }

        $variant = $item->getVariant();

        // Sylius can't price a variant without a price in the channel (adding it to the cart throws)
        return null === $variant || ($variant->isEnabled() && null !== $variant->getChannelPricingForChannel($channel)?->getPrice());
    }
}
