<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Setono\SyliusWishlistPlugin\EventSubscriber\ConvertGuestWishlistToUserWishlistSubscriber;
use Setono\SyliusWishlistPlugin\Factory\WishlistFactoryInterface;
use Setono\SyliusWishlistPlugin\Factory\WishlistItemFactoryInterface;
use Setono\SyliusWishlistPlugin\Provider\GuestWishlistProvider;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(ConvertGuestWishlistToUserWishlistSubscriber::class)
        ->args([
            // Not the composite provider: the user is already logged in when LoginSuccessEvent fires,
            // so it would return the user's wishlists instead of the guest wishlist
            service(GuestWishlistProvider::class),
            service('setono_sylius_wishlist.repository.user_wishlist'),
            service(WishlistFactoryInterface::class),
            service(WishlistItemFactoryInterface::class),
            service('doctrine'),
        ])
        ->tag('kernel.event_subscriber')
    ;
};
