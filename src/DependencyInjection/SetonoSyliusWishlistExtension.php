<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\DependencyInjection;

use Sylius\Bundle\ResourceBundle\DependencyInjection\Extension\AbstractResourceExtension;
use Sylius\Bundle\ResourceBundle\SyliusResourceBundle;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

final class SetonoSyliusWishlistExtension extends AbstractResourceExtension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        /** @var array{resources: array<string, mixed>} $config */
        $config = $this->processConfiguration(new Configuration(), $configs);
        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../../config'));

        $this->registerResources(
            'setono_sylius_wishlist',
            SyliusResourceBundle::DRIVER_DOCTRINE_ORM,
            $config['resources'],
            $container,
        );

        $loader->load('services.php');
    }

    public function prepend(ContainerBuilder $container): void
    {
        $container->prependExtensionConfig('sylius_twig_hooks', [
            'hooks' => [
                'sylius_shop.base#stylesheets' => [
                    'setono_sylius_wishlist_styles' => [
                        'template' => '@SetonoSyliusWishlistPlugin/shop/_styles.html.twig',
                        'priority' => 0,
                    ],
                ],
                'sylius_shop.base#javascripts' => [
                    'setono_sylius_wishlist_javascripts' => [
                        'template' => '@SetonoSyliusWishlistPlugin/shop/_javascripts.html.twig',
                        'priority' => 0,
                    ],
                ],
                'sylius_shop.product.show.content.info.summary' => [
                    'setono_sylius_wishlist_toggle' => [
                        'template' => '@SetonoSyliusWishlistPlugin/shop/product/_wishlist_toggle.html.twig',
                        'priority' => 5,
                    ],
                ],
            ],
        ]);
    }
}
