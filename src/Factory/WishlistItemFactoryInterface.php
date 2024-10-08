<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Factory;

use Setono\SyliusWishlistPlugin\Model\WishlistItemInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Resource\Factory\FactoryInterface;

/**
 * @extends FactoryInterface<WishlistItemInterface>
 */
interface WishlistItemFactoryInterface extends FactoryInterface
{
    /**
     * @throws \InvalidArgumentException if the variant does not exist
     */
    public function createWithVariant(int|ProductVariantInterface $variant, int $quantity = 1): WishlistItemInterface;
}
