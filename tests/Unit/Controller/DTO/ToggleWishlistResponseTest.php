<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Tests\Unit\Controller\DTO;

use PHPUnit\Framework\TestCase;
use Setono\SyliusWishlistPlugin\Controller\DTO\ToggleWishlistResponse;

final class ToggleWishlistResponseTest extends TestCase
{
    /** @test */
    public function it_exposes_the_data_it_was_constructed_with(): void
    {
        $response = new ToggleWishlistResponse(ToggleWishlistResponse::EVENT_ADDED, '/toggle-url', 3);

        self::assertSame('added', $response->event);
        self::assertSame('/toggle-url', $response->toggleUrl);
        self::assertSame(3, $response->wishlistItemsCount);
    }
}
