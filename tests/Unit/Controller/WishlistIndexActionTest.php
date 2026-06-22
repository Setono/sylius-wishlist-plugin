<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Tests\Unit\Controller;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Setono\SyliusWishlistPlugin\Controller\WishlistIndexAction;
use Setono\SyliusWishlistPlugin\Model\WishlistInterface;
use Setono\SyliusWishlistPlugin\Provider\WishlistProviderInterface;
use Twig\Environment;

final class WishlistIndexActionTest extends TestCase
{
    #[Test]
    public function it_renders_the_index_template_with_the_visitors_wishlists(): void
    {
        $wishlist = $this->createMock(WishlistInterface::class);

        $provider = $this->createMock(WishlistProviderInterface::class);
        $provider->method('getWishlists')->willReturn([$wishlist]);

        $twig = $this->createMock(Environment::class);
        $twig->expects(self::once())
            ->method('render')
            ->with(
                '@SetonoSyliusWishlistPlugin/shop/wishlist/index.html.twig',
                ['wishlists' => [$wishlist]],
            )
            ->willReturn('rendered')
        ;

        $response = (new WishlistIndexAction($twig, $provider))();

        self::assertSame('rendered', $response->getContent());
        self::assertSame(200, $response->getStatusCode());
    }
}
