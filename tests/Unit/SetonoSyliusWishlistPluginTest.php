<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Tests\Unit;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\DoctrineOrmMappingsPass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Setono\CompositeCompilerPass\CompositeCompilerPass;
use Setono\SyliusWishlistPlugin\SetonoSyliusWishlistPlugin;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class SetonoSyliusWishlistPluginTest extends TestCase
{
    #[Test]
    public function it_registers_the_doctrine_mapping_and_composite_provider_compiler_passes(): void
    {
        $container = new ContainerBuilder();
        (new SetonoSyliusWishlistPlugin())->build($container);

        $passes = array_map(
            static fn (CompilerPassInterface $pass): string => $pass::class,
            $container->getCompilerPassConfig()->getPasses(),
        );

        self::assertContains(DoctrineOrmMappingsPass::class, $passes);
        self::assertContains(CompositeCompilerPass::class, $passes);
    }

    #[Test]
    public function it_only_supports_the_doctrine_orm_driver(): void
    {
        self::assertSame(['doctrine/orm'], (new SetonoSyliusWishlistPlugin())->getSupportedDrivers());
    }

    #[Test]
    public function it_resolves_paths_from_the_repository_root(): void
    {
        $plugin = new SetonoSyliusWishlistPlugin();
        $root = \dirname(__DIR__, 2);

        self::assertSame($root, $plugin->getPath());
        self::assertSame(
            $root . '/config/doctrine/model',
            (new \ReflectionMethod($plugin, 'getConfigFilesPath'))->invoke($plugin),
        );
    }
}
