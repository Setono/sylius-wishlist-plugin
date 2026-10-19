<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Tests\Unit\Controller\Command;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Setono\SyliusWishlistPlugin\Controller\Command\SelectWishlistsCommand;
use Setono\SyliusWishlistPlugin\Model\WishlistInterface;

final class SelectWishlistsCommandTest extends TestCase
{
    #[Test]
    public function it_defaults_to_no_wishlists(): void
    {
        self::assertSame([], (new SelectWishlistsCommand())->wishlists);
    }

    #[Test]
    public function it_holds_the_given_wishlists(): void
    {
        $wishlist = $this->createMock(WishlistInterface::class);

        self::assertSame([$wishlist], (new SelectWishlistsCommand([$wishlist]))->wishlists);
    }
}
