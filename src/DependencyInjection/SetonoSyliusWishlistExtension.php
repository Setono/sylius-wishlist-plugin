<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\DependencyInjection;

use Sylius\Bundle\ResourceBundle\DependencyInjection\Extension\AbstractResourceExtension;
use Sylius\Bundle\ResourceBundle\SyliusResourceBundle;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

final class SetonoSyliusWishlistExtension extends AbstractResourceExtension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        /**
         * @psalm-suppress PossiblyNullArgument
         *
         * @var array{resources: array} $config
         */
        $config = $this->processConfiguration($this->getConfiguration([], $container), $configs);
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));

        $this->registerResources(
            'setono_sylius_wishlist',
            SyliusResourceBundle::DRIVER_DOCTRINE_ORM,
            $config['resources'],
            $container,
        );

        $loader->load('services.xml');
    }

    public function prepend(ContainerBuilder $container): void
    {
        $container->prependExtensionConfig('sylius_ui', [
            'events' => [
                'sylius.shop.layout.stylesheets' => [
                    'blocks' => [
                        'setono_sylius_wishlist__styles' => [
                            'template' => '@SetonoSyliusWishlistPlugin/shop/_styles.html.twig',
                        ],
                    ],
                ],
                'sylius.shop.layout.javascripts' => [
                    'blocks' => [
                        'setono_sylius_wishlist__javascripts' => [
                            'template' => '@SetonoSyliusWishlistPlugin/shop/_javascripts.html.twig',
                        ],
                    ],
                ],
            ],
        ]);
    }
}
