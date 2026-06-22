<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Setono\SyliusWishlistPlugin\Provider\WishlistProviderInterface;
use Setono\SyliusWishlistPlugin\Security\Voter\WishlistVoter;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(WishlistVoter::class)
        ->args([
            service(WishlistProviderInterface::class),
        ])
        ->tag('security.voter')
    ;
};
