<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Controller;

use Doctrine\Persistence\ManagerRegistry;
use Setono\Doctrine\ORMTrait;
use Setono\SyliusWishlistPlugin\Controller\Command\SelectWishlistsCommand;
use Setono\SyliusWishlistPlugin\Factory\WishlistItemFactoryInterface;
use Setono\SyliusWishlistPlugin\Form\Type\SelectWishlistsType;
use Setono\SyliusWishlistPlugin\Model\WishlistInterface;
use Setono\SyliusWishlistPlugin\Provider\WishlistProviderInterface;
use Setono\SyliusWishlistPlugin\Repository\WishlistRepositoryInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
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

    public function show(WishlistRepositoryInterface $wishlistRepository, string $uuid): Response
    {
        $wishlist = $wishlistRepository->findOneByUuid($uuid);

        if (null === $wishlist) {
            throw new NotFoundHttpException(sprintf('Wishlist with id %s not found', $uuid));
        }

        return new Response($this->twig->render('@SetonoSyliusWishlistPlugin/shop/wishlist/show.html.twig', [
            'wishlist' => $wishlist,
        ]));
    }

    public function add(
        WishlistProviderInterface $wishlistProvider,
        WishlistItemFactoryInterface $wishlistItemFactory,
        FormFactoryInterface $formFactory,
        int $product,
    ): JsonResponse {
        $productEntity = $this->getProduct($product);

        $wishlistItem = $wishlistItemFactory->createWithProduct($productEntity);

        $preSelectedWishlists = $wishlistProvider->getPreSelectedWishlists();
        foreach ($preSelectedWishlists as $wishlist) {
            $manager = $this->getManager($wishlist);
            $manager->persist($wishlist);

            $wishlist->addItem($wishlistItem);
        }

        $manager->flush();

        $form = $formFactory->create(SelectWishlistsType::class, new SelectWishlistsCommand($wishlistProvider->getWishlists()), [
            'selected' => $preSelectedWishlists,
        ]);

        return new JsonResponse([
            'toggleButton' => $this->twig->render('@SetonoSyliusWishlistPlugin/shop/wishlist/_toggle_button.html.twig', [
                'product' => $productEntity,
            ]),
            'selectWishlistsForm' => $this->twig->render('@SetonoSyliusWishlistPlugin/shop/wishlist/_select_wishlists.html.twig', [
                'product' => $productEntity,
                'form' => $form->createView(),
            ]),
        ]);
    }

    public function remove(
        Request $request,
        WishlistProviderInterface $wishlistProvider,
        int $product,
        int $wishlist = null,
    ): Response {
        $productEntity = $this->getProduct($product);

        foreach ($wishlistProvider->getWishlists() as $wishlistEntity) {
            if (null !== $wishlist && $wishlistEntity->getId() !== $wishlist) {
                continue;
            }

            $wishlistEntity->removeProduct($productEntity);
        }

        $this->getManager($wishlistEntity)->flush();

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'toggleButton' => $this->twig->render('@SetonoSyliusWishlistPlugin/shop/wishlist/_toggle_button.html.twig', [
                    'product' => $productEntity,
                ]),
            ]);
        }

        return new RedirectResponse($request->headers->get('referer'));
    }

    public function selectWishlists(Request $request, int $product): Response
    {
        return new Response('Not implemented', Response::HTTP_NOT_IMPLEMENTED);
    }

    private function getProduct(int $id): ProductInterface
    {
        $product = $this->getManager($this->productClass)->find($this->productClass, $id);

        if (null === $product) {
            throw new NotFoundHttpException(sprintf('Product with id %s not found', $id));
        }

        return $product;
    }
}
