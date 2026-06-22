<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Tests\Unit\Provider;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Setono\Client\Client;
use Setono\ClientBundle\Context\ClientContextInterface;
use Setono\SyliusWishlistPlugin\Model\GuestWishlistInterface;
use Setono\SyliusWishlistPlugin\Provider\GuestWishlistProvider;
use Setono\SyliusWishlistPlugin\Repository\GuestWishlistRepositoryInterface;

final class GuestWishlistProviderTest extends TestCase
{
    #[Test]
    public function it_returns_no_wishlists_when_the_client_has_none(): void
    {
        $repository = $this->createMock(GuestWishlistRepositoryInterface::class);
        $repository->method('findOneByClientId')->with('client-1')->willReturn(null);

        $provider = new GuestWishlistProvider($this->clientContext('client-1'), $repository);

        self::assertSame([], $provider->getWishlists());
    }

    #[Test]
    public function it_returns_the_clients_wishlist(): void
    {
        $wishlist = $this->createMock(GuestWishlistInterface::class);

        $repository = $this->createMock(GuestWishlistRepositoryInterface::class);
        $repository->method('findOneByClientId')->with('client-1')->willReturn($wishlist);

        $provider = new GuestWishlistProvider($this->clientContext('client-1'), $repository);

        self::assertSame([$wishlist], $provider->getWishlists());
        self::assertSame([$wishlist], $provider->getPreSelectedWishlists());
    }

    private function clientContext(string $clientId): ClientContextInterface
    {
        $clientContext = $this->createMock(ClientContextInterface::class);
        $clientContext->method('getClient')->willReturn(new Client($clientId));

        return $clientContext;
    }
}
