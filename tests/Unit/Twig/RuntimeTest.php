<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Tests\Unit\Twig;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusWishlistPlugin\Checker\WishlistCheckerInterface;
use Setono\SyliusWishlistPlugin\Checker\WishlistItemAvailabilityCheckerInterface;
use Setono\SyliusWishlistPlugin\Model\WishlistInterface;
use Setono\SyliusWishlistPlugin\Model\WishlistItem;
use Setono\SyliusWishlistPlugin\Provider\WishlistProviderInterface;
use Setono\SyliusWishlistPlugin\Twig\Runtime;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Model\ChannelInterface as BaseChannelInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;

final class RuntimeTest extends TestCase
{
    use ProphecyTrait;

    #[Test]
    public function it_delegates_on_wishlist_to_the_checker(): void
    {
        $product = $this->prophesize(ProductInterface::class)->reveal();

        $checker = $this->prophesize(WishlistCheckerInterface::class);
        $checker->onWishlist($product)->willReturn(true);

        self::assertTrue($this->runtime($this->prophesize(WishlistProviderInterface::class)->reveal(), $checker->reveal())->onWishlist($product));
    }

    #[Test]
    public function it_has_a_wishlist_when_the_provider_returns_at_least_one(): void
    {
        $provider = $this->prophesize(WishlistProviderInterface::class);
        $provider->getWishlists()->willReturn([$this->prophesize(WishlistInterface::class)->reveal()]);

        self::assertTrue($this->runtime($provider->reveal(), $this->prophesize(WishlistCheckerInterface::class)->reveal())->hasWishlist());
    }

    #[Test]
    public function it_has_no_wishlist_when_the_provider_returns_none(): void
    {
        $provider = $this->prophesize(WishlistProviderInterface::class);
        $provider->getWishlists()->willReturn([]);

        self::assertFalse($this->runtime($provider->reveal(), $this->prophesize(WishlistCheckerInterface::class)->reveal())->hasWishlist());
    }

    #[Test]
    #[TestWith([true])]
    #[TestWith([false])]
    public function it_checks_the_availability_of_an_item_in_the_current_channel(bool $available): void
    {
        $item = new WishlistItem();
        $channel = $this->prophesize(ChannelInterface::class)->reveal();

        $channelContext = $this->prophesize(ChannelContextInterface::class);
        $channelContext->getChannel()->willReturn($channel);

        $availabilityChecker = $this->prophesize(WishlistItemAvailabilityCheckerInterface::class);
        $availabilityChecker->isAvailable($item, $channel)->shouldBeCalledOnce()->willReturn($available);

        $runtime = $this->runtime(
            $this->prophesize(WishlistProviderInterface::class)->reveal(),
            $this->prophesize(WishlistCheckerInterface::class)->reveal(),
            $availabilityChecker->reveal(),
            $channelContext->reveal(),
        );

        self::assertSame($available, $runtime->isAvailable($item));
    }

    #[Test]
    public function it_requires_the_current_channel_to_be_a_core_channel(): void
    {
        $channelContext = $this->prophesize(ChannelContextInterface::class);
        $channelContext->getChannel()->willReturn($this->prophesize(BaseChannelInterface::class)->reveal());

        $runtime = $this->runtime(
            $this->prophesize(WishlistProviderInterface::class)->reveal(),
            $this->prophesize(WishlistCheckerInterface::class)->reveal(),
            channelContext: $channelContext->reveal(),
        );

        $this->expectException(\InvalidArgumentException::class);

        $runtime->isAvailable(new WishlistItem());
    }

    private function runtime(
        WishlistProviderInterface $provider,
        WishlistCheckerInterface $checker,
        ?WishlistItemAvailabilityCheckerInterface $availabilityChecker = null,
        ?ChannelContextInterface $channelContext = null,
    ): Runtime {
        return new Runtime(
            $provider,
            $checker,
            $availabilityChecker ?? $this->prophesize(WishlistItemAvailabilityCheckerInterface::class)->reveal(),
            $channelContext ?? $this->prophesize(ChannelContextInterface::class)->reveal(),
        );
    }
}
