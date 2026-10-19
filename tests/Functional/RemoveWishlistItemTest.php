<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Tests\Functional;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Relies on the fixtures loaded by CI (sylius:fixtures:load)
 */
final class RemoveWishlistItemTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        restore_exception_handler();
    }

    #[Test]
    public function the_owner_removes_an_item_and_returns_to_the_wishlist(): void
    {
        [$uuid, $itemId] = $this->wishlistWithItem();

        $this->client->request('GET', sprintf('/en_US/wishlists/%s/remove-item/%d', $uuid, $itemId));

        self::assertResponseRedirects(sprintf('/en_US/wishlists/%s', $uuid));
        self::assertSame(0, $this->itemCount());
    }

    #[Test]
    public function anyone_else_gets_not_found_and_the_item_stays(): void
    {
        [$uuid, $itemId] = $this->wishlistWithItem();

        // Another visitor: a browser without the owner's cookies
        $this->client->restart();
        $this->client->request('GET', sprintf('/en_US/wishlists/%s/remove-item/%d', $uuid, $itemId));

        self::assertResponseStatusCodeSame(404);
        self::assertSame(1, $this->itemCount());
    }

    #[Test]
    public function an_unknown_wishlist_is_not_found(): void
    {
        $this->client->request('GET', '/en_US/wishlists/unknown/remove-item/1');

        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @return array{string, int} the uuid of the visitor's wishlist and the id of its item
     */
    private function wishlistWithItem(): array
    {
        $productId = $this->connection()->fetchOne('SELECT id FROM sylius_product ORDER BY id LIMIT 1');
        self::assertIsNumeric($productId, 'Load the fixtures (sylius:fixtures:load) before running the functional tests');

        $this->client->request('GET', sprintf('/en_US/wishlist/add-product/%d', $productId));
        self::assertResponseIsSuccessful();

        $row = $this->connection()->fetchAssociative(
            'SELECT w.uuid, i.id FROM setono_sylius_wishlist__wishlist w JOIN setono_sylius_wishlist__wishlist_item i ON i.wishlist_id = w.id',
        );
        self::assertIsArray($row);
        self::assertIsString($row['uuid']);
        self::assertIsNumeric($row['id']);

        return [$row['uuid'], (int) $row['id']];
    }

    private function itemCount(): int
    {
        $count = $this->connection()->fetchOne('SELECT COUNT(*) FROM setono_sylius_wishlist__wishlist_item');
        self::assertIsNumeric($count);

        return (int) $count;
    }

    private function connection(): Connection
    {
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        self::assertInstanceOf(Connection::class, $connection);

        return $connection;
    }
}
