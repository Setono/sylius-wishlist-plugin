<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Tests\Unit\Security\Voter;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Setono\SyliusWishlistPlugin\Model\WishlistInterface;
use Setono\SyliusWishlistPlugin\Provider\WishlistProviderInterface;
use Setono\SyliusWishlistPlugin\Security\Voter\WishlistVoter;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class WishlistVoterTest extends TestCase
{
    #[Test]
    public function it_grants_editing_a_wishlist_owned_by_the_current_visitor(): void
    {
        $wishlist = $this->createMock(WishlistInterface::class);

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter([$wishlist])->vote($this->token(), $wishlist, [WishlistVoter::EDIT]),
        );
    }

    #[Test]
    public function it_denies_editing_a_wishlist_not_owned_by_the_current_visitor(): void
    {
        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter([])->vote($this->token(), $this->createMock(WishlistInterface::class), [WishlistVoter::EDIT]),
        );
    }

    #[Test]
    public function it_abstains_from_unsupported_attributes(): void
    {
        $wishlist = $this->createMock(WishlistInterface::class);

        self::assertSame(
            VoterInterface::ACCESS_ABSTAIN,
            $this->voter([$wishlist])->vote($this->token(), $wishlist, ['some_other_attribute']),
        );
    }

    #[Test]
    public function it_abstains_from_unsupported_subjects(): void
    {
        self::assertSame(
            VoterInterface::ACCESS_ABSTAIN,
            $this->voter([])->vote($this->token(), new \stdClass(), [WishlistVoter::EDIT]),
        );
    }

    /**
     * @param list<WishlistInterface> $wishlists
     */
    private function voter(array $wishlists): WishlistVoter
    {
        $provider = $this->createMock(WishlistProviderInterface::class);
        $provider->method('getWishlists')->willReturn($wishlists);

        return new WishlistVoter($provider);
    }

    private function token(): TokenInterface
    {
        return $this->createMock(TokenInterface::class);
    }
}
