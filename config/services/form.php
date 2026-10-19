<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Setono\SyliusWishlistPlugin\Form\Type\WishlistItemType;
use Setono\SyliusWishlistPlugin\Form\Type\WishlistType;

return static function (ContainerConfigurator $container): void {
    $container->parameters()
        ->set('setono_sylius_wishlist.form.type.wishlist.validation_groups', ['setono_sylius_wishlist'])
        ->set('setono_sylius_wishlist.form.type.wishlist_item.validation_groups', ['setono_sylius_wishlist'])
    ;

    $services = $container->services();

    $services->set(WishlistType::class)
        ->args([
            param('setono_sylius_wishlist.model.wishlist.class'),
            param('setono_sylius_wishlist.form.type.wishlist.validation_groups'),
        ])
        ->tag('form.type')
    ;

    $services->set(WishlistItemType::class)
        ->args([
            param('setono_sylius_wishlist.model.wishlist_item.class'),
            param('setono_sylius_wishlist.form.type.wishlist_item.validation_groups'),
        ])
        ->tag('form.type')
    ;
};
