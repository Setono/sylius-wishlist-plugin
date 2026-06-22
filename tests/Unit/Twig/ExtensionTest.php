<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Tests\Unit\Twig;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Setono\SyliusWishlistPlugin\Twig\Extension;

final class ExtensionTest extends TestCase
{
    #[Test]
    public function it_exposes_the_wishlist_twig_functions(): void
    {
        $names = array_map(
            static fn (\Twig\TwigFunction $function): string => $function->getName(),
            (new Extension())->getFunctions(),
        );

        self::assertContains('setono_sylius_wishlist_on_wishlist', $names);
        self::assertContains('setono_sylius_wishlist_has_wishlist', $names);
    }
}
