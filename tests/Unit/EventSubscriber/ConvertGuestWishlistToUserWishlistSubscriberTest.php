<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Tests\Unit\EventSubscriber;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Setono\SyliusWishlistPlugin\EventSubscriber\ConvertGuestWishlistToUserWishlistSubscriber;
use Setono\SyliusWishlistPlugin\Model\GuestWishlistInterface;
use Setono\SyliusWishlistPlugin\Provider\WishlistProviderInterface;
use Sylius\Component\User\Model\UserInterface as SyliusUserInterface;
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

    #[Test]
    public function it_does_nothing_when_the_user_is_not_a_sylius_user(): void
    {
        $provider = $this->createMock(WishlistProviderInterface::class);
        $provider->expects(self::never())->method('getWishlists');

        $subscriber = new ConvertGuestWishlistToUserWishlistSubscriber($provider, $this->createMock(ManagerRegistry::class));
        $subscriber->onLoginSuccess($this->event($this->createMock(SymfonyUserInterface::class)));
    }

    #[Test]
    public function it_converts_guest_wishlists_with_items_to_the_user(): void
    {
        $user = $this->createMock(SyliusUserInterface::class);

        $guestWishlist = $this->createMock(GuestWishlistInterface::class);
        $guestWishlist->method('hasItems')->willReturn(true);
        $guestWishlist->expects(self::once())->method('convertToUserWishlist')->with($user);

        $provider = $this->createMock(WishlistProviderInterface::class);
        $provider->method('getWishlists')->willReturn([$guestWishlist]);

        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->expects(self::once())->method('flush');

        $managerRegistry = $this->createMock(ManagerRegistry::class);
        $managerRegistry->method('getManagerForClass')->willReturn($manager);

        $subscriber = new ConvertGuestWishlistToUserWishlistSubscriber($provider, $managerRegistry);
        $subscriber->onLoginSuccess($this->event($user));
    }

    #[Test]
    public function it_skips_guest_wishlists_without_items(): void
    {
        $user = $this->createMock(SyliusUserInterface::class);

        $guestWishlist = $this->createMock(GuestWishlistInterface::class);
        $guestWishlist->method('hasItems')->willReturn(false);
        $guestWishlist->expects(self::never())->method('convertToUserWishlist');

        $provider = $this->createMock(WishlistProviderInterface::class);
        $provider->method('getWishlists')->willReturn([$guestWishlist]);

        $managerRegistry = $this->createMock(ManagerRegistry::class);
        $managerRegistry->expects(self::never())->method('getManagerForClass');

        $subscriber = new ConvertGuestWishlistToUserWishlistSubscriber($provider, $managerRegistry);
        $subscriber->onLoginSuccess($this->event($user));
    }

    private function event(SymfonyUserInterface $user): LoginSuccessEvent
    {
        $event = $this->createMock(LoginSuccessEvent::class);
        $event->method('getUser')->willReturn($user);

        return $event;
    }
}
