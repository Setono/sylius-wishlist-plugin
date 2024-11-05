<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Controller;

use Doctrine\Persistence\ManagerRegistry;
use Setono\Doctrine\ORMTrait;
use Setono\SyliusWishlistPlugin\Form\Type\WishlistType;
use Setono\SyliusWishlistPlugin\Model\WishlistInterface;
use Setono\SyliusWishlistPlugin\Provider\WishlistProviderInterface;
use Setono\SyliusWishlistPlugin\Repository\WishlistRepositoryInterface;
use Sylius\Component\Core\Factory\CartItemFactoryInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Environment;

final class WishlistController
{
    use ORMTrait;

    public function __construct(
        ManagerRegistry $managerRegistry,
        private readonly Environment $twig,
        private readonly CartItemFactoryInterface $cartItemFactory,
        /** @var class-string<ProductInterface> $productClass */
        private readonly string $productClass,
        /** @var class-string<WishlistInterface> $wishlistClass */
        private readonly string $wishlistClass,
    ) {
        $this->managerRegistry = $managerRegistry;
    }

    public function index(WishlistProviderInterface $wishlistProvider): Response
    {
        return new Response($this->twig->render('@SetonoSyliusWishlistPlugin/shop/wishlist/index.html.twig', [
            'wishlists' => $wishlistProvider->getWishlists(),
        ]));
    }

    public function show(
        Request $request,
        WishlistRepositoryInterface $wishlistRepository,
        FormFactoryInterface $formFactory,
        string $uuid,
    ): Response {
        $wishlist = $wishlistRepository->findOneByUuid($uuid);

        if (null === $wishlist) {
            throw new NotFoundHttpException(sprintf('Wishlist with id %s not found', $uuid));
        }

        $form = $formFactory->create(WishlistType::class, $wishlist);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $wishlistRepository->add($wishlist);
        }

        return new Response($this->twig->render('@SetonoSyliusWishlistPlugin/shop/wishlist/show.html.twig', [
            'wishlist' => $wishlist,
            'form' => $form->createView(),
        ]));
    }

    public function selectWishlists(Request $request, int $product): Response
    {
        return new Response('Not implemented', Response::HTTP_NOT_IMPLEMENTED);
    }
}
