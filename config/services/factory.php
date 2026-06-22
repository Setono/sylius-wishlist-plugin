<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Setono\SyliusWishlistPlugin\Factory\WishlistFactory;
use Setono\SyliusWishlistPlugin\Factory\WishlistFactoryInterface;
use Setono\SyliusWishlistPlugin\Factory\WishlistItemFactory;
use Setono\SyliusWishlistPlugin\Factory\WishlistItemFactoryInterface;
use Symfony\Bundle\SecurityBundle\Security;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->alias(WishlistFactoryInterface::class, WishlistFactory::class);

    $services->set(WishlistFactory::class)
        ->decorate('setono_sylius_wishlist.factory.wishlist', null, 64)
        ->args([
            service(Security::class),
            service('setono_client.client_context.default'),
            service('translator'),
            param('setono_sylius_wishlist.model.guest_wishlist.class'),
            param('setono_sylius_wishlist.model.user_wishlist.class'),
        ])
    ;

    $services->alias(WishlistItemFactoryInterface::class, WishlistItemFactory::class);

    $services->set(WishlistItemFactory::class)
        ->decorate('setono_sylius_wishlist.factory.wishlist_item', null, 64)
        ->args([
            service('.inner'),
        ])
    ;
};
