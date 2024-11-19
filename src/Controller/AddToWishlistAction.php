<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Controller;

use Doctrine\Persistence\ManagerRegistry;
use Setono\Doctrine\ORMTrait;
use Setono\SyliusWishlistPlugin\Factory\WishlistItemFactoryInterface;
use Setono\SyliusWishlistPlugin\Provider\WishlistProviderInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Environment;

/**
 * This controller action is responsible for adding a product or product variant to the wishlist
 */
final class AddToWishlistAction
{
    use ORMTrait;

    public function __construct(
        private readonly WishlistProviderInterface $wishlistProvider,
        private readonly WishlistItemFactoryInterface $wishlistItemFactory,
        private readonly Environment $twig,
        ManagerRegistry $managerRegistry,
        /** @var class-string<ProductInterface|ProductVariantInterface> $className */
        private readonly string $className,
    ) {
        $this->managerRegistry = $managerRegistry;
    }

    public function __invoke(int $id): Response
    {
        $entity = $this->getManager($this->className)->find($this->className, $id);

        if (null === $entity) {
            throw new NotFoundHttpException(sprintf('Product with id %s not found', $id));
        }

        $wishlistItem = $entity instanceof ProductInterface ? $this->wishlistItemFactory->createWithProduct($entity) : $this->wishlistItemFactory->createWithProductVariant($entity);

        $preSelectedWishlists = $this->wishlistProvider->getPreSelectedWishlists();
        foreach ($preSelectedWishlists as $wishlist) {
            $manager = $this->getManager($wishlist);
            $manager->persist($wishlist);

            $wishlist->addItem($wishlistItem);
        }

        $manager->flush();

        return new JsonResponse([
            'toggleButton' => $this->twig->render('@SetonoSyliusWishlistPlugin/shop/wishlist/_toggle_button.html.twig', [
                'product' => $entity instanceof ProductInterface ? $entity : $entity->getProduct(),
                'productVariant' => $entity instanceof ProductVariantInterface ? $entity : null,
            ]),
// Will be added in a later version
//             'selectWishlistsForm' => $this->twig->render('@SetonoSyliusWishlistPlugin/shop/wishlist/_select_wishlists.html.twig', [
//                'product' => $entity instanceof ProductInterface ? $entity : $entity->getProduct(),
//                'productVariant' => $entity instanceof ProductVariantInterface ? $entity : null,
//                'form' => $form->createView(),
//            ]),
        ]);
    }
}
