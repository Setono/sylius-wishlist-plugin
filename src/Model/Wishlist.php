<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Model;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\User\Model\UserInterface;
use Sylius\Resource\Model\TimestampableTrait;
use Symfony\Component\Uid\Uuid;

abstract class Wishlist implements WishlistInterface
{
    use TimestampableTrait;

    protected ?int $id = null;

    protected string $uuid;

    protected ?string $name = null;

    protected ?UserInterface $user = null;

    /** @var Collection<array-key, WishlistItemInterface> */
    protected Collection $items;

    public function __construct()
    {
        $this->uuid = (string) Uuid::v7();
        $this->items = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function hasItems(): bool
    {
        return !$this->items->isEmpty();
    }

    public function addItem(WishlistItemInterface $item): void
    {
        if (!$this->hasItem($item)) {
            $this->items->add($item);
            $item->setWishlist($this);
        }
    }

    public function removeItem(WishlistItemInterface $item): void
    {
        if ($this->hasItem($item)) {
            $this->items->removeElement($item);
            $item->setWishlist(null);
        }
    }

    public function hasItem(WishlistItemInterface $item): bool
    {
        return $this->items->contains($item);
    }

    public function getItems(): Collection
    {
        return $this->items;
    }

    public function hasProduct(ProductInterface $product): bool
    {
        foreach ($this->items as $item) {
            if ($item->getProduct()?->getId() === $product->getId()) {
                return true;
            }
        }

        return false;
    }

    public function removeProduct(ProductInterface $product): void
    {
        foreach ($this->items as $item) {
            if ($item->getProduct()?->getId() === $product->getId()) {
                $this->removeItem($item);
            }
        }
    }

    public function hasProductVariant(ProductVariantInterface $productVariant): bool
    {
        foreach ($this->items as $item) {
            if ($item->getVariant()?->getId() === $productVariant->getId()) {
                return true;
            }
        }

        return false;
    }

    public function removeProductVariant(ProductVariantInterface $productVariant): void
    {
        foreach ($this->items as $item) {
            if ($item->getVariant()?->getId() === $productVariant->getId()) {
                $this->removeItem($item);
            }
        }
    }

    public function getQuantity(): int
    {
        $quantity = 0;
        foreach ($this->items as $item) {
            $quantity += $item->getQuantity();
        }

        return $quantity;
    }
}
