<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Controller;

use Doctrine\Persistence\ManagerRegistry;
use Setono\Doctrine\ORMTrait;
use Setono\SyliusWishlistPlugin\Repository\WishlistRepositoryInterface;
use Sylius\Component\Core\Factory\CartItemFactoryInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Sylius\Component\Order\Modifier\OrderItemQuantityModifierInterface;
use Sylius\Component\Order\Modifier\OrderModifierInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Webmozart\Assert\Assert;

final class AddWishlistToCartAction
{
    use ORMTrait;

    public function __construct(
        private readonly WishlistRepositoryInterface $wishlistRepository,
        private readonly OrderItemQuantityModifierInterface $orderItemQuantityModifier,
        private readonly OrderModifierInterface $orderModifier,
        private readonly CartContextInterface $cartContext,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly CartItemFactoryInterface $cartItemFactory,
        ManagerRegistry $managerRegistry,
    ) {
        $this->managerRegistry = $managerRegistry;
    }

    public function __invoke(Request $request, string $uuid): RedirectResponse
    {
        $wishlist = $this->wishlistRepository->findOneByUuid($uuid);
        if (null === $wishlist) {
            throw new NotFoundHttpException(sprintf('Wishlist with id %s not found', $uuid));
        }

        try {
            $cart = $this->cartContext->getCart();
            Assert::isInstanceOf($cart, OrderInterface::class);

            foreach ($wishlist->getItems() as $item) {
                if ($item->getVariant() === null) {
                    throw new \RuntimeException(sprintf(
                        'You have not selected a variant for the product "%s"',
                        (string) $item->getProduct()?->getName(),
                    ));
                }

                $cartItem = $this->cartItemFactory->createForCart($cart);
                $cartItem->setVariant($item->getVariant());

                $this->orderItemQuantityModifier->modify($cartItem, $item->getQuantity());

                $this->orderModifier->addToOrder($cart, $cartItem);
            }

            $this->getManager($cart)->persist($cart);
            $this->getManager($cart)->flush();
        } catch (\Throwable $e) {
            $session = $request->getSession();
            if ($session instanceof FlashBagAwareSessionInterface) {
                $session->getFlashBag()->add('error', $e->getMessage());
            }
        }

        return new RedirectResponse($this->urlGenerator->generate('setono_sylius_wishlist_shop_wishlist_show', [
            'uuid' => $uuid,
        ]));
    }
}
