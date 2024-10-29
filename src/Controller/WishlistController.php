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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Environment;

final class WishlistController
{
    use ORMTrait;

    public function __construct(
        ManagerRegistry $managerRegistry,
        private readonly WishlistProviderInterface $wishlistProvider,
        private readonly WishlistItemFactoryInterface $wishlistItemFactory,
        private readonly Environment $twig,
        private readonly FormFactoryInterface $formFactory,
        /** @var class-string<ProductInterface> $productClass */
        private readonly string $productClass,
        /** @var class-string<WishlistInterface> $wishlistClass */
        private readonly string $wishlistClass,
    ) {
        $this->managerRegistry = $managerRegistry;
    }

    public function index(): Response
    {
        return new Response($this->twig->render('@SetonoSyliusWishlistPlugin/shop/wishlist/index.html.twig', [
            'wishlists' => $this->wishlistProvider->getWishlists(),
        ]));
    }

    public function show(string $uuid): Response
    {
        $wishlist = $this->getRepository($this->wishlistClass, WishlistRepositoryInterface::class)->findOneByUuid($uuid);

        if (null === $wishlist) {
            throw new NotFoundHttpException(sprintf('Wishlist with id %s not found', $uuid));
        }

        return new Response($this->twig->render('@SetonoSyliusWishlistPlugin/shop/wishlist/show.html.twig', [
            'wishlist' => $wishlist,
        ]));
    }

    public function add(int $product): JsonResponse
    {
        $productEntity = $this->getProduct($product);

        $wishlistItem = $this->wishlistItemFactory->createWithProduct($productEntity);

        $preSelectedWishlists = $this->wishlistProvider->getPreSelectedWishlists();
        foreach ($preSelectedWishlists as $wishlist) {
            $manager = $this->getManager($wishlist);
            $manager->persist($wishlist);

            $wishlist->addItem($wishlistItem);
        }

        $manager->flush();

        $form = $this->formFactory->create(SelectWishlistsType::class, new SelectWishlistsCommand($this->wishlistProvider->getWishlists()), [
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

    public function remove(int $product, int $wishlist = null): JsonResponse
    {
        $productEntity = $this->getProduct($product);

        foreach ($this->wishlistProvider->getWishlists() as $wishlistEntity) {
            if (null !== $wishlist && $wishlistEntity->getId() !== $wishlist) {
                continue;
            }

            $wishlistEntity->removeProduct($productEntity);
        }

        $this->getManager($wishlistEntity)->flush();

        return new JsonResponse([
            'toggleButton' => $this->twig->render('@SetonoSyliusWishlistPlugin/shop/wishlist/_toggle_button.html.twig', [
                'product' => $productEntity,
            ]),
        ]);
    }

    public function selectWishlists(Request $request, int $product): Response
    {
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
