<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Controller;

use Doctrine\Persistence\ManagerRegistry;
use Setono\Doctrine\ORMTrait;
use Setono\SyliusWishlistPlugin\Provider\WishlistProviderInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

/**
 * This controller action is responsible for removing a product or product variant from the wishlist
 */
final class RemoveFromWishlistAction
{
    use ORMTrait;

    public function __construct(
        private readonly WishlistProviderInterface $wishlistProvider,
        private readonly Environment $twig,
        ManagerRegistry $managerRegistry,
        private readonly UrlGeneratorInterface $urlGenerator,
        /** @var class-string<ProductInterface|ProductVariantInterface> $className */
        private readonly string $className,
    ) {
        $this->managerRegistry = $managerRegistry;
    }

    public function __invoke(Request $request, int $id, int $wishlist = null): Response
    {
        $entity = $this->getManager($this->className)->find($this->className, $id);

        if (null === $entity) {
            throw new NotFoundHttpException(sprintf('Product with id %s not found', $id));
        }

        foreach ($this->wishlistProvider->getWishlists() as $wishlistEntity) {
            if (null !== $wishlist && $wishlistEntity->getId() !== $wishlist) {
                continue;
            }

            $entity instanceof ProductInterface ? $wishlistEntity->removeProduct($entity) : $wishlistEntity->removeProductVariant($entity);
        }

        $this->getManager($entity)->flush();

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'toggleButton' => $this->twig->render(
                    '@SetonoSyliusWishlistPlugin/shop/wishlist/_toggle_button.html.twig',
                    [
                        'product' => $entity instanceof ProductInterface ? $entity : $entity->getProduct(),
                        'productVariant' => $entity instanceof ProductVariantInterface ? $entity : null,
                    ],
                ),
            ]);
        }

        return new RedirectResponse($this->getRedirectUrl($request));
    }

    private function getRedirectUrl(Request $request): string
    {
        return $request->headers->get('referer') ?? $this->urlGenerator->generate('setono_sylius_wishlist_shop_wishlist_index');
    }
}
