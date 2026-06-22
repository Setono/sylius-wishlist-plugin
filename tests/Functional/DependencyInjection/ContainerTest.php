<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Tests\Functional\DependencyInjection;

use PHPUnit\Framework\Attributes\Test;
use Setono\SyliusWishlistPlugin\Checker\WishlistCheckerInterface;
use Setono\SyliusWishlistPlugin\Provider\WishlistProviderInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ContainerTest extends KernelTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();

        restore_exception_handler();
    }

    #[Test]
    public function it_boots_the_kernel_and_registers_the_plugin_services(): void
    {
        self::bootKernel();

        $container = self::getContainer();

        self::assertTrue($container->has(WishlistProviderInterface::class));
        self::assertTrue($container->has(WishlistCheckerInterface::class));
    }
}
