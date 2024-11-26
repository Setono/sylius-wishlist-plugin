<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Controller\DTO;

final class ToggleWishlistResponse
{
    public const EVENT_ADDED = 'added';

    public const EVENT_REMOVED = 'removed';

    public function __construct(
        public readonly string $event,
        public readonly string $toggleUrl,
        public readonly int $wishlistItemsCount,
    ) {
    }
}
