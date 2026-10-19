<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Tests\Unit\Controller;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusWishlistPlugin\Checker\WishlistItemAvailabilityCheckerInterface;
use Setono\SyliusWishlistPlugin\Controller\AddWishlistToCartAction;
use Setono\SyliusWishlistPlugin\Model\UserWishlist;
use Setono\SyliusWishlistPlugin\Model\WishlistInterface;
use Setono\SyliusWishlistPlugin\Model\WishlistItem;
use Setono\SyliusWishlistPlugin\Model\WishlistItemInterface;
use Setono\SyliusWishlistPlugin\Repository\WishlistRepositoryInterface;
use Sylius\Component\Core\Factory\CartItemFactoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Inventory\Checker\AvailabilityCheckerInterface;
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
    use ProphecyTrait;

    private ChannelInterface $channel;

    protected function setUp(): void
    {
        $this->channel = $this->prophesize(ChannelInterface::class)->reveal();
    }

    #[Test]
    public function it_throws_not_found_when_the_wishlist_does_not_exist(): void
    {
        $cartContext = $this->prophesize(CartContextInterface::class);
        $cartContext->getCart()->shouldNotBeCalled();

        $action = $this->action(null, cartContext: $cartContext->reveal());

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

        $firstCartItem = $this->prophesize(OrderItemInterface::class);
        $firstCartItem->setVariant($first)->shouldBeCalledOnce();
        $secondCartItem = $this->prophesize(OrderItemInterface::class);
        $secondCartItem->setVariant($second)->shouldBeCalledOnce();

        $cartItemFactory = $this->prophesize(CartItemFactoryInterface::class);
        $cartItemFactory->createNew()->willReturn($firstCartItem->reveal(), $secondCartItem->reveal());

        $quantityModifier = $this->prophesize(OrderItemQuantityModifierInterface::class);
        $quantityModifier->modify($firstCartItem->reveal(), 2)->shouldBeCalledOnce();
        $quantityModifier->modify($secondCartItem->reveal(), 1)->shouldBeCalledOnce();

        $added = [];
        $orderModifier = $this->prophesize(OrderModifierInterface::class);
        $orderModifier
            ->addToOrder($cart, Argument::type(OrderItemInterface::class))
            ->shouldBeCalledTimes(2)
            ->will(static function (array $args) use (&$added): void {
                $added[] = $args[1];
            })
        ;

        $manager = $this->prophesize(EntityManagerInterface::class);
        $manager->persist($cart)->shouldBeCalledOnce();
        $manager->flush()->shouldBeCalledOnce();

        $urlGenerator = $this->prophesize(UrlGeneratorInterface::class);
        $urlGenerator
            ->generate('setono_sylius_wishlist_shop_wishlist_show', ['uuid' => 'uuid'])
            ->shouldBeCalledOnce()
            ->willReturn('/wishlists/uuid')
        ;

        $request = $this->request();

        $response = $this->action(
            $wishlist,
            cart: $cart,
            cartItemFactory: $cartItemFactory->reveal(),
            quantityModifier: $quantityModifier->reveal(),
            orderModifier: $orderModifier->reveal(),
            manager: $manager->reveal(),
            urlGenerator: $urlGenerator->reveal(),
        )($request, 'uuid');

        self::assertSame('/wishlists/uuid', $response->getTargetUrl());
        self::assertSame([$firstCartItem->reveal(), $secondCartItem->reveal()], $added);
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

        $cartItem = $this->prophesize(OrderItemInterface::class);
        $cartItem->setVariant($withVariant)->shouldBeCalledOnce();

        $cartItemFactory = $this->prophesize(CartItemFactoryInterface::class);
        $cartItemFactory->createNew()->shouldBeCalledOnce()->willReturn($cartItem->reveal());

        $orderModifier = $this->prophesize(OrderModifierInterface::class);
        $orderModifier->addToOrder(Argument::cetera())->shouldBeCalledOnce();

        $manager = $this->prophesize(EntityManagerInterface::class);
        $manager->persist(Argument::any())->willReturn();
        $manager->flush()->shouldBeCalledOnce();

        $request = $this->request();

        $this->action(
            $wishlist,
            cartItemFactory: $cartItemFactory->reveal(),
            orderModifier: $orderModifier->reveal(),
            manager: $manager->reveal(),
        )($request, 'uuid');

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

        $orderModifier = $this->prophesize(OrderModifierInterface::class);
        $orderModifier->addToOrder(Argument::cetera())->shouldBeCalledOnce();

        $request = $this->request();

        $this->action($wishlist, orderModifier: $orderModifier->reveal())($request, 'uuid');

        self::assertSame(['success' => ['setono_sylius_wishlist.wishlist_added_to_cart']], $this->flashes($request));
    }

    #[Test]
    public function it_names_the_unavailable_products_instead_of_adding_them(): void
    {
        $unavailableItem = $this->item($this->variant($this->product('T-shirt')), 1);
        $available = $this->variant($this->product('Cap'));

        $wishlist = new UserWishlist();
        $wishlist->addItem($unavailableItem);
        $wishlist->addItem($this->item($available, 1));

        $cartItem = $this->prophesize(OrderItemInterface::class);
        $cartItem->setVariant($available)->shouldBeCalledOnce();

        $cartItemFactory = $this->prophesize(CartItemFactoryInterface::class);
        $cartItemFactory->createNew()->shouldBeCalledOnce()->willReturn($cartItem->reveal());

        $request = $this->request();

        $this->action(
            $wishlist,
            cartItemFactory: $cartItemFactory->reveal(),
            itemAvailabilityChecker: $this->itemAvailabilityChecker($unavailableItem),
        )($request, 'uuid');

        self::assertSame([
            'success' => ['setono_sylius_wishlist.wishlist_added_to_cart'],
            'warning' => [[
                'message' => 'setono_sylius_wishlist.products_unavailable_not_added_to_cart',
                'parameters' => ['%products%' => 'T-shirt'],
            ]],
        ], $this->flashes($request));
    }

    #[Test]
    public function it_names_an_unavailable_product_as_unavailable_even_if_no_variant_is_chosen(): void
    {
        // Choosing a variant doesn't help if the product isn't sold anymore
        $item = $this->productItem($this->product('T-shirt'));

        $wishlist = new UserWishlist();
        $wishlist->addItem($item);

        $request = $this->request();

        $this->action($wishlist, itemAvailabilityChecker: $this->itemAvailabilityChecker($item))($request, 'uuid');

        self::assertSame([
            'warning' => [[
                'message' => 'setono_sylius_wishlist.products_unavailable_not_added_to_cart',
                'parameters' => ['%products%' => 'T-shirt'],
            ]],
        ], $this->flashes($request));
    }

    #[Test]
    public function it_reports_products_without_a_variant_and_unavailable_products_separately(): void
    {
        $unavailableItem = $this->item($this->variant($this->product('Cap')), 1);

        $wishlist = new UserWishlist();
        $wishlist->addItem($this->productItem($this->product('T-shirt')));
        $wishlist->addItem($unavailableItem);

        $request = $this->request();

        $this->action($wishlist, itemAvailabilityChecker: $this->itemAvailabilityChecker($unavailableItem))($request, 'uuid');

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
    public function it_names_the_products_without_enough_stock_instead_of_adding_them(): void
    {
        $outOfStock = $this->variant($this->product('T-shirt'));
        $inStock = $this->variant($this->product('Cap'));

        $wishlist = new UserWishlist();
        $wishlist->addItem($this->item($outOfStock, 2));
        $wishlist->addItem($this->item($inStock, 1));

        $availabilityChecker = $this->prophesize(AvailabilityCheckerInterface::class);
        $availabilityChecker->isStockSufficient(Argument::cetera())->will(
            static fn (array $args): bool => $args[0] === $inStock,
        );

        $cartItem = $this->prophesize(OrderItemInterface::class);
        $cartItem->setVariant($inStock)->shouldBeCalledOnce();

        $cartItemFactory = $this->prophesize(CartItemFactoryInterface::class);
        $cartItemFactory->createNew()->shouldBeCalledOnce()->willReturn($cartItem->reveal());

        $request = $this->request();

        $this->action(
            $wishlist,
            cartItemFactory: $cartItemFactory->reveal(),
            availabilityChecker: $availabilityChecker->reveal(),
        )($request, 'uuid');

        self::assertSame([
            'success' => ['setono_sylius_wishlist.wishlist_added_to_cart'],
            'warning' => [[
                'message' => 'setono_sylius_wishlist.products_out_of_stock_not_added_to_cart',
                'parameters' => ['%products%' => 'T-shirt'],
            ]],
        ], $this->flashes($request));
    }

    #[Test]
    public function it_counts_the_quantity_already_in_the_cart_when_checking_the_stock(): void
    {
        $variant = $this->variant($this->product('T-shirt'));

        $wishlist = new UserWishlist();
        $wishlist->addItem($this->item($variant, 2));

        $sameVariant = $this->prophesize(OrderItemInterface::class);
        $sameVariant->getVariant()->willReturn($variant);
        $sameVariant->getQuantity()->willReturn(3);

        $otherVariant = $this->prophesize(OrderItemInterface::class);
        $otherVariant->getVariant()->willReturn($this->variant($this->product('Cap')));
        $otherVariant->getQuantity()->willReturn(7);

        $availabilityChecker = $this->prophesize(AvailabilityCheckerInterface::class);
        $availabilityChecker->isStockSufficient($variant, 5)->shouldBeCalledOnce()->willReturn(true);

        $this->action(
            $wishlist,
            cart: $this->cart([$otherVariant->reveal(), $sameVariant->reveal()]),
            availabilityChecker: $availabilityChecker->reveal(),
        )($this->request(), 'uuid');
    }

    #[Test]
    public function it_checks_the_stock_for_the_wishlist_quantity_when_the_variant_is_not_in_the_cart(): void
    {
        $variant = $this->variant($this->product('T-shirt'));

        $wishlist = new UserWishlist();
        $wishlist->addItem($this->item($variant, 2));

        $otherVariant = $this->prophesize(OrderItemInterface::class);
        $otherVariant->getVariant()->willReturn($this->variant($this->product('Cap')));
        $otherVariant->getQuantity()->willReturn(7);

        $availabilityChecker = $this->prophesize(AvailabilityCheckerInterface::class);
        $availabilityChecker->isStockSufficient($variant, 2)->shouldBeCalledOnce()->willReturn(true);

        $this->action(
            $wishlist,
            cart: $this->cart([$otherVariant->reveal()]),
            availabilityChecker: $availabilityChecker->reveal(),
        )($this->request(), 'uuid');
    }

    #[Test]
    public function it_requires_the_cart_to_have_a_channel(): void
    {
        $cart = $this->prophesize(OrderInterface::class);
        $cart->getChannel()->willReturn(null);

        $action = $this->action(new UserWishlist(), cart: $cart->reveal());

        $this->expectException(\InvalidArgumentException::class);

        $action($this->request(), 'uuid');
    }

    #[Test]
    public function it_requires_the_cart_to_be_a_core_order(): void
    {
        $cartContext = $this->prophesize(CartContextInterface::class);
        $cartContext->getCart()->willReturn($this->prophesize(BaseOrderInterface::class)->reveal());

        $action = $this->action(new UserWishlist(), cartContext: $cartContext->reveal());

        $this->expectException(\InvalidArgumentException::class);

        $action($this->request(), 'uuid');
    }

    #[Test]
    public function it_neither_saves_the_cart_nor_reports_success_when_no_item_has_a_variant(): void
    {
        $wishlist = new UserWishlist();
        $wishlist->addItem($this->productItem($this->product('T-shirt')));

        $orderModifier = $this->prophesize(OrderModifierInterface::class);
        $orderModifier->addToOrder(Argument::cetera())->shouldNotBeCalled();

        $managerRegistry = $this->prophesize(ManagerRegistry::class);
        $managerRegistry->getManagerForClass(Argument::cetera())->shouldNotBeCalled();

        $request = $this->request();

        $this->action($wishlist, orderModifier: $orderModifier->reveal(), managerRegistry: $managerRegistry->reveal())($request, 'uuid');

        self::assertSame(['warning'], array_keys($this->flashes($request)));
    }

    #[Test]
    public function it_does_nothing_for_an_empty_wishlist(): void
    {
        $managerRegistry = $this->prophesize(ManagerRegistry::class);
        $managerRegistry->getManagerForClass(Argument::cetera())->shouldNotBeCalled();

        $request = $this->request();

        $this->action(new UserWishlist(), managerRegistry: $managerRegistry->reveal())($request, 'uuid');

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
        ?AvailabilityCheckerInterface $availabilityChecker = null,
        ?WishlistItemAvailabilityCheckerInterface $itemAvailabilityChecker = null,
    ): AddWishlistToCartAction {
        $repository = $this->prophesize(WishlistRepositoryInterface::class);
        $repository->findOneByUuid(Argument::any())->willReturn($wishlist);

        if (null === $cartContext) {
            $cartContextProphecy = $this->prophesize(CartContextInterface::class);
            $cartContextProphecy->getCart()->willReturn($cart ?? $this->cart());
            $cartContext = $cartContextProphecy->reveal();
        }

        if (null === $cartItemFactory) {
            // The callback is static, because Prophecy binds a non-static one to the double
            $test = $this;
            $cartItemFactoryProphecy = $this->prophesize(CartItemFactoryInterface::class);
            $cartItemFactoryProphecy->createNew()->will(
                static fn (): OrderItemInterface => $test->cartItem(),
            );
            $cartItemFactory = $cartItemFactoryProphecy->reveal();
        }

        if (null === $managerRegistry) {
            $managerRegistryProphecy = $this->prophesize(ManagerRegistry::class);
            $managerRegistryProphecy->getManagerForClass(Argument::any())->willReturn($manager ?? $this->prophesize(EntityManagerInterface::class)->reveal());
            $managerRegistry = $managerRegistryProphecy->reveal();
        }

        if (null === $urlGenerator) {
            $urlGeneratorProphecy = $this->prophesize(UrlGeneratorInterface::class);
            $urlGeneratorProphecy->generate(Argument::cetera())->willReturn('/wishlists/uuid');
            $urlGenerator = $urlGeneratorProphecy->reveal();
        }

        if (null === $availabilityChecker) {
            $availabilityCheckerProphecy = $this->prophesize(AvailabilityCheckerInterface::class);
            $availabilityCheckerProphecy->isStockSufficient(Argument::cetera())->willReturn(true);
            $availabilityChecker = $availabilityCheckerProphecy->reveal();
        }

        return new AddWishlistToCartAction(
            $repository->reveal(),
            $quantityModifier ?? $this->prophesize(OrderItemQuantityModifierInterface::class)->reveal(),
            $orderModifier ?? $this->prophesize(OrderModifierInterface::class)->reveal(),
            $cartContext,
            $urlGenerator,
            $cartItemFactory,
            $managerRegistry,
            $availabilityChecker,
            $itemAvailabilityChecker ?? $this->itemAvailabilityChecker(),
        );
    }

    /**
     * Every item is available in the cart's channel, except the given ones
     */
    private function itemAvailabilityChecker(WishlistItemInterface ...$unavailableItems): WishlistItemAvailabilityCheckerInterface
    {
        // The callback is static, because Prophecy binds a non-static one to the double
        $channel = $this->channel;

        $checker = $this->prophesize(WishlistItemAvailabilityCheckerInterface::class);
        $checker->isAvailable(Argument::cetera())->will(
            static fn (array $args): bool => $args[1] === $channel && !in_array($args[0], $unavailableItems, true),
        );

        return $checker->reveal();
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

    private function cartItem(): OrderItemInterface
    {
        return $this->prophesize(OrderItemInterface::class)->reveal();
    }

    /**
     * @param list<OrderItemInterface> $items
     */
    private function cart(array $items = []): OrderInterface
    {
        $cart = $this->prophesize(OrderInterface::class);
        $cart->getChannel()->willReturn($this->channel);
        $cart->getItems()->willReturn(new ArrayCollection($items));

        return $cart->reveal();
    }

    private function product(?string $name, ?string $code = null): ProductInterface
    {
        $product = $this->prophesize(ProductInterface::class);
        $product->getName()->willReturn($name);
        $product->getCode()->willReturn($code);

        return $product->reveal();
    }

    private function variant(ProductInterface $product): ProductVariantInterface
    {
        $variant = $this->prophesize(ProductVariantInterface::class);
        $variant->getProduct()->willReturn($product);

        return $variant->reveal();
    }
}
