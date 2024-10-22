<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Controller;

use Doctrine\Persistence\ManagerRegistry;
use Setono\Doctrine\ORMTrait;
use Setono\SyliusWishlistPlugin\Controller\Command\SelectWishlistsCommand;
use Setono\SyliusWishlistPlugin\Factory\WishlistItemFactoryInterface;
use Setono\SyliusWishlistPlugin\Form\Type\SelectWishlistsType;
use Setono\SyliusWishlistPlugin\Provider\WishlistProviderInterface;
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
        private readonly WishlistProviderInterface $wishlistProvider,
        private readonly WishlistItemFactoryInterface $wishlistItemFactory,
        private readonly Environment $twig,
        private readonly FormFactoryInterface $formFactory,
        /** @var class-string<ProductInterface> $productClass */
        private readonly string $productClass,
    ) {
        $this->managerRegistry = $managerRegistry;
    }

    public function add(int $product): Response
    {
        $productEntity = $this->getManager($this->productClass)->find($this->productClass, $product);

        if (null === $productEntity) {
            throw new NotFoundHttpException(sprintf('Product with id %s not found', $product));
        }

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

        return new Response($this->twig->render('@SetonoSyliusWishlistPlugin/shop/wishlist/select_wishlists.html.twig', [
            'product' => $productEntity,
            'form' => $form->createView(),
        ]));
    }

    public function selectWishlists(Request $request, int $product): Response
    {
    }
}
