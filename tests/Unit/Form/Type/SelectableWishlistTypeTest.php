<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Tests\Unit\Form\Type;

use PHPUnit\Framework\Attributes\Test;
use Setono\SyliusWishlistPlugin\Form\Type\SelectableWishlistType;
use Setono\SyliusWishlistPlugin\Model\UserWishlist;
use Symfony\Component\Form\Test\TypeTestCase;

final class SelectableWishlistTypeTest extends TypeTestCase
{
    #[Test]
    public function it_has_a_selected_checkbox_and_a_name_field_without_labels(): void
    {
        $form = $this->factory->create(SelectableWishlistType::class);

        self::assertNull($form->getConfig()->getDataClass());
        self::assertSame([], $form->getConfig()->getOption('selected'));

        self::assertFalse($form->get('selected')->getConfig()->getOption('label'));
        self::assertFalse($form->get('selected')->getConfig()->getOption('required'));
        self::assertFalse($form->get('name')->getConfig()->getOption('label'));
    }

    #[Test]
    public function it_has_no_data_without_a_wishlist(): void
    {
        $form = $this->factory->create(SelectableWishlistType::class);

        self::assertNull($form->getData());
    }

    #[Test]
    public function it_maps_a_selected_wishlist(): void
    {
        $wishlist = $this->wishlist('Birthday');

        $form = $this->factory->create(SelectableWishlistType::class, $wishlist, ['selected' => [$wishlist]]);

        self::assertSame(['selected' => true, 'name' => 'Birthday'], $form->getData());
    }

    #[Test]
    public function it_only_considers_the_same_wishlist_instance_selected(): void
    {
        $wishlist = $this->wishlist('Birthday');

        // An equal copy isn't the same wishlist
        $form = $this->factory->create(SelectableWishlistType::class, $wishlist, ['selected' => [clone $wishlist]]);

        self::assertSame(['selected' => false, 'name' => 'Birthday'], $form->getData());
    }

    #[Test]
    public function it_submits_the_selection_and_the_name(): void
    {
        $form = $this->factory->create(SelectableWishlistType::class, $this->wishlist('Birthday'));

        $form->submit(['selected' => '1', 'name' => 'Christmas']);

        self::assertTrue($form->isSynchronized());
        self::assertSame(['selected' => true, 'name' => 'Christmas'], $form->getData());
    }

    #[Test]
    public function it_requires_the_selected_wishlists_to_be_a_list(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->factory->create(SelectableWishlistType::class, $this->wishlist('Birthday'), ['selected' => 'not a list']);
    }

    private function wishlist(string $name): UserWishlist
    {
        $wishlist = new UserWishlist();
        $wishlist->setName($name);

        return $wishlist;
    }
}
