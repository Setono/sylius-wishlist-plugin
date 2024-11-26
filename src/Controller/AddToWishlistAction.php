<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Controller;

use Doctrine\Persistence\ManagerRegistry;
use Setono\Doctrine\ORMTrait;
use Setono\SyliusWishlistPlugin\Controller\DTO\ToggleWishlistResponse;
use Setono\SyliusWishlistPlugin\Factory\WishlistFactoryInterface;
use Setono\SyliusWishlistPlugin\Factory\WishlistItemFactoryInterface;
use Setono\SyliusWishlistPlugin\Provider\WishlistProviderInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * This controller action is responsible for adding a product or product variant to the wishlist
 */
final class AddToWishlistAction
{
    use ORMTrait;

    public function __construct(
        private readonly WishlistProviderInterface $wishlistProvider,
        private readonly WishlistItemFactoryInterface $wishlistItemFactory,
        ManagerRegistry $managerRegistry,
        private readonly WishlistFactoryInterface $wishlistFactory,
        private readonly UrlGeneratorInterface $urlGenerator,
        /** @var class-string<ProductInterface|ProductVariantInterface> $className */
        private readonly string $className,
    ) {
        $this->managerRegistry = $managerRegistry;
    }

    public function __invoke(int $id): JsonResponse
    {
        $entity = $this->getManager($this->className)->find($this->className, $id);

        if (null === $entity) {
            throw new NotFoundHttpException(sprintf('Product with id %s not found', $id));
        }

        $wishlistItem = $entity instanceof ProductInterface ? $this->wishlistItemFactory->createWithProduct($entity) : $this->wishlistItemFactory->createWithProductVariant($entity);

        $preSelectedWishlists = $this->wishlistProvider->getPreSelectedWishlists();
        if ([] === $preSelectedWishlists) {
            $preSelectedWishlists = [$this->wishlistFactory->createNew()];
        }

        $wishlistItemsCount = 0;
        foreach ($preSelectedWishlists as $wishlist) {
            $manager = $this->getManager($wishlist);
            $manager->persist($wishlist);

            $wishlist->addItem($wishlistItem);

            $wishlistItemsCount += $wishlist->getQuantity();
        }

        $manager->flush();

        return new JsonResponse(new ToggleWishlistResponse(
            ToggleWishlistResponse::EVENT_ADDED,
            $this->urlGenerator->generate($entity instanceof ProductInterface ? 'setono_sylius_wishlist_shop_wishlist_remove_product' : 'setono_sylius_wishlist_shop_wishlist_remove_product_variant', ['id' => $entity->getId()]),
            $wishlistItemsCount,
        ));
    }
}
