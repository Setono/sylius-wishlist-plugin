<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Model;

use Doctrine\Common\Collections\Collection;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Resource\Model\ResourceInterface;
use Sylius\Resource\Model\TimestampableInterface;

interface WishlistInterface extends ResourceInterface, TimestampableInterface
{
    public function getId(): ?int;

    public function getUuid(): string;

    public function getName(): ?string;

    public function setName(?string $name): void;

    public function hasItems(): bool;

    public function addItem(WishlistItemInterface $item): void;

    public function removeItem(WishlistItemInterface|int $item): void;

    public function hasItem(WishlistItemInterface $item): bool;

    /**
     * @return Collection<array-key, WishlistItemInterface>
     */
    public function getItems(): Collection;

    public function hasProduct(ProductInterface $product): bool;

    public function removeProduct(ProductInterface $product): void;

    public function hasProductVariant(ProductVariantInterface $productVariant): bool;

    public function removeProductVariant(ProductVariantInterface $productVariant): void;

    public function getQuantity(): int;
}
