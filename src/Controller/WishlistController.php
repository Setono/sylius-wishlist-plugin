<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Controller;

use Doctrine\Persistence\ManagerRegistry;
use Setono\Doctrine\ORMTrait;
use Setono\SyliusWishlistPlugin\Model\WishlistInterface;
use Sylius\Component\Core\Factory\CartItemFactoryInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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

    public function selectWishlists(Request $request, int $product): Response
    {
        return new Response('Not implemented', Response::HTTP_NOT_IMPLEMENTED);
    }
}
