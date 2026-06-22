<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Setono\SyliusWishlistPlugin\EventSubscriber\ConvertGuestWishlistToUserWishlistSubscriber;
use Setono\SyliusWishlistPlugin\Provider\WishlistProviderInterface;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(ConvertGuestWishlistToUserWishlistSubscriber::class)
        ->args([
            service(WishlistProviderInterface::class),
            service('doctrine'),
        ])
        ->tag('kernel.event_subscriber')
    ;
};
