<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Setono\SyliusWishlistPlugin\Controller\AddToWishlistAction;
use Setono\SyliusWishlistPlugin\Controller\AddWishlistToCartAction;
use Setono\SyliusWishlistPlugin\Controller\FirstWishlistRedirectAction;
use Setono\SyliusWishlistPlugin\Controller\RemoveFromWishlistAction;
use Setono\SyliusWishlistPlugin\Controller\RemoveWishlistItemAction;
use Setono\SyliusWishlistPlugin\Controller\ShowWishlistAction;
use Setono\SyliusWishlistPlugin\Controller\WishlistController;
use Setono\SyliusWishlistPlugin\Controller\WishlistIndexAction;
use Setono\SyliusWishlistPlugin\Factory\WishlistFactoryInterface;
use Setono\SyliusWishlistPlugin\Factory\WishlistItemFactoryInterface;
use Setono\SyliusWishlistPlugin\Provider\WishlistProviderInterface;
use Sylius\Component\Order\Modifier\OrderItemQuantityModifierInterface;
use Sylius\Component\Order\Modifier\OrderModifierInterface;
use Symfony\Bundle\SecurityBundle\Security;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(WishlistController::class)
        ->args([
            service('doctrine'),
        ])
        ->tag('controller.service_arguments')
    ;

    $services->set(WishlistIndexAction::class)
        ->public()
        ->args([
            service('twig'),
            service(WishlistProviderInterface::class),
        ])
    ;

    $services->set(ShowWishlistAction::class)
        ->public()
        ->args([
            service('setono_sylius_wishlist.repository.wishlist'),
            service('form.factory'),
            service('security.authorization_checker'),
            service('twig'),
            service('router'),
        ])
    ;

    $services->set(FirstWishlistRedirectAction::class)
        ->public()
        ->args([
            service(WishlistProviderInterface::class),
            service('router'),
        ])
    ;

    $services->set(RemoveWishlistItemAction::class)
        ->public()
        ->args([
            service('router'),
            service(Security::class),
            service('doctrine'),
            param('setono_sylius_wishlist.model.wishlist.class'),
        ])
    ;

    $services->set(AddWishlistToCartAction::class)
        ->public()
        ->args([
            service('setono_sylius_wishlist.repository.wishlist'),
            service(OrderItemQuantityModifierInterface::class),
            service(OrderModifierInterface::class),
            service('sylius.context.cart'),
            service('router'),
            service('sylius.factory.order_item'),
            service('doctrine'),
        ])
    ;

    // Add product/variant to wishlist. The abstract parent uses the FQCN as its id; the two concrete
    // children keep their snake-cased ids because they are referenced by name in config/routes/shop.yaml
    // (and a single class registered twice cannot share one FQCN id).
    $services->set(AddToWishlistAction::class)
        ->abstract()
        ->public()
        ->args([
            service(WishlistProviderInterface::class),
            service(WishlistItemFactoryInterface::class),
            service('doctrine'),
            service(WishlistFactoryInterface::class),
            service('router'),
        ])
    ;

    $services->set('setono_sylius_wishlist.controller.add_product_to_wishlist')
        ->parent(AddToWishlistAction::class)
        ->public()
        ->arg('$className', param('sylius.model.product.class'))
    ;

    $services->set('setono_sylius_wishlist.controller.add_product_variant_to_wishlist')
        ->parent(AddToWishlistAction::class)
        ->public()
        ->arg('$className', param('sylius.model.product_variant.class'))
    ;

    // Remove product/variant from wishlist (same pattern as above)
    $services->set(RemoveFromWishlistAction::class)
        ->abstract()
        ->public()
        ->args([
            service(WishlistProviderInterface::class),
            service('doctrine'),
            service('router'),
        ])
    ;

    $services->set('setono_sylius_wishlist.controller.remove_product_from_wishlist')
        ->parent(RemoveFromWishlistAction::class)
        ->public()
        ->arg('$className', param('sylius.model.product.class'))
    ;

    $services->set('setono_sylius_wishlist.controller.remove_product_variant_from_wishlist')
        ->parent(RemoveFromWishlistAction::class)
        ->public()
        ->arg('$className', param('sylius.model.product_variant.class'))
    ;
};
