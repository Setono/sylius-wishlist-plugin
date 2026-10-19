<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Tests\Unit\Twig;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Setono\SyliusWishlistPlugin\Twig\Extension;
use Setono\SyliusWishlistPlugin\Twig\Runtime;

final class ExtensionTest extends TestCase
{
    #[Test]
    public function it_exposes_the_wishlist_twig_functions_backed_by_the_runtime(): void
    {
        $callables = [];
        foreach ((new Extension())->getFunctions() as $function) {
            $callables[$function->getName()] = $function->getCallable();
        }

        self::assertSame([
            'setono_sylius_wishlist_on_wishlist' => [Runtime::class, 'onWishlist'],
            'setono_sylius_wishlist_has_wishlist' => [Runtime::class, 'hasWishlist'],
        ], $callables);
    }
}
