<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Tests\Unit\Form\Type;

use PHPUnit\Framework\Attributes\Test;
use Setono\SyliusWishlistPlugin\Controller\Command\SelectWishlistsCommand;
use Setono\SyliusWishlistPlugin\Form\Type\SelectableWishlistType;
use Setono\SyliusWishlistPlugin\Form\Type\SelectWishlistsType;
use Setono\SyliusWishlistPlugin\Model\UserWishlist;
use Symfony\Component\Form\Test\TypeTestCase;

final class SelectWishlistsTypeTest extends TypeTestCase
{
    #[Test]
    public function it_maps_the_command_and_has_a_growing_list_of_selectable_wishlists(): void
    {
        $form = $this->factory->create(SelectWishlistsType::class);

        self::assertSame(SelectWishlistsCommand::class, $form->getConfig()->getDataClass());
        self::assertSame([], $form->getConfig()->getOption('selected'));

        $wishlists = $form->get('wishlists')->getConfig();
        self::assertSame(SelectableWishlistType::class, $wishlists->getOption('entry_type'));
        self::assertTrue($wishlists->getOption('allow_add'));

        /** @var array{label: mixed, selected: mixed} $entryOptions */
        $entryOptions = $wishlists->getOption('entry_options');
        self::assertFalse($entryOptions['label']);
        self::assertSame([], $entryOptions['selected']);
    }

    #[Test]
    public function it_marks_the_selected_wishlists(): void
    {
        $birthday = $this->wishlist('Birthday');
        $christmas = $this->wishlist('Christmas');

        $form = $this->factory->create(
            SelectWishlistsType::class,
            new SelectWishlistsCommand([$birthday, $christmas]),
            ['selected' => [$christmas]],
        );

        /** @var array{selected: mixed} $entryOptions */
        $entryOptions = $form->get('wishlists')->getConfig()->getOption('entry_options');
        self::assertSame([$christmas], $entryOptions['selected']);
        self::assertSame(['selected' => false, 'name' => 'Birthday'], $form->get('wishlists')->get('0')->getData());
        self::assertSame(['selected' => true, 'name' => 'Christmas'], $form->get('wishlists')->get('1')->getData());
    }

    #[Test]
    public function it_submits_the_selection(): void
    {
        $command = new SelectWishlistsCommand([$this->wishlist('Birthday')]);

        $form = $this->factory->create(SelectWishlistsType::class, $command);

        $form->submit(['wishlists' => [
            ['selected' => '1', 'name' => 'Birthday'],
            // Browsers don't submit unchecked checkboxes
            ['name' => 'Christmas'],
        ]]);

        self::assertTrue($form->isSynchronized());
        self::assertSame($command, $form->getData());
        self::assertSame([
            ['selected' => true, 'name' => 'Birthday'],
            ['selected' => false, 'name' => 'Christmas'],
        ], $command->wishlists);
    }

    private function wishlist(string $name): UserWishlist
    {
        $wishlist = new UserWishlist();
        $wishlist->setName($name);

        return $wishlist;
    }
}
