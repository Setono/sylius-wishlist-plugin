<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Model;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Sylius\Resource\Model\TimestampableTrait;

abstract class Wishlist implements WishlistInterface
{
    use TimestampableTrait;

    protected ?int $id = null;

    protected ?string $name = null;

    /** @var Collection<array-key, WishlistItemInterface> */
    protected Collection $items;

    public function __construct()
    {
        $this->items = new ArrayCollection();
    }

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

    public function addItem(WishlistItemInterface $item): void
    {
        if (!$this->hasItem($item)) {
            $this->items->add($item);
        }
    }

    public function removeItem(WishlistItemInterface $item): void
    {
        if ($this->hasItem($item)) {
            $this->items->removeElement($item);
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
}
