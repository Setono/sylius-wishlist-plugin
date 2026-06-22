<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Setono\SyliusWishlistPlugin\Checker\CachedWishlistChecker;
use Setono\SyliusWishlistPlugin\Checker\WishlistChecker;
use Setono\SyliusWishlistPlugin\Checker\WishlistCheckerInterface;
use Setono\SyliusWishlistPlugin\Provider\WishlistProviderInterface;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->alias(WishlistCheckerInterface::class, WishlistChecker::class);

    $services->set(WishlistChecker::class)
        ->args([
            service(WishlistProviderInterface::class),
        ])
    ;

    $services->set(CachedWishlistChecker::class)
        ->decorate(WishlistCheckerInterface::class, null, 64)
        ->args([
            service('.inner'),
        ])
    ;
};
