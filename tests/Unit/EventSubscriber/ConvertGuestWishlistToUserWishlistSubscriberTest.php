<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Tests\Unit\EventSubscriber;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Setono\SyliusWishlistPlugin\EventSubscriber\ConvertGuestWishlistToUserWishlistSubscriber;
use Setono\SyliusWishlistPlugin\Factory\WishlistFactoryInterface;
use Setono\SyliusWishlistPlugin\Factory\WishlistItemFactoryInterface;
use Setono\SyliusWishlistPlugin\Model\GuestWishlist;
use Setono\SyliusWishlistPlugin\Model\UserWishlist;
use Setono\SyliusWishlistPlugin\Model\WishlistInterface;
use Setono\SyliusWishlistPlugin\Model\WishlistItem;
use Setono\SyliusWishlistPlugin\Model\WishlistItemInterface;
use Setono\SyliusWishlistPlugin\Provider\WishlistProviderInterface;
use Setono\SyliusWishlistPlugin\Repository\UserWishlistRepositoryInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Component\Security\Core\User\UserInterface as SymfonyUserInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

final class ConvertGuestWishlistToUserWishlistSubscriberTest extends TestCase
{
    #[Test]
    public function it_subscribes_to_the_login_success_event(): void
    {
        self::assertSame(
            [LoginSuccessEvent::class => 'onLoginSuccess'],
            ConvertGuestWishlistToUserWishlistSubscriber::getSubscribedEvents(),
        );
    }

    /**
     * @param class-string<SymfonyUserInterface> $userClass
     */
    #[Test]
    #[TestWith([SymfonyUserInterface::class])]
    #[TestWith([AdminUserInterface::class])]
    public function it_does_nothing_when_the_user_is_not_a_shop_user(string $userClass): void
    {
        $provider = $this->createMock(WishlistProviderInterface::class);
        $provider->expects(self::never())->method('getWishlists');

        $subscriber = $this->subscriber($provider, $this->createMock(UserWishlistRepositoryInterface::class), $this->createMock(ManagerRegistry::class));
        $subscriber->onLoginSuccess($this->event($this->createMock($userClass)));
    }

    #[Test]
    public function it_moves_the_guest_items_onto_the_existing_user_wishlist_and_keeps_the_guest_wishlist_as_a_placeholder(): void
    {
        $user = $this->createMock(ShopUserInterface::class);

        $product = $this->product(1);
        $variant = $this->variant(10, $this->product(2));

        $productItem = $this->item($product, quantity: 2, note: 'Size M');
        $variantItem = $this->variantItem($variant, quantity: 3, note: 'In red');

        $guestWishlist = new GuestWishlist();
        $guestWishlist->setClientId('client-1');
        $guestWishlist->addItem($productItem);
        $guestWishlist->addItem($variantItem);

        $userWishlist = new UserWishlist();

        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->expects(self::never())->method('persist');
        $manager->expects(self::never())->method('remove');
        $manager->expects(self::once())->method('flush');

        $wishlistFactory = $this->createMock(WishlistFactoryInterface::class);
        $wishlistFactory->expects(self::never())->method('createForUser');

        $subscriber = $this->subscriber(
            $this->provider([$guestWishlist]),
            $this->repository($user, [$userWishlist]),
            $this->managerRegistry($manager),
            $wishlistFactory,
        );
        $subscriber->onLoginSuccess($this->event($user));

        $items = array_values($userWishlist->getItems()->toArray());
        self::assertCount(2, $items);

        [$copiedProductItem, $copiedVariantItem] = $items;

        self::assertNotSame($productItem, $copiedProductItem);
        self::assertSame($userWishlist, $copiedProductItem->getWishlist());
        self::assertSame($product, $copiedProductItem->getProduct());
        self::assertNull($copiedProductItem->getVariant());
        self::assertSame(2, $copiedProductItem->getQuantity());
        self::assertSame('Size M', $copiedProductItem->getNote());

        self::assertNotSame($variantItem, $copiedVariantItem);
        self::assertSame($userWishlist, $copiedVariantItem->getWishlist());
        self::assertSame($variant, $copiedVariantItem->getVariant());
        self::assertSame($variant->getProduct(), $copiedVariantItem->getProduct());
        self::assertSame(3, $copiedVariantItem->getQuantity());
        self::assertSame('In red', $copiedVariantItem->getNote());

        self::assertFalse($guestWishlist->hasItems());
        self::assertNull($productItem->getWishlist());
        self::assertNull($variantItem->getWishlist());
        self::assertNull($guestWishlist->getClientId());
        self::assertSame($userWishlist, $guestWishlist->getMergedInto());
    }

