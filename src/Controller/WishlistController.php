<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class WishlistController
{
    public function selectWishlists(Request $request, int $product): Response
    {
        return new Response('Not implemented', Response::HTTP_NOT_IMPLEMENTED);
    }
}
