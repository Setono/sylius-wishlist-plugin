<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Factory;

use Setono\SyliusWishlistPlugin\Model\WishlistItemInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Resource\Factory\FactoryInterface;

/**
 * @extends FactoryInterface<WishlistItemInterface>
 */
interface WishlistItemFactoryInterface extends FactoryInterface
{
    public function createWithProduct(ProductInterface $product, int $quantity = 1): WishlistItemInterface;
}