<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Controller;

use Doctrine\Persistence\ManagerRegistry;
use Setono\Doctrine\ORMTrait;
use Setono\SyliusWishlistPlugin\Controller\Command\SelectWishlistsCommand;
use Setono\SyliusWishlistPlugin\Factory\WishlistItemFactoryInterface;
use Setono\SyliusWishlistPlugin\Form\Type\SelectWishlistsType;
use Setono\SyliusWishlistPlugin\Form\Type\WishlistType;
use Setono\SyliusWishlistPlugin\Model\WishlistInterface;
use Setono\SyliusWishlistPlugin\Provider\WishlistProviderInterface;
use Setono\SyliusWishlistPlugin\Repository\WishlistRepositoryInterface;
use Sylius\Component\Core\Factory\CartItemFactoryInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Sylius\Component\Order\Modifier\OrderItemQuantityModifierInterface;
use Sylius\Component\Order\Modifier\OrderModifierInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Environment;
use Webmozart\Assert\Assert;

final class WishlistController
{
    use ORMTrait;

    public function __construct(
        ManagerRegistry $managerRegistry,
        private readonly Environment $twig,
        private readonly CartItemFactoryInterface $cartItemFactory,
        /** @var class-string<ProductInterface> $productClass */
        private readonly string $productClass,
        /** @var class-string<WishlistInterface> $wishlistClass */
        private readonly string $wishlistClass,
    ) {
        $this->managerRegistry = $managerRegistry;
    }

    public function index(WishlistProviderInterface $wishlistProvider): Response
    {
        return new Response($this->twig->render('@SetonoSyliusWishlistPlugin/shop/wishlist/index.html.twig', [
            'wishlists' => $wishlistProvider->getWishlists(),
        ]));
    }

    public function show(
        Request $request,
        WishlistRepositoryInterface $wishlistRepository,
        FormFactoryInterface $formFactory,
        string $uuid,
    ): Response {
        $wishlist = $wishlistRepository->findOneByUuid($uuid);

        if (null === $wishlist) {
            throw new NotFoundHttpException(sprintf('Wishlist with id %s not found', $uuid));
        }

        $form = $formFactory->create(WishlistType::class, $wishlist);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $wishlistRepository->add($wishlist);
        }

        return new Response($this->twig->render('@SetonoSyliusWishlistPlugin/shop/wishlist/show.html.twig', [
            'wishlist' => $wishlist,
            'form' => $form->createView(),
        ]));
    }

    public function add(
        WishlistProviderInterface $wishlistProvider,
        WishlistItemFactoryInterface $wishlistItemFactory,
        FormFactoryInterface $formFactory,
        int $product,
    ): JsonResponse {
        $productEntity = $this->getProduct($product);

        $wishlistItem = $wishlistItemFactory->createWithProduct($productEntity);

        $preSelectedWishlists = $wishlistProvider->getPreSelectedWishlists();
        foreach ($preSelectedWishlists as $wishlist) {
            $manager = $this->getManager($wishlist);
            $manager->persist($wishlist);

            $wishlist->addItem($wishlistItem);
        }

        $manager->flush();

        $form = $formFactory->create(
            SelectWishlistsType::class,
            new SelectWishlistsCommand($wishlistProvider->getWishlists()),
            [
                'selected' => $preSelectedWishlists,
            ],
        );

        return new JsonResponse([
            'toggleButton' => $this->twig->render(
                '@SetonoSyliusWishlistPlugin/shop/wishlist/_toggle_button.html.twig',
                [
                    'product' => $productEntity,
                ],
            ),
            'selectWishlistsForm' => $this->twig->render(
                '@SetonoSyliusWishlistPlugin/shop/wishlist/_select_wishlists.html.twig',
                [
                    'product' => $productEntity,
                    'form' => $form->createView(),
                ],
            ),
        ]);
    }

    public function remove(
        Request $request,
        WishlistProviderInterface $wishlistProvider,
        int $product,
        int $wishlist = null,
    ): Response {
        $productEntity = $this->getProduct($product);

        foreach ($wishlistProvider->getWishlists() as $wishlistEntity) {
            if (null !== $wishlist && $wishlistEntity->getId() !== $wishlist) {
                continue;
            }

            $wishlistEntity->removeProduct($productEntity);
        }

        $this->getManager($productEntity)->flush();

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'toggleButton' => $this->twig->render(
                    '@SetonoSyliusWishlistPlugin/shop/wishlist/_toggle_button.html.twig',
                    [
                        'product' => $productEntity,
                    ],
                ),
            ]);
        }

        return new RedirectResponse($request->headers->get('referer'));
    }

    public function selectWishlists(Request $request, int $product): Response
    {
        return new Response('Not implemented', Response::HTTP_NOT_IMPLEMENTED);
    }

    public function addToCart(
        Request $request,
        WishlistRepositoryInterface $wishlistRepository,
        OrderItemQuantityModifierInterface $orderItemQuantityModifier,
        OrderModifierInterface $orderModifier,
        CartContextInterface $cartContext,
        string $uuid,
    ): Response {
        $wishlist = $wishlistRepository->findOneByUuid($uuid);
        if (null === $wishlist) {
            throw new NotFoundHttpException(sprintf('Wishlist with id %s not found', $uuid));
        }

        try {
            $cart = $cartContext->getCart();
            Assert::isInstanceOf($cart, OrderInterface::class);

            foreach ($wishlist->getItems() as $item) {
                if ($item->getVariant() === null) {
                    throw new \RuntimeException(sprintf('You have not selected a variant for the product "%s"', $item->getProduct()?->getName()));
                }

                $cartItem = $this->cartItemFactory->createForCart($cart);
                $cartItem->setVariant($item->getVariant());

                $orderItemQuantityModifier->modify($cartItem, $item->getQuantity());

                $orderModifier->addToOrder($cart, $cartItem);
            }

            $this->getManager($cart)->persist($cart);
            $this->getManager($cart)->flush();
        } catch (\Throwable $e) {
            $session = $request->getSession();
            if ($session instanceof FlashBagAwareSessionInterface) {
                $session->getFlashBag()->add('error', $e->getMessage());
            }
        }

        return new RedirectResponse($request->headers->get('referer'));
    }

    private function getProduct(int $id): ProductInterface
    {
        $product = $this->getManager($this->productClass)->find($this->productClass, $id);

        if (null === $product) {
            throw new NotFoundHttpException(sprintf('Product with id %s not found', $id));
        }

        return $product;
    }
}