    #[Test]
    public function it_creates_a_user_wishlist_when_the_user_has_none(): void
    {
        $user = $this->createMock(ShopUserInterface::class);
        $product = $this->product(1);

        $guestWishlist = new GuestWishlist();
        $guestWishlist->addItem($this->item($product));

        $userWishlist = new UserWishlist();

        $wishlistFactory = $this->createMock(WishlistFactoryInterface::class);
        $wishlistFactory->expects(self::once())->method('createForUser')->with($user)->willReturn($userWishlist);

        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->expects(self::once())->method('persist')->with($userWishlist);
        $manager->expects(self::never())->method('remove');
        $manager->expects(self::once())->method('flush');

        $subscriber = $this->subscriber(
            $this->provider([$guestWishlist]),
            $this->repository($user, []),
            $this->managerRegistry($manager),
            $wishlistFactory,
        );
        $subscriber->onLoginSuccess($this->event($user));

        self::assertTrue($userWishlist->hasProduct($product));
        self::assertSame($userWishlist, $guestWishlist->getMergedInto());
    }

    #[Test]
    public function it_copies_the_guest_items_onto_the_first_user_wishlist(): void
    {
        $user = $this->createMock(ShopUserInterface::class);
        $product = $this->product(1);

        $guestWishlist = new GuestWishlist();
        $guestWishlist->addItem($this->item($product));

        $first = new UserWishlist();
        $second = new UserWishlist();

        $subscriber = $this->subscriber(
            $this->provider([$guestWishlist]),
            $this->repository($user, [$first, $second]),
            $this->managerRegistry($this->createMock(EntityManagerInterface::class)),
        );
        $subscriber->onLoginSuccess($this->event($user));

        self::assertTrue($first->hasProduct($product));
        self::assertFalse($second->hasItems());
    }

    #[Test]
    public function it_keeps_the_existing_item_when_the_product_or_variant_is_already_on_the_user_wishlist(): void
    {
        $user = $this->createMock(ShopUserInterface::class);

        $product = $this->product(1);
        $variant = $this->variant(10, $this->product(2));

        $guestWishlist = new GuestWishlist();
        $guestWishlist->addItem($this->item($product, quantity: 5, note: 'Guest note'));
        $guestWishlist->addItem($this->variantItem($variant, quantity: 5, note: 'Guest note'));

        $existingProductItem = $this->item($product);
        $existingVariantItem = $this->variantItem($variant);

        $userWishlist = new UserWishlist();
        $userWishlist->addItem($existingProductItem);
        $userWishlist->addItem($existingVariantItem);

        $subscriber = $this->subscriber(
            $this->provider([$guestWishlist]),
            $this->repository($user, [$userWishlist]),
            $this->managerRegistry($this->createMock(EntityManagerInterface::class)),
        );
        $subscriber->onLoginSuccess($this->event($user));

        self::assertSame([$existingProductItem, $existingVariantItem], array_values($userWishlist->getItems()->toArray()));
        self::assertSame(1, $existingProductItem->getQuantity());
        self::assertNull($existingProductItem->getNote());
        self::assertSame(1, $existingVariantItem->getQuantity());
        self::assertNull($existingVariantItem->getNote());
    }

