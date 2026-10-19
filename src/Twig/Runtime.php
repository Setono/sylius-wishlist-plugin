<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Twig;

use Setono\SyliusWishlistPlugin\Checker\WishlistCheckerInterface;
use Setono\SyliusWishlistPlugin\Checker\WishlistItemAvailabilityCheckerInterface;
use Setono\SyliusWishlistPlugin\Model\WishlistItemInterface;
use Setono\SyliusWishlistPlugin\Provider\WishlistProviderInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Twig\Extension\RuntimeExtensionInterface;
use Webmozart\Assert\Assert;

final readonly class Runtime implements RuntimeExtensionInterface
{
    public function __construct(
        private WishlistProviderInterface $wishlistProvider,
        private WishlistCheckerInterface $wishlistChecker,
        private WishlistItemAvailabilityCheckerInterface $wishlistItemAvailabilityChecker,
        private ChannelContextInterface $channelContext,
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

    public function isAvailable(WishlistItemInterface $item): bool
    {
        $channel = $this->channelContext->getChannel();
        Assert::isInstanceOf($channel, ChannelInterface::class);

        return $this->wishlistItemAvailabilityChecker->isAvailable($item, $channel);
    }
}
