<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Controller;

use Setono\SyliusWishlistPlugin\Form\Type\WishlistType;
use Setono\SyliusWishlistPlugin\Model\WishlistInterface;
use Setono\SyliusWishlistPlugin\Repository\WishlistRepositoryInterface;
use Setono\SyliusWishlistPlugin\Security\Voter\WishlistVoter;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Twig\Environment;

final class ShowWishlistAction
{
    /**
     * @param WishlistRepositoryInterface<WishlistInterface> $wishlistRepository
     */
    public function __construct(
        private readonly WishlistRepositoryInterface $wishlistRepository,
        private readonly FormFactoryInterface $formFactory,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
        private readonly Environment $twig,
    ) {
    }

    public function __invoke(Request $request, string $uuid): Response
    {
        $wishlist = $this->wishlistRepository->findOneByUuid($uuid);

        if (null === $wishlist) {
            throw new NotFoundHttpException(sprintf('Wishlist with id %s not found', $uuid));
        }

        $form = $this->formFactory->create(WishlistType::class, $wishlist);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            // A wishlist may be viewed by anyone holding its (shareable) URL, but only its owner may edit it.
            // Throwing "not found" rather than "access denied" avoids disclosing which wishlists exist.
            if (!$this->authorizationChecker->isGranted(WishlistVoter::EDIT, $wishlist)) {
                throw new NotFoundHttpException(sprintf('Wishlist with id %s not found', $uuid));
            }

            $this->wishlistRepository->add($wishlist);
        }

        return new Response($this->twig->render('@SetonoSyliusWishlistPlugin/shop/wishlist/show.html.twig', [
            'wishlist' => $wishlist,
            'form' => $form->createView(),
        ]));
    }
}