    #[Test]
    public function it_skips_items_without_a_product(): void
    {
        $user = $this->createMock(ShopUserInterface::class);

        $guestWishlist = new GuestWishlist();
        $guestWishlist->addItem(new WishlistItem());

        $userWishlist = new UserWishlist();

        $wishlistItemFactory = $this->createMock(WishlistItemFactoryInterface::class);
        $wishlistItemFactory->expects(self::never())->method('createNew');

        $subscriber = $this->subscriber(
            $this->provider([$guestWishlist]),
            $this->repository($user, [$userWishlist]),
            $this->managerRegistry($this->createMock(EntityManagerInterface::class)),
            wishlistItemFactory: $wishlistItemFactory,
        );
        $subscriber->onLoginSuccess($this->event($user));

        self::assertFalse($userWishlist->hasItems());
    }

    #[Test]
    public function it_removes_an_empty_guest_wishlist_without_touching_the_user_wishlists(): void
    {
        $user = $this->createMock(ShopUserInterface::class);
        $guestWishlist = new GuestWishlist();

        $repository = $this->createMock(UserWishlistRepositoryInterface::class);
        $repository->expects(self::never())->method('findByUser');

        $wishlistFactory = $this->createMock(WishlistFactoryInterface::class);
        $wishlistFactory->expects(self::never())->method('createForUser');

        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->expects(self::never())->method('persist');
        $manager->expects(self::once())->method('remove')->with($guestWishlist);
        $manager->expects(self::once())->method('flush');

        $subscriber = $this->subscriber($this->provider([$guestWishlist]), $repository, $this->managerRegistry($manager), $wishlistFactory);
        $subscriber->onLoginSuccess($this->event($user));
    }

    #[Test]
    public function it_looks_up_the_user_wishlist_once_for_several_guest_wishlists(): void
    {
        $user = $this->createMock(ShopUserInterface::class);

        $first = new GuestWishlist();
        $first->addItem($this->item($this->product(1)));

        $second = new GuestWishlist();
        $second->addItem($this->item($this->product(2)));

        $userWishlist = new UserWishlist();

        $repository = $this->createMock(UserWishlistRepositoryInterface::class);
        $repository->expects(self::once())->method('findByUser')->with($user)->willReturn([$userWishlist]);

        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->expects(self::never())->method('remove');
        $manager->expects(self::once())->method('flush');

        $subscriber = $this->subscriber($this->provider([$first, $second]), $repository, $this->managerRegistry($manager));
        $subscriber->onLoginSuccess($this->event($user));

        self::assertCount(2, $userWishlist->getItems());
        self::assertSame($userWishlist, $first->getMergedInto());
        self::assertSame($userWishlist, $second->getMergedInto());
    }

    #[Test]
    public function it_keeps_going_after_removing_an_empty_guest_wishlist(): void
    {
        $user = $this->createMock(ShopUserInterface::class);

        $empty = new GuestWishlist();

        $withItems = new GuestWishlist();
        $withItems->addItem($this->item($this->product(1)));

        $userWishlist = new UserWishlist();

        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->expects(self::once())->method('remove')->with($empty);
        $manager->expects(self::once())->method('flush');

        $subscriber = $this->subscriber(
            $this->provider([$empty, $withItems]),
            $this->repository($user, [$userWishlist]),
            $this->managerRegistry($manager),
        );
        $subscriber->onLoginSuccess($this->event($user));

        self::assertNull($empty->getMergedInto());
        self::assertSame($userWishlist, $withItems->getMergedInto());
        self::assertCount(1, $userWishlist->getItems());
    }

    #[Test]
    public function it_ignores_wishlists_that_are_not_guest_wishlists(): void
    {
        $user = $this->createMock(ShopUserInterface::class);

        $otherUsersWishlist = new UserWishlist();
        $otherUsersWishlist->addItem($this->item($this->product(1)));

        $guestWishlist = new GuestWishlist();
        $guestWishlist->addItem($this->item($this->product(2)));

        $userWishlist = new UserWishlist();

        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->expects(self::never())->method('remove');
        $manager->expects(self::once())->method('flush');

        $subscriber = $this->subscriber(
            $this->provider([$otherUsersWishlist, $guestWishlist]),
            $this->repository($user, [$userWishlist]),
            $this->managerRegistry($manager),
        );
        $subscriber->onLoginSuccess($this->event($user));

        self::assertCount(1, $otherUsersWishlist->getItems());
        self::assertCount(1, $userWishlist->getItems());
        self::assertSame($userWishlist, $guestWishlist->getMergedInto());
    }

