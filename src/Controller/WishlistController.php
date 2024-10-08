<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Controller;

use Doctrine\Persistence\ManagerRegistry;
use Setono\Doctrine\ORMTrait;
use Setono\SyliusWishlistPlugin\Factory\WishlistItemFactoryInterface;
use Setono\SyliusWishlistPlugin\Provider\WishlistProviderInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class WishlistController
{
    use ORMTrait;

    public function __construct(
        ManagerRegistry $managerRegistry,
        private readonly WishlistProviderInterface $wishlistProvider,
        private readonly WishlistItemFactoryInterface $wishlistItemFactory,
    ) {
        $this->managerRegistry = $managerRegistry;
    }

    public function add(int $productVariant): JsonResponse
    {
        try {
            $wishlistItem = $this->wishlistItemFactory->createWithVariant($productVariant);
        } catch (\InvalidArgumentException) {
            throw new NotFoundHttpException(sprintf('Product variant with id %s not found', $productVariant));
        }

        $wishlists = $this->wishlistProvider->getWishlists();
        $wishlists[0]->addItem($wishlistItem);

        $manager = $this->getManager($wishlists[0]);
        $manager->persist($wishlists[0]);
        $manager->flush();

        return new JsonResponse();
    }
}
