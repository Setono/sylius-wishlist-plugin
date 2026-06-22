<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Doctrine\ORM\Tools\ResolveTargetEntityListener;
use Setono\SyliusWishlistPlugin\Model\WishlistInterface;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('setono_sylius_wishlist.event_listener.doctrine.resolve_target_entity_listener', ResolveTargetEntityListener::class)
        ->call('addResolveTargetEntity', [
            WishlistInterface::class,
            param('setono_sylius_wishlist.model.wishlist.class'),
            [],
        ])
        ->tag('doctrine.event_listener', ['event' => 'loadClassMetadata'])
    ;
};
