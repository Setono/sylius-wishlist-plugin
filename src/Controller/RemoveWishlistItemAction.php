<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Controller;

use Doctrine\Persistence\ManagerRegistry;
use Setono\Doctrine\ORMTrait;
use Setono\SyliusWishlistPlugin\Model\WishlistInterface;
use Setono\SyliusWishlistPlugin\Provider\WishlistProviderInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class RemoveWishlistItemAction
{
    use ORMTrait;

    public function __construct(
        private readonly WishlistProviderInterface $wishlistProvider,
        private readonly UrlGeneratorInterface $urlGenerator,
        ManagerRegistry $managerRegistry,
    ) {
        $this->managerRegistry = $managerRegistry;
    }

    public function __invoke(string $uuid, int $id): RedirectResponse
    {
        $wishlist = $this->getWishlist($uuid);

        // todo soooo ugly
        foreach ($wishlist->getItems() as $item) {
            if ($item->getId() === $id) {
                $wishlist->removeItem($item);

                break;
            }
        }

        $this->getManager($wishlist)->flush();

        return new RedirectResponse($this->urlGenerator->generate('setono_sylius_wishlist_shop_wishlist_show', [
            'uuid' => $uuid,
        ]));
    }

    private function getWishlist(string $uuid): WishlistInterface
    {
        // todo optimize this
        foreach ($this->wishlistProvider->getWishlists() as $wishlist) {
            if ($wishlist->getUuid() === $uuid) {
                return $wishlist;
            }
        }

        throw new NotFoundHttpException(sprintf('Wishlist with uuid %s not found', $uuid));
    }
}
