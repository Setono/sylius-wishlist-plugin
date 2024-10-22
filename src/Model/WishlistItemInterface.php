<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Model;

use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Resource\Model\ResourceInterface;

interface WishlistItemInterface extends ResourceInterface
{
    public function getWishlist(): ?WishlistInterface;

    public function setWishlist(?WishlistInterface $wishlist): void;

    public function getProduct(): ?ProductInterface;

    public function setProduct(?ProductInterface $product): void;

    public function getQuantity(): int;

    public function setQuantity(int $quantity): void;
}