    #[Test]
    public function it_does_nothing_when_the_visitor_has_no_guest_wishlist(): void
    {
        $managerRegistry = $this->createMock(ManagerRegistry::class);
        $managerRegistry->expects(self::never())->method('getManagerForClass');

        $subscriber = $this->subscriber($this->provider([]), $this->createMock(UserWishlistRepositoryInterface::class), $managerRegistry);
        $subscriber->onLoginSuccess($this->event($this->createMock(ShopUserInterface::class)));
    }

    private function subscriber(
        WishlistProviderInterface $provider,
        UserWishlistRepositoryInterface $repository,
        ManagerRegistry $managerRegistry,
        ?WishlistFactoryInterface $wishlistFactory = null,
        ?WishlistItemFactoryInterface $wishlistItemFactory = null,
    ): ConvertGuestWishlistToUserWishlistSubscriber {
        if (null === $wishlistItemFactory) {
            $wishlistItemFactory = $this->createMock(WishlistItemFactoryInterface::class);
            $wishlistItemFactory->method('createNew')->willReturnCallback(static fn (): WishlistItemInterface => new WishlistItem());
        }

        return new ConvertGuestWishlistToUserWishlistSubscriber(
            $provider,
            $repository,
            $wishlistFactory ?? $this->createMock(WishlistFactoryInterface::class),
            $wishlistItemFactory,
            $managerRegistry,
        );
    }

    /**
     * @param list<WishlistInterface> $wishlists
     */
    private function provider(array $wishlists): WishlistProviderInterface
    {
        $provider = $this->createMock(WishlistProviderInterface::class);
        $provider->method('getWishlists')->willReturn($wishlists);

        return $provider;
    }

    /**
     * @param list<UserWishlist> $userWishlists
     */
    private function repository(ShopUserInterface $user, array $userWishlists): UserWishlistRepositoryInterface
    {
        $repository = $this->createMock(UserWishlistRepositoryInterface::class);
        $repository->method('findByUser')->with($user)->willReturn($userWishlists);

        return $repository;
    }

    private function managerRegistry(EntityManagerInterface $manager): ManagerRegistry
    {
        $managerRegistry = $this->createMock(ManagerRegistry::class);
        $managerRegistry->method('getManagerForClass')->willReturn($manager);

        return $managerRegistry;
    }

    private function event(SymfonyUserInterface $user): LoginSuccessEvent
    {
        $event = $this->createMock(LoginSuccessEvent::class);
        $event->method('getUser')->willReturn($user);

        return $event;
    }

    private function item(ProductInterface $product, int $quantity = 1, ?string $note = null): WishlistItem
    {
        $item = new WishlistItem();
        $item->setProduct($product);
        $item->setQuantity($quantity);
        $item->setNote($note);

        return $item;
    }

    private function variantItem(ProductVariantInterface $variant, int $quantity = 1, ?string $note = null): WishlistItem
    {
        $item = new WishlistItem();
        $item->setVariant($variant);
        $item->setQuantity($quantity);
        $item->setNote($note);

        return $item;
    }

    private function product(int $id): ProductInterface&MockObject
    {
        $product = $this->createMock(ProductInterface::class);
        $product->method('getId')->willReturn($id);

        return $product;
    }

    private function variant(int $id, ProductInterface $product): ProductVariantInterface&MockObject
    {
        $variant = $this->createMock(ProductVariantInterface::class);
        $variant->method('getId')->willReturn($id);
        $variant->method('getProduct')->willReturn($product);

        return $variant;
    }
}
