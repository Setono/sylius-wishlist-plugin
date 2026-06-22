<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Controller;

use Setono\SyliusWishlistPlugin\Provider\WishlistProviderInterface;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

final readonly class WishlistIndexAction
{
    public function __construct(private Environment $twig, private WishlistProviderInterface $wishlistProvider)
    {
    }

    public function __invoke(): Response
    {
        return new Response($this->twig->render('@SetonoSyliusWishlistPlugin/shop/wishlist/index.html.twig', [
            'wishlists' => $this->wishlistProvider->getWishlists(),
        ]));
    }
}
