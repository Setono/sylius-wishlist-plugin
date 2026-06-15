<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\DependencyInjection;

use Setono\SyliusWishlistPlugin\Model\GuestWishlist;
use Setono\SyliusWishlistPlugin\Model\UserWishlist;
use Setono\SyliusWishlistPlugin\Model\Wishlist;
use Setono\SyliusWishlistPlugin\Model\WishlistItem;
use Setono\SyliusWishlistPlugin\Repository\GuestWishlistRepository;
use Setono\SyliusWishlistPlugin\Repository\UserWishlistRepository;
use Setono\SyliusWishlistPlugin\Repository\WishlistRepository;
use Sylius\Component\Resource\Factory\Factory;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('setono_sylius_wishlist');

        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();

        $this->addResourcesSection($rootNode);

        return $treeBuilder;
    }

    private function addResourcesSection(ArrayNodeDefinition $node): void
    {
        $resources = $node->children()->arrayNode('resources');
        $resources->addDefaultsIfNotSet();

        $children = $resources->children();

        $this->addResource($children, 'wishlist', Wishlist::class, WishlistRepository::class);
        $this->addResource($children, 'guest_wishlist', GuestWishlist::class, GuestWishlistRepository::class);
        $this->addResource($children, 'user_wishlist', UserWishlist::class, UserWishlistRepository::class);
        $this->addResource($children, 'wishlist_item', WishlistItem::class, null);
    }

    private function addResource(NodeBuilder $children, string $name, string $model, ?string $repository): void
    {
        $resource = $children->arrayNode($name);
        $resource->addDefaultsIfNotSet();

        $resourceChildren = $resource->children();
        $resourceChildren->variableNode('options');

        $classes = $resourceChildren->arrayNode('classes');
        $classes->addDefaultsIfNotSet();

        $classesChildren = $classes->children();
        $classesChildren->scalarNode('model')->defaultValue($model)->cannotBeEmpty();

        $repositoryNode = $classesChildren->scalarNode('repository');
        if (null !== $repository) {
            $repositoryNode->defaultValue($repository);
        }
        $repositoryNode->cannotBeEmpty();

        $classesChildren->scalarNode('factory')->defaultValue(Factory::class);
    }
}
