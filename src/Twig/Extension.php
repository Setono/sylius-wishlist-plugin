<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class Extension extends AbstractExtension
{
    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('setono_sylius_wishlist_on_wishlist', [Runtime::class, 'onWishlist']),
            new TwigFunction('setono_sylius_wishlist_has_wishlist', [Runtime::class, 'hasWishlist']),
        ];
    }
}
