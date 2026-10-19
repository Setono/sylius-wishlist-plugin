<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Tests\Unit\Twig;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
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
    #[Test]
    public function it_delegates_on_wishlist_to_the_checker(): void
    {
        $product = $this->createMock(ProductInterface::class);

        $checker = $this->createMock(WishlistCheckerInterface::class);
        $checker->method('onWishlist')->with($product)->willReturn(true);

        self::assertTrue($this->runtime($this->createMock(WishlistProviderInterface::class), $checker)->onWishlist($product));
    }

    #[Test]
    public function it_has_a_wishlist_when_the_provider_returns_at_least_one(): void
    {
        $provider = $this->createMock(WishlistProviderInterface::class);
        $provider->method('getWishlists')->willReturn([$this->createMock(WishlistInterface::class)]);

        self::assertTrue($this->runtime($provider, $this->createMock(WishlistCheckerInterface::class))->hasWishlist());
    }

    #[Test]
    public function it_has_no_wishlist_when_the_provider_returns_none(): void
    {
        $provider = $this->createMock(WishlistProviderInterface::class);
        $provider->method('getWishlists')->willReturn([]);

        self::assertFalse($this->runtime($provider, $this->createMock(WishlistCheckerInterface::class))->hasWishlist());
    }

    #[Test]
    #[TestWith([true])]
    #[TestWith([false])]
    public function it_checks_the_availability_of_an_item_in_the_current_channel(bool $available): void
    {
        $item = new WishlistItem();
        $channel = $this->createMock(ChannelInterface::class);

        $channelContext = $this->createMock(ChannelContextInterface::class);
        $channelContext->method('getChannel')->willReturn($channel);

        $availabilityChecker = $this->createMock(WishlistItemAvailabilityCheckerInterface::class);
        $availabilityChecker->expects(self::once())->method('isAvailable')->with($item, $channel)->willReturn($available);

        $runtime = $this->runtime(
            $this->createMock(WishlistProviderInterface::class),
            $this->createMock(WishlistCheckerInterface::class),
            $availabilityChecker,
            $channelContext,
        );

        self::assertSame($available, $runtime->isAvailable($item));
    }

    #[Test]
    public function it_requires_the_current_channel_to_be_a_core_channel(): void
    {
        $channelContext = $this->createMock(ChannelContextInterface::class);
        $channelContext->method('getChannel')->willReturn($this->createMock(BaseChannelInterface::class));

        $runtime = $this->runtime(
            $this->createMock(WishlistProviderInterface::class),
            $this->createMock(WishlistCheckerInterface::class),
            channelContext: $channelContext,
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
            $availabilityChecker ?? $this->createMock(WishlistItemAvailabilityCheckerInterface::class),
            $channelContext ?? $this->createMock(ChannelContextInterface::class),
        );
    }
}
