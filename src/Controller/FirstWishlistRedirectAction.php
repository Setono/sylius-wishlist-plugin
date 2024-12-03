<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Controller;

use Setono\SyliusWishlistPlugin\Provider\WishlistProviderInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * This class is used to redirect the user to the first wishlist.
 * This is useful if your application only needs one wishlist per user.
 */
final class FirstWishlistRedirectAction
{
    public function __construct(
        private readonly WishlistProviderInterface $wishlistProvider,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(Request $request): RedirectResponse
    {
        $wishlists = $this->wishlistProvider->getWishlists();
        if ([] === $wishlists) {
            $session = $request->getSession();
            if ($session instanceof FlashBagAwareSessionInterface) {
                $session->getFlashBag()->add('info', 'setono_sylius_wishlist.no_wishlists');
            }

            return $this->redirect($request);
        }

        return new RedirectResponse($this->urlGenerator->generate(
            'setono_sylius_wishlist_shop_wishlist_show',
            ['uuid' => $wishlists[0]->getUuid()],
        ));
    }

    private function redirect(Request $request): RedirectResponse
    {
        $referrer = $request->headers->get('referer');
        if (is_string($referrer) && '' !== $referrer) {
            return new RedirectResponse($referrer);
        }

        return new RedirectResponse($this->urlGenerator->generate('sylius_shop_homepage'));
    }
}
