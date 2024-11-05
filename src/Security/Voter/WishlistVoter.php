<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Security\Voter;

use Setono\SyliusWishlistPlugin\Model\WishlistInterface;
use Setono\SyliusWishlistPlugin\Provider\WishlistProviderInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<'wishlist_edit', WishlistInterface>
 */
final class WishlistVoter extends Voter
{
    final public const EDIT = 'wishlist_edit';

    public function __construct(private readonly WishlistProviderInterface $wishlistProvider)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::EDIT === $attribute && $subject instanceof WishlistInterface;
    }

    /**
     * @param mixed|WishlistInterface $subject
     */
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        return in_array($subject, $this->wishlistProvider->getWishlists(), true);
    }
}
