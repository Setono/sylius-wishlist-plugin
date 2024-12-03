<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Controller;

use Doctrine\Persistence\ManagerRegistry;
use Setono\Doctrine\ORMTrait;
use Setono\SyliusWishlistPlugin\Model\UserWishlistInterface;
use Setono\SyliusWishlistPlugin\Model\WishlistInterface;
use Setono\SyliusWishlistPlugin\Provider\WishlistProviderInterface;
use Setono\SyliusWishlistPlugin\Security\Voter\WishlistVoter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class RemoveWishlistItemAction
{
    use ORMTrait;

    public function __construct(
        private readonly WishlistProviderInterface $wishlistProvider,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly Security $security,
        ManagerRegistry $managerRegistry,
        /** @var class-string<UserWishlistInterface> $userWishlistClass */
        private readonly string $userWishlistClass,
    ) {
        $this->managerRegistry = $managerRegistry;
    }

    public function __invoke(string $uuid, int $id): RedirectResponse
    {
        $manager = $this->getManager($this->userWishlistClass);

        /** @var UserWishlistInterface|null $wishlist */
        $wishlist = $manager->createQueryBuilder()
            ->select('o')
            ->from($this->userWishlistClass, 'o')
            ->andWhere('o.uuid = :uuid')
            ->setParameter('uuid', $uuid)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        if (null === $wishlist) {
            throw new NotFoundHttpException(sprintf('Wishlist with uuid %s not found', $uuid));
        }

        if (!$this->security->isGranted(WishlistVoter::EDIT, $wishlist)) {
            throw new NotFoundHttpException(sprintf('Wishlist with uuid %s not found', $uuid));
        }

        $wishlist->removeItem($id);

        $manager->flush();

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
