<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Model;

use Sylius\Resource\Model\ResourceInterface;
use Sylius\Resource\Model\TimestampableInterface;

interface WishlistInterface extends ResourceInterface, TimestampableInterface
{
    public function getId(): ?int;

    public function getName(): ?string;

    public function setName(?string $name): void;
}
