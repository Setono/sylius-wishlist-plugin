<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Controller;

use Doctrine\Persistence\ManagerRegistry;
use Setono\Doctrine\ORMTrait;
use Setono\SyliusWishlistPlugin\Factory\WishlistFactoryInterface;
use Setono\SyliusWishlistPlugin\Provider\WishlistProviderInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * This class is used to redirect the user to the first wishlist.
 * This is useful if your application only needs one wishlist per user.
 *
 * NOTICE that it will persist the wishlist if it doesn't exist.
 */
final class FirstWishlistRedirectAction
{
    use ORMTrait;

    public function __construct(
        private readonly WishlistProviderInterface $wishlistProvider,
        private readonly WishlistFactoryInterface $wishlistFactory,
        ManagerRegistry $managerRegistry,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
        $this->managerRegistry = $managerRegistry;
    }

    public function __invoke(): RedirectResponse
    {
        $wishlist = $this->wishlistProvider->getWishlists()[0];

        $this->getManager($wishlist)->persist($wishlist);
        $this->getManager($wishlist)->flush();

        return new RedirectResponse($this->urlGenerator->generate(
            'setono_sylius_wishlist_shop_wishlist_show',
            ['uuid' => $wishlist->getUuid()],
        ));
    }
}
