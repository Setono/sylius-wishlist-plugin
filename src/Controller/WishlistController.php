<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Controller;

use Doctrine\Persistence\ManagerRegistry;
use Setono\Doctrine\ORMTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class WishlistController
{
    use ORMTrait;

    public function __construct(ManagerRegistry $managerRegistry)
    {
        $this->managerRegistry = $managerRegistry;
    }

    public function selectWishlists(Request $request, int $product): Response
    {
        return new Response('Not implemented', Response::HTTP_NOT_IMPLEMENTED);
    }
}
