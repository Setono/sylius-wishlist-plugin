<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Tests\Unit\Checker;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusWishlistPlugin\Checker\WishlistItemAvailabilityChecker;
use Setono\SyliusWishlistPlugin\Model\WishlistItem;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ChannelPricingInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;

final class WishlistItemAvailabilityCheckerTest extends TestCase
{
    use ProphecyTrait;

    private ChannelInterface $channel;

    protected function setUp(): void
    {
        $this->channel = $this->prophesize(ChannelInterface::class)->reveal();
    }

    #[Test]
    public function it_is_available_when_the_product_and_variant_are_sold_in_the_channel(): void
    {
        self::assertTrue((new WishlistItemAvailabilityChecker())->isAvailable($this->variantItem(), $this->channel));
    }

    #[Test]
    public function it_is_available_when_the_product_is_sold_in_the_channel_and_no_variant_is_chosen_yet(): void
    {
        $item = new WishlistItem();
        $item->setProduct($this->product());

        self::assertTrue((new WishlistItemAvailabilityChecker())->isAvailable($item, $this->channel));
    }

    #[Test]
    public function it_is_unavailable_without_a_product(): void
    {
        self::assertFalse((new WishlistItemAvailabilityChecker())->isAvailable(new WishlistItem(), $this->channel));
    }

    /**
     * @param array{productEnabled?: bool, inChannel?: bool, variantEnabled?: bool, priced?: bool, price?: int|null} $state
     */
    #[Test]
    #[DataProvider('provideUnavailableItems')]
    public function it_is_unavailable_when_the_item_is_not_sold_in_the_channel(array $state, bool $withVariant): void
    {
        $product = $this->product($state['productEnabled'] ?? true, $state['inChannel'] ?? true);

        if ($withVariant) {
            $item = $this->variantItem($product, $state['variantEnabled'] ?? true, $state['priced'] ?? true, array_key_exists('price', $state) ? $state['price'] : 100);
        } else {
            $item = new WishlistItem();
            $item->setProduct($product);
        }

        self::assertFalse((new WishlistItemAvailabilityChecker())->isAvailable($item, $this->channel));
    }

    /**
     * @return iterable<string, array{array{productEnabled?: bool, inChannel?: bool, variantEnabled?: bool, priced?: bool, price?: int|null}, bool}>
     */
    public static function provideUnavailableItems(): iterable
    {
        yield 'disabled product' => [['productEnabled' => false], true];
        yield 'disabled product without a variant' => [['productEnabled' => false], false];
        yield 'product not in the channel' => [['inChannel' => false], true];
        yield 'product not in the channel without a variant' => [['inChannel' => false], false];
        yield 'disabled variant' => [['variantEnabled' => false], true];
        yield 'no channel pricing' => [['priced' => false], true];
        yield 'channel pricing without a price' => [['price' => null], true];
    }

    private function variantItem(?ProductInterface $product = null, bool $enabled = true, bool $priced = true, ?int $price = 100): WishlistItem
    {
        $channelPricing = $this->prophesize(ChannelPricingInterface::class);
        $channelPricing->getPrice()->willReturn($price);

        // Prophecy binds non-static closures to the prophecy, so $this->channel wouldn't resolve inside the callback
        $channel = $this->channel;

        $variant = $this->prophesize(ProductVariantInterface::class);
        $variant->getProduct()->willReturn($product ?? $this->product());
        $variant->isEnabled()->willReturn($enabled);
        $variant->getChannelPricingForChannel(Argument::any())->will(
            static fn (array $args): ?ChannelPricingInterface => $priced && $args[0] === $channel ? $channelPricing->reveal() : null,
        );

        $item = new WishlistItem();
        $item->setVariant($variant->reveal());

        return $item;
    }

    private function product(bool $enabled = true, bool $inChannel = true): ProductInterface
    {
        $channel = $this->channel;

        $product = $this->prophesize(ProductInterface::class);
        $product->isEnabled()->willReturn($enabled);
        $product->hasChannel(Argument::any())->will(static fn (array $args): bool => $inChannel && $args[0] === $channel);

        return $product->reveal();
    }
}
