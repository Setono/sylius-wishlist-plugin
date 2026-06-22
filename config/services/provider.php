<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Setono\SyliusWishlistPlugin\Provider\CachedWishlistProvider;
use Setono\SyliusWishlistPlugin\Provider\CompositeWishlistProvider;
use Setono\SyliusWishlistPlugin\Provider\GuestWishlistProvider;
use Setono\SyliusWishlistPlugin\Provider\UserWishlistProvider;
use Setono\SyliusWishlistPlugin\Provider\WishlistProviderInterface;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->alias(WishlistProviderInterface::class, CompositeWishlistProvider::class);

    $services->set(CompositeWishlistProvider::class);

    $services->set(UserWishlistProvider::class)
        ->args([
            service('security.helper'),
            service('setono_sylius_wishlist.repository.user_wishlist'),
        ])
        ->tag('setono_sylius_wishlist.wishlist_provider', ['priority' => -40])
    ;

    $services->set(GuestWishlistProvider::class)
        ->args([
            service('setono_client.client_context.default'),
            service('setono_sylius_wishlist.repository.guest_wishlist'),
        ])
        ->tag('setono_sylius_wishlist.wishlist_provider', ['priority' => -50])
    ;

    $services->set(CachedWishlistProvider::class)
        ->decorate(WishlistProviderInterface::class, null, 64)
        ->args([
            service('.inner'),
        ])
    ;
};
