<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Tests\Unit\Provider;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Setono\SyliusWishlistPlugin\Model\WishlistInterface;
use Setono\SyliusWishlistPlugin\Provider\CompositeWishlistProvider;
use Setono\SyliusWishlistPlugin\Provider\WishlistProviderInterface;

final class CompositeWishlistProviderTest extends TestCase
{
    #[Test]
    public function it_returns_no_wishlists_when_it_has_no_providers(): void
    {
        self::assertSame([], (new CompositeWishlistProvider())->getWishlists());
        self::assertSame([], (new CompositeWishlistProvider())->getPreSelectedWishlists());
    }

    #[Test]
    public function it_returns_the_first_non_empty_result(): void
    {
        $wishlist = $this->createMock(WishlistInterface::class);

        $composite = new CompositeWishlistProvider();
        $composite->add($this->provider([], []));
        $composite->add($this->provider([$wishlist], [$wishlist]));

        self::assertSame([$wishlist], $composite->getWishlists());
        self::assertSame([$wishlist], $composite->getPreSelectedWishlists());
    }

    #[Test]
    public function it_does_not_consult_later_providers_once_a_result_is_found(): void
    {
        $first = $this->createMock(WishlistInterface::class);

        $composite = new CompositeWishlistProvider();
        $composite->add($this->provider([$first], [$first]));
        $composite->add($this->provider([$this->createMock(WishlistInterface::class)], []));

        self::assertSame([$first], $composite->getWishlists());
    }

    /**
     * @param list<WishlistInterface> $wishlists
     * @param list<WishlistInterface> $preSelectedWishlists
     */
    private function provider(array $wishlists, array $preSelectedWishlists): WishlistProviderInterface
    {
        $provider = $this->createMock(WishlistProviderInterface::class);
        $provider->method('getWishlists')->willReturn($wishlists);
        $provider->method('getPreSelectedWishlists')->willReturn($preSelectedWishlists);

        return $provider;
    }
}
