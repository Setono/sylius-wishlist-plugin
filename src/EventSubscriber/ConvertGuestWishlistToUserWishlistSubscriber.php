<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\EventSubscriber;

use Doctrine\Persistence\ManagerRegistry;
use Setono\Doctrine\ORMTrait;
use Setono\SyliusWishlistPlugin\Factory\WishlistFactoryInterface;
use Setono\SyliusWishlistPlugin\Factory\WishlistItemFactoryInterface;
use Setono\SyliusWishlistPlugin\Model\GuestWishlistInterface;
use Setono\SyliusWishlistPlugin\Model\UserWishlistInterface;
use Setono\SyliusWishlistPlugin\Model\WishlistItemInterface;
use Setono\SyliusWishlistPlugin\Provider\WishlistProviderInterface;
use Setono\SyliusWishlistPlugin\Repository\UserWishlistRepositoryInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * When the user logs in, we copy the items of the guest wishlist onto the user's wishlist and remove the guest wishlist.
 *
 * Doctrine can't change the type (i.e. the discriminator) of an existing entity, so the guest wishlist can't
 * simply be turned into a user wishlist
 */
final class ConvertGuestWishlistToUserWishlistSubscriber implements EventSubscriberInterface
{
    use ORMTrait;

    public function __construct(
        private readonly WishlistProviderInterface $guestWishlistProvider,
        private readonly UserWishlistRepositoryInterface $userWishlistRepository,
        private readonly WishlistFactoryInterface $wishlistFactory,
        private readonly WishlistItemFactoryInterface $wishlistItemFactory,
        ManagerRegistry $managerRegistry,
    ) {
        $this->managerRegistry = $managerRegistry;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        // Only customers have wishlists. Administrators log in through the same event, and since the user wishlists
        // are looked up by id, the guest items would otherwise go to the customer that shares the administrator's id
        if (!$user instanceof ShopUserInterface) {
            return;
        }

        $manager = null;
        $userWishlist = null;

        foreach ($this->guestWishlistProvider->getWishlists() as $guestWishlist) {
            if (!$guestWishlist instanceof GuestWishlistInterface) {
                continue;
            }

            if ($guestWishlist->hasItems()) {
                $userWishlist ??= $this->getUserWishlist($user);

                foreach ($guestWishlist->getItems() as $item) {
                    $this->copyItem($item, $userWishlist);
                }
            }

            // The guest wishlist is tied to the browser, not the user. If we kept it, the next anonymous visitor
            // (or another customer) on this browser would get the items that now belong to this user
            $manager = $this->getManager($guestWishlist);
            $manager->remove($guestWishlist);
        }

        $manager?->flush();
    }

    private function getUserWishlist(ShopUserInterface $user): UserWishlistInterface
    {
        $userWishlists = $this->userWishlistRepository->findByUser($user);
        if ([] !== $userWishlists) {
            return $userWishlists[0];
        }

        $userWishlist = $this->wishlistFactory->createForUser($user);
        $this->getManager($userWishlist)->persist($userWishlist);

        return $userWishlist;
    }

    /**
     * Items already on the user wishlist are left as they are, the same way adding a product twice is a no-op
     */
    private function copyItem(WishlistItemInterface $item, UserWishlistInterface $userWishlist): void
    {
        $product = $item->getProduct();
        $variant = $item->getVariant();

        if (null !== $variant) {
            if ($userWishlist->hasProductVariant($variant)) {
                return;
            }
        } elseif (null === $product || $userWishlist->hasProduct($product)) {
            return;
        }

        $copy = $this->wishlistItemFactory->createNew();
        $copy->setProduct($product);
        $copy->setVariant($variant);
        $copy->setQuantity($item->getQuantity());
        $copy->setNote($item->getNote());

        $userWishlist->addItem($copy);
    }
}
