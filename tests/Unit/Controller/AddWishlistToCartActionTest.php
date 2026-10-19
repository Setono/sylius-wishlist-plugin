<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Tests\Unit\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Setono\SyliusWishlistPlugin\Controller\AddWishlistToCartAction;
use Setono\SyliusWishlistPlugin\Model\UserWishlist;
use Setono\SyliusWishlistPlugin\Model\WishlistInterface;
use Setono\SyliusWishlistPlugin\Model\WishlistItem;
use Setono\SyliusWishlistPlugin\Repository\WishlistRepositoryInterface;
use Sylius\Component\Core\Factory\CartItemFactoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ChannelPricingInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Sylius\Component\Order\Model\OrderInterface as BaseOrderInterface;
use Sylius\Component\Order\Modifier\OrderItemQuantityModifierInterface;
use Sylius\Component\Order\Modifier\OrderModifierInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class AddWishlistToCartActionTest extends TestCase
{
    private ChannelInterface $channel;

    protected function setUp(): void
    {
        $this->channel = $this->createMock(ChannelInterface::class);
    }

    #[Test]
    public function it_throws_not_found_when_the_wishlist_does_not_exist(): void
    {
        $cartContext = $this->createMock(CartContextInterface::class);
        $cartContext->expects(self::never())->method('getCart');

        $action = $this->action(null, cartContext: $cartContext);

        $this->expectException(NotFoundHttpException::class);

        $action($this->request(), 'missing');
    }

    #[Test]
    public function it_adds_every_item_to_the_cart_and_redirects_back_to_the_wishlist(): void
    {
        $cart = $this->cart();

        $first = $this->variant($this->product('T-shirt'));
        $second = $this->variant($this->product('Cap'));

        $wishlist = new UserWishlist();
        $wishlist->addItem($this->item($first, 2));
        $wishlist->addItem($this->item($second, 1));

        $firstCartItem = $this->createMock(OrderItemInterface::class);
        $firstCartItem->expects(self::once())->method('setVariant')->with($first);
        $secondCartItem = $this->createMock(OrderItemInterface::class);
        $secondCartItem->expects(self::once())->method('setVariant')->with($second);

        $cartItemFactory = $this->createMock(CartItemFactoryInterface::class);
        $cartItemFactory->method('createNew')->willReturnOnConsecutiveCalls($firstCartItem, $secondCartItem);

        $quantityModifier = $this->createMock(OrderItemQuantityModifierInterface::class);
        $quantityModifier
            ->expects(self::exactly(2))
            ->method('modify')
            ->willReturnCallback(static function (OrderItemInterface $cartItem, int $quantity) use ($firstCartItem, $secondCartItem): void {
                self::assertSame($cartItem === $firstCartItem ? 2 : 1, $quantity);
                self::assertContains($cartItem, [$firstCartItem, $secondCartItem]);
            })
        ;

        $added = [];
        $orderModifier = $this->createMock(OrderModifierInterface::class);
        $orderModifier
            ->expects(self::exactly(2))
            ->method('addToOrder')
            ->willReturnCallback(static function (OrderInterface $order, OrderItemInterface $cartItem) use ($cart, &$added): void {
                self::assertSame($cart, $order);
                $added[] = $cartItem;
            })
        ;

        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->expects(self::once())->method('persist')->with($cart);
        $manager->expects(self::once())->method('flush');

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator
            ->expects(self::once())
            ->method('generate')
            ->with('setono_sylius_wishlist_shop_wishlist_show', ['uuid' => 'uuid'])
            ->willReturn('/wishlists/uuid')
        ;

        $request = $this->request();

        $response = $this->action(
            $wishlist,
            cart: $cart,
            cartItemFactory: $cartItemFactory,
            quantityModifier: $quantityModifier,
            orderModifier: $orderModifier,
            manager: $manager,
            urlGenerator: $urlGenerator,
        )($request, 'uuid');

        self::assertSame('/wishlists/uuid', $response->getTargetUrl());
        self::assertSame([$firstCartItem, $secondCartItem], $added);
        self::assertSame(['success' => ['setono_sylius_wishlist.wishlist_added_to_cart']], $this->flashes($request));
    }

    #[Test]
    public function it_adds_the_items_with_a_variant_and_names_the_products_without_one(): void
    {
        $withVariant = $this->variant($this->product('Cap'));

        $wishlist = new UserWishlist();
        $wishlist->addItem($this->productItem($this->product('T-shirt', 'TSHIRT')));
        $wishlist->addItem($this->item($withVariant, 1));
        // Products without a name in the current locale are named by their code
        $wishlist->addItem($this->productItem($this->product(null, 'JEANS')));

        $cartItem = $this->createMock(OrderItemInterface::class);
        $cartItem->expects(self::once())->method('setVariant')->with($withVariant);

        $cartItemFactory = $this->createMock(CartItemFactoryInterface::class);
        $cartItemFactory->expects(self::once())->method('createNew')->willReturn($cartItem);

        $orderModifier = $this->createMock(OrderModifierInterface::class);
        $orderModifier->expects(self::once())->method('addToOrder');

        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->expects(self::once())->method('flush');

        $request = $this->request();

        $this->action($wishlist, cartItemFactory: $cartItemFactory, orderModifier: $orderModifier, manager: $manager)($request, 'uuid');

        self::assertSame([
            'success' => ['setono_sylius_wishlist.wishlist_added_to_cart'],
            'warning' => [[
                'message' => 'setono_sylius_wishlist.products_without_variant_not_added_to_cart',
                'parameters' => ['%products%' => 'T-shirt, JEANS'],
            ]],
        ], $this->flashes($request));
    }

    #[Test]
    public function it_names_each_product_without_a_variant_once(): void
    {
        $product = $this->product('T-shirt');

        $wishlist = new UserWishlist();
        $wishlist->addItem($this->productItem($product));
        $wishlist->addItem($this->productItem($product));

        $request = $this->request();

        $this->action($wishlist)($request, 'uuid');

        self::assertSame([
            'warning' => [[
                'message' => 'setono_sylius_wishlist.products_without_variant_not_added_to_cart',
                'parameters' => ['%products%' => 'T-shirt'],
            ]],
        ], $this->flashes($request));
    }

    #[Test]
    public function it_ignores_items_without_a_product(): void
    {
        $wishlist = new UserWishlist();
        $wishlist->addItem(new WishlistItem());
        $wishlist->addItem($this->item($this->variant($this->product('Cap')), 1));

        $orderModifier = $this->createMock(OrderModifierInterface::class);
        $orderModifier->expects(self::once())->method('addToOrder');

        $request = $this->request();

        $this->action($wishlist, orderModifier: $orderModifier)($request, 'uuid');

        self::assertSame(['success' => ['setono_sylius_wishlist.wishlist_added_to_cart']], $this->flashes($request));
    }

    /**
     * @param array{productEnabled?: bool, inChannel?: bool, variantEnabled?: bool, price?: int|null, priced?: bool} $availability
     */
    #[Test]
    #[DataProvider('provideUnavailableVariants')]
    public function it_names_the_unavailable_products_instead_of_adding_them(array $availability): void
    {
        $unavailable = $this->variant(
            $this->product('T-shirt', productEnabled: $availability['productEnabled'] ?? true, inChannel: $availability['inChannel'] ?? true),
            enabled: $availability['variantEnabled'] ?? true,
            price: array_key_exists('price', $availability) ? $availability['price'] : 100,
            priced: $availability['priced'] ?? true,
        );
        $available = $this->variant($this->product('Cap'));

        $wishlist = new UserWishlist();
        $wishlist->addItem($this->item($unavailable, 1));
        $wishlist->addItem($this->item($available, 1));

        $cartItem = $this->createMock(OrderItemInterface::class);
        $cartItem->expects(self::once())->method('setVariant')->with($available);

        $cartItemFactory = $this->createMock(CartItemFactoryInterface::class);
        $cartItemFactory->expects(self::once())->method('createNew')->willReturn($cartItem);

        $request = $this->request();

        $this->action($wishlist, cartItemFactory: $cartItemFactory)($request, 'uuid');

        self::assertSame([
            'success' => ['setono_sylius_wishlist.wishlist_added_to_cart'],
            'warning' => [[
                'message' => 'setono_sylius_wishlist.products_unavailable_not_added_to_cart',
                'parameters' => ['%products%' => 'T-shirt'],
            ]],
        ], $this->flashes($request));
    }

    /**
     * @return iterable<string, array{array{productEnabled?: bool, inChannel?: bool, variantEnabled?: bool, price?: int|null, priced?: bool}}>
     */
    public static function provideUnavailableVariants(): iterable
    {
        yield 'disabled product' => [['productEnabled' => false]];
        yield 'product not in the channel' => [['inChannel' => false]];
        yield 'disabled variant' => [['variantEnabled' => false]];
        yield 'no channel pricing' => [['priced' => false]];
        yield 'channel pricing without a price' => [['price' => null]];
    }

    #[Test]
    public function it_reports_products_without_a_variant_and_unavailable_products_separately(): void
    {
        $wishlist = new UserWishlist();
        $wishlist->addItem($this->productItem($this->product('T-shirt')));
        $wishlist->addItem($this->item($this->variant($this->product('Cap'), enabled: false), 1));

        $request = $this->request();

        $this->action($wishlist)($request, 'uuid');

        self::assertSame([
            'warning' => [
                [
                    'message' => 'setono_sylius_wishlist.products_without_variant_not_added_to_cart',
                    'parameters' => ['%products%' => 'T-shirt'],
                ],
                [
                    'message' => 'setono_sylius_wishlist.products_unavailable_not_added_to_cart',
                    'parameters' => ['%products%' => 'Cap'],
                ],
            ],
        ], $this->flashes($request));
    }

    #[Test]
    public function it_requires_the_cart_to_have_a_channel(): void
    {
        $cart = $this->createMock(OrderInterface::class);
        $cart->method('getChannel')->willReturn(null);

        $action = $this->action(new UserWishlist(), cart: $cart);

        $this->expectException(\InvalidArgumentException::class);

        $action($this->request(), 'uuid');
    }

    #[Test]
    public function it_requires_the_cart_to_be_a_core_order(): void
    {
        $cartContext = $this->createMock(CartContextInterface::class);
        $cartContext->method('getCart')->willReturn($this->createMock(BaseOrderInterface::class));

        $action = $this->action(new UserWishlist(), cartContext: $cartContext);

        $this->expectException(\InvalidArgumentException::class);

        $action($this->request(), 'uuid');
    }

    #[Test]
    public function it_neither_saves_the_cart_nor_reports_success_when_no_item_has_a_variant(): void
    {
        $wishlist = new UserWishlist();
        $wishlist->addItem($this->productItem($this->product('T-shirt')));

        $orderModifier = $this->createMock(OrderModifierInterface::class);
        $orderModifier->expects(self::never())->method('addToOrder');

        $managerRegistry = $this->createMock(ManagerRegistry::class);
        $managerRegistry->expects(self::never())->method('getManagerForClass');

        $request = $this->request();

        $this->action($wishlist, orderModifier: $orderModifier, managerRegistry: $managerRegistry)($request, 'uuid');

        self::assertSame(['warning'], array_keys($this->flashes($request)));
    }

    #[Test]
    public function it_does_nothing_for_an_empty_wishlist(): void
    {
        $managerRegistry = $this->createMock(ManagerRegistry::class);
        $managerRegistry->expects(self::never())->method('getManagerForClass');

        $request = $this->request();

        $this->action(new UserWishlist(), managerRegistry: $managerRegistry)($request, 'uuid');

        self::assertSame([], $this->flashes($request));
    }

    /**
     * @param CartItemFactoryInterface<OrderItemInterface>|null $cartItemFactory
     */
    private function action(
        ?WishlistInterface $wishlist,
        ?OrderInterface $cart = null,
        ?CartContextInterface $cartContext = null,
        ?CartItemFactoryInterface $cartItemFactory = null,
        ?OrderItemQuantityModifierInterface $quantityModifier = null,
        ?OrderModifierInterface $orderModifier = null,
        ?EntityManagerInterface $manager = null,
        ?ManagerRegistry $managerRegistry = null,
        ?UrlGeneratorInterface $urlGenerator = null,
    ): AddWishlistToCartAction {
        /** @var MockObject&WishlistRepositoryInterface<WishlistInterface> $repository */
        $repository = $this->createMock(WishlistRepositoryInterface::class);
        $repository->method('findOneByUuid')->willReturn($wishlist);

        if (null === $cartContext) {
            $cartContext = $this->createMock(CartContextInterface::class);
            $cartContext->method('getCart')->willReturn($cart ?? $this->cart());
        }

        if (null === $cartItemFactory) {
            $cartItemFactory = $this->createMock(CartItemFactoryInterface::class);
            $cartItemFactory->method('createNew')->willReturnCallback(fn (): OrderItemInterface => $this->createMock(OrderItemInterface::class));
        }

        if (null === $managerRegistry) {
            $managerRegistry = $this->createMock(ManagerRegistry::class);
            $managerRegistry->method('getManagerForClass')->willReturn($manager ?? $this->createMock(EntityManagerInterface::class));
        }

        if (null === $urlGenerator) {
            $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
            $urlGenerator->method('generate')->willReturn('/wishlists/uuid');
        }

        return new AddWishlistToCartAction(
            $repository,
            $quantityModifier ?? $this->createMock(OrderItemQuantityModifierInterface::class),
            $orderModifier ?? $this->createMock(OrderModifierInterface::class),
            $cartContext,
            $urlGenerator,
            $cartItemFactory,
            $managerRegistry,
        );
    }

    private function request(): Request
    {
        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));

        return $request;
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function flashes(Request $request): array
    {
        $session = $request->getSession();
        self::assertInstanceOf(Session::class, $session);

        /** @var array<string, list<mixed>> $flashes */
        $flashes = $session->getFlashBag()->peekAll();

        return $flashes;
    }

    private function item(ProductVariantInterface $variant, int $quantity): WishlistItem
    {
        $item = new WishlistItem();
        $item->setVariant($variant);
        $item->setQuantity($quantity);

        return $item;
    }

    private function productItem(ProductInterface $product): WishlistItem
    {
        $item = new WishlistItem();
        $item->setProduct($product);

        return $item;
    }

    private function cart(): OrderInterface&MockObject
    {
        $cart = $this->createMock(OrderInterface::class);
        $cart->method('getChannel')->willReturn($this->channel);

        return $cart;
    }

    private function product(?string $name, ?string $code = null, bool $productEnabled = true, bool $inChannel = true): ProductInterface&MockObject
    {
        $product = $this->createMock(ProductInterface::class);
        $product->method('getName')->willReturn($name);
        $product->method('getCode')->willReturn($code);
        $product->method('isEnabled')->willReturn($productEnabled);
        $product->method('hasChannel')->willReturnCallback(fn (ChannelInterface $channel): bool => $inChannel && $channel === $this->channel);

        return $product;
    }

    private function variant(ProductInterface $product, bool $enabled = true, ?int $price = 100, bool $priced = true): ProductVariantInterface&MockObject
    {
        $channelPricing = $this->createMock(ChannelPricingInterface::class);
        $channelPricing->method('getPrice')->willReturn($price);

        $variant = $this->createMock(ProductVariantInterface::class);
        $variant->method('getProduct')->willReturn($product);
        $variant->method('isEnabled')->willReturn($enabled);
        $variant->method('getChannelPricingForChannel')->willReturnCallback(
            fn (ChannelInterface $channel): ?ChannelPricingInterface => $priced && $channel === $this->channel ? $channelPricing : null,
        );

        return $variant;
    }
}
