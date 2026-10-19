<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Controller;

use Setono\SyliusWishlistPlugin\Form\Type\WishlistType;
use Setono\SyliusWishlistPlugin\Model\GuestWishlistInterface;
use Setono\SyliusWishlistPlugin\Model\WishlistInterface;
use Setono\SyliusWishlistPlugin\Repository\WishlistRepositoryInterface;
use Setono\SyliusWishlistPlugin\Security\Voter\WishlistVoter;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Twig\Environment;

final readonly class ShowWishlistAction
{
    /**
     * @param WishlistRepositoryInterface<WishlistInterface> $wishlistRepository
     */
    public function __construct(
        private WishlistRepositoryInterface $wishlistRepository,
        private FormFactoryInterface $formFactory,
        private AuthorizationCheckerInterface $authorizationChecker,
        private Environment $twig,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(Request $request, string $uuid): Response
    {
        $wishlist = $this->wishlistRepository->findOneByUuid($uuid);

        if (null === $wishlist) {
            throw new NotFoundHttpException(sprintf('Wishlist with id %s not found', $uuid));
        }

        // The guest logged in, so the items of this guest wishlist were moved onto a user wishlist. Only the owner of that
        // wishlist is redirected: it may hold items that were never on the (maybe shared) guest wishlist, and the
        // redirect would reveal its URL. See https://github.com/Setono/sylius-wishlist-plugin/issues/13
        if ($wishlist instanceof GuestWishlistInterface && null !== $mergedInto = $wishlist->getMergedInto()) {
            if (!$this->authorizationChecker->isGranted(WishlistVoter::EDIT, $mergedInto)) {
                throw new NotFoundHttpException(sprintf('Wishlist with id %s not found', $uuid));
            }

            return new RedirectResponse($this->urlGenerator->generate('setono_sylius_wishlist_shop_wishlist_show', [
                'uuid' => $mergedInto->getUuid(),
            ]));
        }

        // A wishlist may be viewed by anyone holding its (shareable) URL, but only its owner may edit it.
        // This is checked before handling the request, so a stranger's submission never touches the managed wishlist.
        // Throwing "not found" rather than "access denied" avoids disclosing which wishlists exist.
        if (!$request->isMethodSafe() && !$this->authorizationChecker->isGranted(WishlistVoter::EDIT, $wishlist)) {
            throw new NotFoundHttpException(sprintf('Wishlist with id %s not found', $uuid));
        }

        // PATCH (sent as POST with a _method override, like the rest of Sylius) leaves fields that aren't submitted
        // untouched, e.g. the name, whose input is only added to the page when the owner clicks it
        $form = $this->formFactory->create(WishlistType::class, $wishlist, ['method' => 'PATCH']);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->wishlistRepository->add($wishlist);

            $session = $request->getSession();
            if ($session instanceof FlashBagAwareSessionInterface) {
                $session->getFlashBag()->add('success', 'setono_sylius_wishlist.wishlist_updated');
            }

            // Redirect after a successful update so a browser refresh doesn't re-submit the form (POST/Redirect/GET).
            return new RedirectResponse($this->urlGenerator->generate('setono_sylius_wishlist_shop_wishlist_show', [
                'uuid' => $uuid,
            ]));
        }

        return new Response($this->twig->render('@SetonoSyliusWishlistPlugin/shop/wishlist/show.html.twig', [
            'wishlist' => $wishlist,
            'form' => $form->createView(),
        ]));
    }
}
