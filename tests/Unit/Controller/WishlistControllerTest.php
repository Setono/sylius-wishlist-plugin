<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Tests\Unit\Controller;

use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusWishlistPlugin\Controller\WishlistController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class WishlistControllerTest extends TestCase
{
    use ProphecyTrait;

    #[Test]
    public function selecting_wishlists_is_not_implemented_yet(): void
    {
        $controller = new WishlistController($this->prophesize(ManagerRegistry::class)->reveal());

        $response = $controller->selectWishlists(new Request(), 1);

        self::assertSame(Response::HTTP_NOT_IMPLEMENTED, $response->getStatusCode());
        self::assertSame('Not implemented', $response->getContent());
    }
}
