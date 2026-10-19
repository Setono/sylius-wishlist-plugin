<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Checker;

use Setono\SyliusWishlistPlugin\Model\WishlistItemInterface;
use Sylius\Component\Core\Model\ChannelInterface;

interface WishlistItemAvailabilityCheckerInterface
{
    /**
     * Whether the item's product can be bought in the given channel. Wishlist items can stay around long after
     * a product stopped being sold, so the product must still be enabled and in the channel, and the variant (if one
     * has been chosen) must still be enabled and have a price in the channel. Stock is not part of this
     */
    public function isAvailable(WishlistItemInterface $item, ChannelInterface $channel): bool;
}
