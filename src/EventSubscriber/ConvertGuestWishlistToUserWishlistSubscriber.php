<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\EventSubscriber;

use Doctrine\Persistence\ManagerRegistry;
use Setono\Doctrine\ORMTrait;
use Setono\SyliusWishlistPlugin\Model\GuestWishlistInterface;
use Setono\SyliusWishlistPlugin\Provider\WishlistProviderInterface;
use Sylius\Component\User\Model\UserInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Webmozart\Assert\Assert;

/**
 * When the user logs in, we want to convert the guest wishlist to a user wishlist
 */
final class ConvertGuestWishlistToUserWishlistSubscriber implements EventSubscriberInterface
{
    use ORMTrait;

    public function __construct(
        private readonly WishlistProviderInterface $guestWishlistProvider,
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
        Assert::isInstanceOf($user, UserInterface::class);

        foreach ($this->guestWishlistProvider->getWishlists() as $guestWishlist) {
            if (!$guestWishlist instanceof GuestWishlistInterface || !$guestWishlist->hasItems()) {
                continue;
            }

            $guestWishlist->convertToUserWishlist($user);

            $this->getManager($guestWishlist)->flush();
        }
    }
}
