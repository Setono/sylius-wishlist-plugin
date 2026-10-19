<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Tests\Functional\Repository;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Test;
use Setono\SyliusWishlistPlugin\Repository\GuestWishlistRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class GuestWishlistRepositoryTest extends KernelTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();

        $this->connection()->executeStatement('DELETE FROM setono_sylius_wishlist__wishlist');
    }

    protected function tearDown(): void
    {
        $this->connection()->executeStatement('DELETE FROM setono_sylius_wishlist__wishlist');

        parent::tearDown();

        restore_exception_handler();
    }

    #[Test]
    public function it_finds_the_guest_wishlist_by_client_id(): void
    {
        $this->insertGuestWishlist('client-1', null);

        self::assertSame('client-1', $this->repository()->findOneByClientId('client-1')?->getClientId());
        self::assertNull($this->repository()->findOneByClientId('client-2'));
    }

    #[Test]
    public function it_ignores_guest_wishlists_that_belong_to_a_user(): void
    {
        // Before 2.0, logging in only attached the user to the guest wishlist
        $userId = $this->connection()->fetchOne('SELECT id FROM sylius_shop_user ORDER BY id LIMIT 1');
        self::assertIsNumeric($userId, 'Load the fixtures (sylius:fixtures:load) before running the functional tests');

        $this->insertGuestWishlist('client-1', (int) $userId);

        self::assertNull($this->repository()->findOneByClientId('client-1'));
    }

    private function insertGuestWishlist(string $clientId, ?int $userId): void
    {
        $this->connection()->insert('setono_sylius_wishlist__wishlist', [
            'uuid' => uniqid('', true),
            'name' => 'Wishlist',
            'user_id' => $userId,
            'kind' => 'guest',
            'clientId' => $clientId,
        ]);
    }

    private function repository(): GuestWishlistRepositoryInterface
    {
        $repository = self::getContainer()->get('setono_sylius_wishlist.repository.guest_wishlist');
        self::assertInstanceOf(GuestWishlistRepositoryInterface::class, $repository);

        return $repository;
    }

    private function connection(): Connection
    {
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        self::assertInstanceOf(Connection::class, $connection);

        return $connection;
    }
}
