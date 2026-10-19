<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Setono\SyliusWishlistPlugin\Checker\WishlistCheckerInterface;
use Setono\SyliusWishlistPlugin\Checker\WishlistItemAvailabilityCheckerInterface;
use Setono\SyliusWishlistPlugin\Provider\WishlistProviderInterface;
use Setono\SyliusWishlistPlugin\Twig\Extension;
use Setono\SyliusWishlistPlugin\Twig\Runtime;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(Extension::class)
        ->tag('twig.extension')
    ;

    $services->set(Runtime::class)
        ->args([
            service(WishlistProviderInterface::class),
            service(WishlistCheckerInterface::class),
            service(WishlistItemAvailabilityCheckerInterface::class),
            service('sylius.context.channel'),
        ])
        ->tag('twig.runtime')
    ;
};
