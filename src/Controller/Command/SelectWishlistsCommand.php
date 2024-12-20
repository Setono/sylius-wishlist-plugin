<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Controller\Command;

use Setono\SyliusWishlistPlugin\Model\WishlistInterface;

final class SelectWishlistsCommand
{
    public function __construct(
        /** @var list<WishlistInterface> $wishlists */
        public array $wishlists = [],
    ) {
    }
}
