<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Tests\Unit\Controller;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusWishlistPlugin\Controller\WishlistIndexAction;
use Setono\SyliusWishlistPlugin\Model\WishlistInterface;
use Setono\SyliusWishlistPlugin\Provider\WishlistProviderInterface;
use Twig\Environment;

final class WishlistIndexActionTest extends TestCase
{
    use ProphecyTrait;

    #[Test]
    public function it_renders_the_index_template_with_the_visitors_wishlists(): void
    {
        $wishlist = $this->prophesize(WishlistInterface::class)->reveal();

        $provider = $this->prophesize(WishlistProviderInterface::class);
        $provider->getWishlists()->willReturn([$wishlist]);

        $twig = $this->prophesize(Environment::class);
        $twig
            ->render(
                '@SetonoSyliusWishlistPlugin/shop/wishlist/index.html.twig',
                ['wishlists' => [$wishlist]],
            )
            ->shouldBeCalledOnce()
            ->willReturn('rendered')
        ;

        $response = (new WishlistIndexAction($twig->reveal(), $provider->reveal()))();

        self::assertSame('rendered', $response->getContent());
        self::assertSame(200, $response->getStatusCode());
    }
}
