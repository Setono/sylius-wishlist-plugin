<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Tests\Unit\Checker;

use PHPUnit\Framework\TestCase;
use Setono\SyliusWishlistPlugin\Checker\CachedWishlistChecker;
use Setono\SyliusWishlistPlugin\Checker\WishlistCheckerInterface;
use Sylius\Component\Core\Model\ProductInterface;

final class CachedWishlistCheckerTest extends TestCase
{
    /** @test */
    public function it_returns_the_result_of_the_decorated_checker(): void
    {
        $product = $this->createMock(ProductInterface::class);

        $decorated = $this->createMock(WishlistCheckerInterface::class);
        $decorated->method('onWishlist')->with($product)->willReturn(true);

        self::assertTrue((new CachedWishlistChecker($decorated))->onWishlist($product));
    }

    /** @test */
    public function it_only_asks_the_decorated_checker_once_per_object(): void
    {
        $product = $this->createMock(ProductInterface::class);

        $decorated = $this->createMock(WishlistCheckerInterface::class);
        $decorated->expects(self::once())->method('onWishlist')->with($product)->willReturn(true);

        $checker = new CachedWishlistChecker($decorated);
        $checker->onWishlist($product);
        $checker->onWishlist($product);
    }

    /** @test */
    public function it_asks_the_decorated_checker_for_each_distinct_object(): void
    {
        $decorated = $this->createMock(WishlistCheckerInterface::class);
        $decorated->expects(self::exactly(2))->method('onWishlist')->willReturn(false);

        $checker = new CachedWishlistChecker($decorated);
        $checker->onWishlist($this->createMock(ProductInterface::class));
        $checker->onWishlist($this->createMock(ProductInterface::class));
    }
}
