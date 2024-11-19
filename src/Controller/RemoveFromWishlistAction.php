<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Controller;

use Doctrine\Persistence\ManagerRegistry;
use Setono\Doctrine\ORMTrait;
use Setono\SyliusWishlistPlugin\Provider\WishlistProviderInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Environment;

/**
 * This controller action is responsible for removing a product or product variant from the wishlist
 */
final class RemoveFromWishlistAction
{
    use ORMTrait;

    public function __construct(
        private readonly WishlistProviderInterface $wishlistProvider,
        private readonly Environment $twig,
        ManagerRegistry $managerRegistry,
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

        foreach ($this->wishlistProvider->getWishlists() as $wishlistEntity) {
            $entity instanceof ProductInterface ? $wishlistEntity->removeProduct($entity) : $wishlistEntity->removeProductVariant($entity);
        }

        $this->getManager($entity)->flush();

        return new JsonResponse([
            'toggleButton' => $this->twig->render(
                '@SetonoSyliusWishlistPlugin/shop/wishlist/_toggle_button.html.twig',
                [
                    'product' => $entity instanceof ProductInterface ? $entity : $entity->getProduct(),
                    'productVariant' => $entity instanceof ProductVariantInterface ? $entity : null,
                ],
            ),
        ]);
    }
}
