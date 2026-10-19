<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Tests\Functional;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Drives the real shop login, so it relies on the fixtures loaded by CI (sylius:fixtures:load)
 */
final class GuestWishlistLoginTest extends WebTestCase
{
    private const string EMAIL = 'shop@example.com';

    private const string PASSWORD = 'sylius';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();

        $this->deleteWishlists();

        // The fixtures don't verify the customer, and Sylius doesn't let unverified customers log in
        $this->connection()->executeStatement(
            'UPDATE sylius_shop_user SET verified_at = COALESCE(verified_at, NOW()) WHERE username = ?',
            [self::EMAIL],
        );
    }

    protected function tearDown(): void
    {
        $this->deleteWishlists();

        parent::tearDown();

        restore_exception_handler();
    }

    #[Test]
    public function it_moves_the_guest_items_onto_a_new_user_wishlist_on_login(): void
    {
        [$product] = $this->productIds(1);

        $this->addToWishlist($product);
        self::assertSame([['kind' => 'guest', 'product_id' => $product]], $this->items());

        $this->logIn();

        self::assertSame([['kind' => 'user', 'product_id' => $product]], $this->items());
        self::assertSame([['kind' => 'user', 'user_id' => $this->userId()]], $this->wishlists());
    }

    #[Test]
    public function it_merges_the_guest_items_into_the_existing_user_wishlist_on_login(): void
    {
        [$first, $second] = $this->productIds(2);

        $this->logIn();
        $this->addToWishlist($first);
        $this->logOut();

        $this->addToWishlist($first);
        $this->addToWishlist($second);

        $this->logIn();

        self::assertSame([
            ['kind' => 'user', 'product_id' => $first],
            ['kind' => 'user', 'product_id' => $second],
        ], $this->items());
        self::assertSame([['kind' => 'user', 'user_id' => $this->userId()]], $this->wishlists());
    }

    #[Test]
    public function it_does_not_hand_the_users_wishlist_to_the_next_visitor_on_the_same_browser(): void
    {
        [$first, $second] = $this->productIds(2);

        $this->addToWishlist($first);
        $this->logIn();
        $this->logOut();

        // The anonymous visitor starts from scratch instead of adding to the logged-out customer's wishlist
        self::assertSame(1, $this->addToWishlist($second));
    }

    #[Test]
    public function it_leaves_the_wishlists_alone_when_an_administrator_logs_in(): void
    {
        [$first, $second] = $this->productIds(2);

        $this->logIn();
        $this->addToWishlist($first);
        $this->logOut();

        $this->addToWishlist($second);

        // Administrators can't own a wishlist. Since the lookup is by id, the guest items could otherwise end up
        // on the wishlist of the customer whose id equals the administrator's
        $this->client->request('GET', '/admin/login');
        $this->client->submitForm('Login', [
            '_username' => 'sylius',
            '_password' => 'sylius',
        ]);
        self::assertResponseRedirects();
        self::assertStringNotContainsString('/login', (string) $this->client->getResponse()->headers->get('Location'));

        self::assertSame([
            ['kind' => 'user', 'product_id' => $first],
            ['kind' => 'guest', 'product_id' => $second],
        ], $this->items());
    }

    /**
     * @return int the number of items on the visitor's wishlist(s) after adding the product
     */
    private function addToWishlist(int $productId): int
    {
        $this->client->request('GET', sprintf('/en_US/wishlist/add-product/%d', $productId));
        self::assertResponseIsSuccessful();

        /** @var array{wishlistItemsCount: int} $response */
        $response = json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        return $response['wishlistItemsCount'];
    }

    private function logIn(): void
    {
        $this->client->request('GET', '/en_US/login');
        $this->client->submitForm('login-button', [
            '_username' => self::EMAIL,
            '_password' => self::PASSWORD,
        ]);

        self::assertResponseRedirects();
        self::assertStringNotContainsString('/login', (string) $this->client->getResponse()->headers->get('Location'));
    }

    private function logOut(): void
    {
        $this->client->request('GET', '/en_US/logout');
        self::assertResponseRedirects();
    }

    /**
     * @return list<int>
     */
    private function productIds(int $count): array
    {
        $ids = [];
        foreach ($this->connection()->fetchFirstColumn(sprintf('SELECT id FROM sylius_product ORDER BY id LIMIT %d', $count)) as $id) {
            self::assertIsNumeric($id);
            $ids[] = (int) $id;
        }

        self::assertCount($count, $ids, 'Load the fixtures (sylius:fixtures:load) before running the functional tests');

        return $ids;
    }

    private function userId(): int
    {
        $id = $this->connection()->fetchOne('SELECT id FROM sylius_shop_user WHERE username = ?', [self::EMAIL]);
        self::assertIsNumeric($id);

        return (int) $id;
    }

    /**
     * @return list<array{kind: string, user_id: int|null}>
     */
    private function wishlists(): array
    {
        /** @var list<array{kind: string, user_id: int|string|null}> $rows */
        $rows = $this->connection()->fetchAllAssociative('SELECT kind, user_id FROM setono_sylius_wishlist__wishlist ORDER BY id');

        return array_map(static fn (array $row): array => [
            'kind' => $row['kind'],
            'user_id' => null === $row['user_id'] ? null : (int) $row['user_id'],
        ], $rows);
    }

    /**
     * @return list<array{kind: string, product_id: int}>
     */
    private function items(): array
    {
        /** @var list<array{kind: string, product_id: int|string}> $rows */
        $rows = $this->connection()->fetchAllAssociative(
            'SELECT w.kind, i.product_id FROM setono_sylius_wishlist__wishlist_item i JOIN setono_sylius_wishlist__wishlist w ON w.id = i.wishlist_id ORDER BY i.product_id',
        );

        return array_map(static fn (array $row): array => [
            'kind' => $row['kind'],
            'product_id' => (int) $row['product_id'],
        ], $rows);
    }

    private function deleteWishlists(): void
    {
        // Items are removed by the ON DELETE CASCADE foreign key
        $this->connection()->executeStatement('DELETE FROM setono_sylius_wishlist__wishlist');
    }

    private function connection(): Connection
    {
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        self::assertInstanceOf(Connection::class, $connection);

        return $connection;
    }
}
