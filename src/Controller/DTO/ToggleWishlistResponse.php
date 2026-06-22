<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Controller\DTO;

final readonly class ToggleWishlistResponse
{
    public const EVENT_ADDED = 'added';

    public const EVENT_REMOVED = 'removed';

    public function __construct(
        public string $event,
        public string $toggleUrl,
        public int $wishlistItemsCount,
    ) {
    }
}
