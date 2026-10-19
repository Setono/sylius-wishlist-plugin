<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Tests\Unit\Provider;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusWishlistPlugin\Model\WishlistInterface;
use Setono\SyliusWishlistPlugin\Provider\CompositeWishlistProvider;
use Setono\SyliusWishlistPlugin\Provider\WishlistProviderInterface;

final class CompositeWishlistProviderTest extends TestCase
{
    use ProphecyTrait;

    #[Test]
    public function it_returns_no_wishlists_when_it_has_no_providers(): void
    {
        self::assertSame([], (new CompositeWishlistProvider())->getWishlists());
        self::assertSame([], (new CompositeWishlistProvider())->getPreSelectedWishlists());
    }

    #[Test]
    public function it_returns_the_first_non_empty_result(): void
    {
        $first = $this->prophesize(WishlistInterface::class)->reveal();
        $second = $this->prophesize(WishlistInterface::class)->reveal();

        $composite = new CompositeWishlistProvider();
        $composite->add($this->provider([], []));
        $composite->add($this->provider([$first, $second], [$second, $first]));

        self::assertSame([$first, $second], $composite->getWishlists());
        self::assertSame([$second, $first], $composite->getPreSelectedWishlists());
    }

    #[Test]
    public function it_does_not_consult_later_providers_once_a_result_is_found(): void
    {
        $first = $this->prophesize(WishlistInterface::class)->reveal();

        $composite = new CompositeWishlistProvider();
        $composite->add($this->provider([$first], [$first]));
        $composite->add($this->provider([$this->prophesize(WishlistInterface::class)->reveal()], []));

        self::assertSame([$first], $composite->getWishlists());
    }

    /**
     * @param list<WishlistInterface> $wishlists
     * @param list<WishlistInterface> $preSelectedWishlists
     */
    private function provider(array $wishlists, array $preSelectedWishlists): WishlistProviderInterface
    {
        $provider = $this->prophesize(WishlistProviderInterface::class);
        $provider->getWishlists()->willReturn($wishlists);
        $provider->getPreSelectedWishlists()->willReturn($preSelectedWishlists);

        return $provider->reveal();
    }
}
