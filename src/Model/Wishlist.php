<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Model;

use Sylius\Resource\Model\TimestampableTrait;

class Wishlist implements WishlistInterface
{
    use TimestampableTrait;

    protected ?int $id = null;

    protected ?string $name = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }
}
