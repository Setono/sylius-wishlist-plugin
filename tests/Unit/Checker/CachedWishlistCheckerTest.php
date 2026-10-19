<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Tests\Unit\Checker;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusWishlistPlugin\Checker\CachedWishlistChecker;
use Setono\SyliusWishlistPlugin\Checker\WishlistCheckerInterface;
use Sylius\Component\Core\Model\ProductInterface;

final class CachedWishlistCheckerTest extends TestCase
{
    use ProphecyTrait;

    #[Test]
    public function it_returns_the_result_of_the_decorated_checker(): void
    {
        $product = $this->prophesize(ProductInterface::class)->reveal();

        $decorated = $this->prophesize(WishlistCheckerInterface::class);
        $decorated->onWishlist($product)->willReturn(true);

        self::assertTrue((new CachedWishlistChecker($decorated->reveal()))->onWishlist($product));
    }

    #[Test]
    public function it_only_asks_the_decorated_checker_once_per_object(): void
    {
        $product = $this->prophesize(ProductInterface::class)->reveal();

        $decorated = $this->prophesize(WishlistCheckerInterface::class);
        $decorated->onWishlist($product)->shouldBeCalledOnce()->willReturn(true);

        $checker = new CachedWishlistChecker($decorated->reveal());
        $checker->onWishlist($product);
        $checker->onWishlist($product);
    }

    #[Test]
    public function it_asks_the_decorated_checker_for_each_distinct_object(): void
    {
        $first = $this->prophesize(ProductInterface::class)->reveal();
        $second = $this->prophesize(ProductInterface::class)->reveal();

        $decorated = $this->prophesize(WishlistCheckerInterface::class);
        $decorated->onWishlist($first)->shouldBeCalledOnce()->willReturn(false);
        $decorated->onWishlist($second)->shouldBeCalledOnce()->willReturn(false);

        $checker = new CachedWishlistChecker($decorated->reveal());
        $checker->onWishlist($first);
        $checker->onWishlist($second);
    }

    #[Test]
    public function it_asks_the_decorated_checker_again_after_a_reset(): void
    {
        $product = $this->prophesize(ProductInterface::class)->reveal();

        $decorated = $this->prophesize(WishlistCheckerInterface::class);
        $decorated->onWishlist($product)->shouldBeCalledTimes(2)->willReturn(true);

        $checker = new CachedWishlistChecker($decorated->reveal());
        $checker->onWishlist($product);

        $checker->reset();

        $checker->onWishlist($product);
    }
}
