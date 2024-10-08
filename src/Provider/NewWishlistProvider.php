<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Provider;

use Setono\ClientBundle\Context\ClientContextInterface;
use Setono\SyliusWishlistPlugin\Factory\WishlistFactoryInterface;
use Sylius\Component\User\Model\UserInterface;
use Symfony\Bundle\SecurityBundle\Security;

final class NewWishlistProvider implements WishlistProviderInterface
{
    public function __construct(
        private readonly WishlistFactoryInterface $wishlistFactory,
        private readonly Security $security,
        private readonly ClientContextInterface $clientContext,
    ) {
    }

    public function getWishlists(): array
    {
        $user = $this->security->getUser();
        if ($user instanceof UserInterface) {
            return [$this->wishlistFactory->createForUser($user)];
        }

        return [$this->wishlistFactory->createForGuest($this->clientContext->getClient()->id)];
    }
}
