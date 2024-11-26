<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Factory;

use Setono\ClientBundle\Context\ClientContextInterface;
use Setono\SyliusWishlistPlugin\Model\GuestWishlistInterface;
use Setono\SyliusWishlistPlugin\Model\UserWishlistInterface;
use Setono\SyliusWishlistPlugin\Model\WishlistInterface;
use Sylius\Component\User\Model\UserInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Webmozart\Assert\Assert;

final class WishlistFactory implements WishlistFactoryInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly ClientContextInterface $clientContext,
        /** @var class-string<GuestWishlistInterface> $guestWishlistClass */
        private readonly string $guestWishlistClass,

        /** @var class-string<UserWishlistInterface> $userWishlistClass */
        private readonly string $userWishlistClass,
    ) {
    }

    public function createNew(): WishlistInterface
    {
        $user = $this->security->getUser();
        if ($user instanceof UserInterface) {
            return $this->createForUser($user);
        }

        return $this->createForGuest($this->clientContext->getClient()->id);
    }

    public function createForGuest(string $clientId): GuestWishlistInterface
    {
        $obj = new $this->guestWishlistClass();
        Assert::isInstanceOf($obj, GuestWishlistInterface::class);

        $obj->setClientId($clientId);
        $obj->setName('Wishlist'); // todo should be configurable

        return $obj;
    }

    public function createForUser(UserInterface $user): UserWishlistInterface
    {
        $obj = new $this->userWishlistClass();
        Assert::isInstanceOf($obj, UserWishlistInterface::class);

        $obj->setUser($user);
        $obj->setName('Wishlist'); // todo should be configurable

        return $obj;
    }
}
