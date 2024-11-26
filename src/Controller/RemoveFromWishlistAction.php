<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Controller;

use Doctrine\Persistence\ManagerRegistry;
use Setono\Doctrine\ORMTrait;
use Setono\SyliusWishlistPlugin\Controller\DTO\ToggleWishlistResponse;
use Setono\SyliusWishlistPlugin\Provider\WishlistProviderInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * This controller action is responsible for removing a product or product variant from the wishlist
 */
final class RemoveFromWishlistAction
{
    use ORMTrait;

    public function __construct(
        private readonly WishlistProviderInterface $wishlistProvider,
        ManagerRegistry $managerRegistry,
        private readonly UrlGeneratorInterface $urlGenerator,
        /** @var class-string<ProductInterface|ProductVariantInterface> $className */
        private readonly string $className,
    ) {
        $this->managerRegistry = $managerRegistry;
    }

    public function __invoke(Request $request, int $id): JsonResponse
    {
        $entity = $this->getManager($this->className)->find($this->className, $id);

        if (null === $entity) {
            throw new NotFoundHttpException(sprintf('Product with id %s not found', $id));
        }

        $wishlistItemsCount = 0;
        foreach ($this->wishlistProvider->getWishlists() as $wishlist) {
            $entity instanceof ProductInterface ? $wishlist->removeProduct($entity) : $wishlist->removeProductVariant($entity);

            $wishlistItemsCount += $wishlist->getQuantity();
        }

        $this->getManager($entity)->flush();

        return new JsonResponse(new ToggleWishlistResponse(
            ToggleWishlistResponse::EVENT_REMOVED,
            $this->urlGenerator->generate($entity instanceof ProductInterface ? 'setono_sylius_wishlist_shop_wishlist_add_product' : 'setono_sylius_wishlist_shop_wishlist_add_product_variant', ['id' => $entity->getId()]),
            $wishlistItemsCount,
        ));
    }
}
