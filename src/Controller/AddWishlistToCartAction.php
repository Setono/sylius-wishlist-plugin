<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Controller;

use Doctrine\Persistence\ManagerRegistry;
use Setono\Doctrine\ORMTrait;
use Setono\SyliusWishlistPlugin\Model\WishlistInterface;
use Setono\SyliusWishlistPlugin\Repository\WishlistRepositoryInterface;
use Sylius\Component\Core\Factory\CartItemFactoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Inventory\Checker\AvailabilityCheckerInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Sylius\Component\Order\Modifier\OrderItemQuantityModifierInterface;
use Sylius\Component\Order\Modifier\OrderModifierInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Webmozart\Assert\Assert;

// todo Right now we are not checking if the product is available.
// todo We should probably copy the logic from the \Sylius\Bundle\OrderBundle\Controller\OrderItemController::addAction
final class AddWishlistToCartAction
{
    use ORMTrait;

    /**
     * @param WishlistRepositoryInterface<WishlistInterface> $wishlistRepository
     * @param CartItemFactoryInterface<OrderItemInterface> $cartItemFactory
     */
    public function __construct(
        private readonly WishlistRepositoryInterface $wishlistRepository,
        private readonly OrderItemQuantityModifierInterface $orderItemQuantityModifier,
        private readonly OrderModifierInterface $orderModifier,
        private readonly CartContextInterface $cartContext,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly CartItemFactoryInterface $cartItemFactory,
        ManagerRegistry $managerRegistry,
        private readonly AvailabilityCheckerInterface $availabilityChecker,
    ) {
        $this->managerRegistry = $managerRegistry;
    }

    public function __invoke(Request $request, string $uuid): RedirectResponse
    {
        $wishlist = $this->wishlistRepository->findOneByUuid($uuid);
        if (null === $wishlist) {
            throw new NotFoundHttpException(sprintf('Wishlist with id %s not found', $uuid));
        }

        $cart = $this->cartContext->getCart();
        Assert::isInstanceOf($cart, OrderInterface::class);

        $channel = $cart->getChannel();
        Assert::isInstanceOf($channel, ChannelInterface::class);

        $added = false;
        $productsWithoutVariant = [];
        $unavailableProducts = [];
        $productsOutOfStock = [];

        // Instead of failing the whole wishlist on an item that can't go into the cart, we add everything else
        // and tell the visitor which products weren't added and why
        foreach ($wishlist->getItems() as $item) {
            $product = $item->getProduct();
            if (null === $product) {
                continue;
            }

            // A configurable product can be put on the wishlist before a variant is chosen
            $variant = $item->getVariant();
            if (null === $variant) {
                $productsWithoutVariant[] = self::getName($product);

                continue;
            }

            if (!self::isAvailable($product, $variant, $channel)) {
                $unavailableProducts[] = self::getName($product);

                continue;
            }

            // Like Sylius' own add to cart, the quantity already in the cart counts too
            if (!$this->availabilityChecker->isStockSufficient($variant, $item->getQuantity() + self::getQuantityInCart($cart, $variant))) {
                $productsOutOfStock[] = self::getName($product);

                continue;
            }

            /** @var OrderItemInterface $cartItem */
            $cartItem = $this->cartItemFactory->createNew();
            $cartItem->setVariant($variant);

            $this->orderItemQuantityModifier->modify($cartItem, $item->getQuantity());

            $this->orderModifier->addToOrder($cart, $cartItem);

            $added = true;
        }

        if ($added) {
            $manager = $this->getManager($cart);
            $manager->persist($cart);
            $manager->flush();

            $this->addFlash($request, 'success', 'setono_sylius_wishlist.wishlist_added_to_cart');
        }

        $this->addProductsFlash($request, 'setono_sylius_wishlist.products_without_variant_not_added_to_cart', $productsWithoutVariant);
        $this->addProductsFlash($request, 'setono_sylius_wishlist.products_unavailable_not_added_to_cart', $unavailableProducts);
        $this->addProductsFlash($request, 'setono_sylius_wishlist.products_out_of_stock_not_added_to_cart', $productsOutOfStock);

        return new RedirectResponse($this->urlGenerator->generate('setono_sylius_wishlist_shop_wishlist_show', [
            'uuid' => $uuid,
        ]));
    }

    /**
     * Wishlist items can stay around long after the product stopped being sold. Sylius can't price a variant without
     * a price in the channel (adding it to the cart would throw), and disabled products can't be bought
     */
    private static function isAvailable(ProductInterface $product, ProductVariantInterface $variant, ChannelInterface $channel): bool
    {
        return $product->isEnabled() &&
            $product->hasChannel($channel) &&
            $variant->isEnabled() &&
            null !== $variant->getChannelPricingForChannel($channel)?->getPrice()
        ;
    }

    /**
     * Adding a variant that is already in the cart increases the quantity of its cart item, so there is at most one
     */
    private static function getQuantityInCart(OrderInterface $cart, ProductVariantInterface $variant): int
    {
        foreach ($cart->getItems() as $cartItem) {
            if ($cartItem->getVariant() === $variant) {
                return $cartItem->getQuantity();
            }
        }

        return 0;
    }

    /**
     * The name is missing if the product isn't translated into the current locale
     */
    private static function getName(ProductInterface $product): ?string
    {
        return $product->getName() ?? $product->getCode();
    }

    /**
     * @param list<string|null> $products
     */
    private function addProductsFlash(Request $request, string $message, array $products): void
    {
        if ([] === $products) {
            return;
        }

        $this->addFlash($request, 'warning', [
            'message' => $message,
            'parameters' => ['%products%' => implode(', ', array_unique($products))],
        ]);
    }

    /**
     * @param string|array{message: string, parameters: array<string, string>} $message
     */
    private function addFlash(Request $request, string $type, string|array $message): void
    {
        $session = $request->getSession();
        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add($type, $message);
        }
    }
}
