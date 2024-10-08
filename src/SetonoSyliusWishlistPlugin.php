<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin;

use Setono\CompositeCompilerPass\CompositeCompilerPass;
use Setono\SyliusWishlistPlugin\Provider\CompositeWishlistProvider;
use Sylius\Bundle\CoreBundle\Application\SyliusPluginTrait;
use Sylius\Bundle\ResourceBundle\AbstractResourceBundle;
use Sylius\Bundle\ResourceBundle\SyliusResourceBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class SetonoSyliusWishlistPlugin extends AbstractResourceBundle
{
    use SyliusPluginTrait;

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new CompositeCompilerPass(
            CompositeWishlistProvider::class,
            'setono_sylius_wishlist.wishlist_provider',
        ));
    }

    public function getSupportedDrivers(): array
    {
        return [
            SyliusResourceBundle::DRIVER_DOCTRINE_ORM,
        ];
    }
}
