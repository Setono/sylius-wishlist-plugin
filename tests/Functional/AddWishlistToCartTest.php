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
final class AddWishlistToCartTest extends WebTestCase
{
    private const string HINT = 'to be able to add this product to your cart';

    private KernelBrowser $client;

    /** @var list<array{string, list<int|null>}> the statements that restore the fixture data a test changed */
    private array $restoreStatements = [];

    protected function setUp(): void
    {
        $this->client = self::createClient();

        $this->deleteWishlists();
    }

    protected function tearDown(): void
    {
        $this->deleteWishlists();

        foreach ($this->restoreStatements as [$sql, $params]) {
            $this->connection()->executeStatement($sql, $params);
        }

        parent::tearDown();

        restore_exception_handler();
    }

    #[Test]
    public function it_adds_the_items_that_have_a_variant_and_names_the_products_without_one(): void
    {
        $configurable = $this->fetchProduct('EXISTS');
        $simple = $this->fetchProduct('NOT EXISTS');

        // A configurable product is added without a variant, a simple product with its only variant
        $this->client->request('GET', sprintf('/en_US/wishlist/add-product/%d', $configurable['id']));
        $this->client->request('GET', sprintf('/en_US/wishlist/add-product/%d', $simple['id']));

        $uuid = $this->connection()->fetchOne("SELECT uuid FROM setono_sylius_wishlist__wishlist WHERE kind = 'guest'");
        self::assertIsString($uuid);

        // The owner is told which item needs a variant
        $this->client->request('GET', sprintf('/en_US/wishlists/%s', $uuid));
        self::assertResponseIsSuccessful();
        self::assertSame(1, substr_count((string) $this->client->getResponse()->getContent(), self::HINT));

        // Only the simple product went into the cart, instead of nothing at all
        self::assertSame([$simple['variant_id']], $this->addWishlistToCart($uuid));

        $content = $this->followRedirect();
        self::assertStringContainsString('Products from the wishlist have been added to your cart', $content);
        self::assertStringContainsString(sprintf('no variant has been selected: %s', $configurable['name']), $content);
    }

    #[Test]
    public function it_adds_the_available_items_and_names_the_products_that_are_no_longer_priced(): void
    {
        $available = $this->fetchProduct('NOT EXISTS');
        $unpriced = $this->fetchProduct('NOT EXISTS', 1);

        $this->client->request('GET', sprintf('/en_US/wishlist/add-product/%d', $available['id']));
        $this->client->request('GET', sprintf('/en_US/wishlist/add-product/%d', $unpriced['id']));

        // Sylius can't price a variant without a price in the channel, which used to fail the whole wishlist
        foreach ($this->connection()->fetchAllKeyValue('SELECT id, price FROM sylius_channel_pricing WHERE product_variant_id = ?', [$unpriced['variant_id']]) as $id => $price) {
            self::assertIsNumeric($id);
            self::assertTrue(null === $price || is_numeric($price));
            $this->restoreStatements[] = ['UPDATE sylius_channel_pricing SET price = ? WHERE id = ?', [null === $price ? null : (int) $price, (int) $id]];
        }
        $this->connection()->executeStatement('UPDATE sylius_channel_pricing SET price = NULL WHERE product_variant_id = ?', [$unpriced['variant_id']]);

        $uuid = $this->connection()->fetchOne("SELECT uuid FROM setono_sylius_wishlist__wishlist WHERE kind = 'guest'");
        self::assertIsString($uuid);

        self::assertSame([$available['variant_id']], $this->addWishlistToCart($uuid));

        // The wishlist page used to fail on the price of such an item too
        $content = $this->followRedirect();
        self::assertStringContainsString('Products from the wishlist have been added to your cart', $content);
        self::assertStringContainsString(sprintf("unavailable and weren't added to your cart: %s", $unpriced['name']), $content);
        self::assertStringContainsString('Unavailable', $content);
    }

    #[Test]
    public function it_adds_the_items_in_stock_and_names_the_products_without_enough_stock(): void
    {
        $inStock = $this->fetchProduct('NOT EXISTS');
        $outOfStock = $this->fetchProduct('NOT EXISTS', 1);

        $this->client->request('GET', sprintf('/en_US/wishlist/add-product/%d', $inStock['id']));
        $this->client->request('GET', sprintf('/en_US/wishlist/add-product/%d', $outOfStock['id']));

        // The fixtures don't track stock, so we make one variant tracked and sold out
        $original = $this->connection()->fetchAssociative('SELECT tracked, on_hand, on_hold FROM sylius_product_variant WHERE id = ?', [$outOfStock['variant_id']]);
        self::assertIsArray($original);
        self::assertIsNumeric($original['tracked']);
        self::assertIsNumeric($original['on_hand']);
        self::assertIsNumeric($original['on_hold']);
        $this->restoreStatements[] = [
            'UPDATE sylius_product_variant SET tracked = ?, on_hand = ?, on_hold = ? WHERE id = ?',
            [(int) $original['tracked'], (int) $original['on_hand'], (int) $original['on_hold'], $outOfStock['variant_id']],
        ];
        $this->connection()->executeStatement('UPDATE sylius_product_variant SET tracked = 1, on_hand = 0, on_hold = 0 WHERE id = ?', [$outOfStock['variant_id']]);

        $uuid = $this->connection()->fetchOne("SELECT uuid FROM setono_sylius_wishlist__wishlist WHERE kind = 'guest'");
        self::assertIsString($uuid);

        self::assertSame([$inStock['variant_id']], $this->addWishlistToCart($uuid));

        $content = $this->followRedirect();
        self::assertStringContainsString('Products from the wishlist have been added to your cart', $content);
        self::assertStringContainsString(sprintf("so they weren't added to your cart: %s", $outOfStock['name']), $content);
        // The owner already sees which row lacks stock
        self::assertStringContainsString('The inventory is insufficient', $content);
    }

    /**
     * @return list<int> the variants in the cart that was created by adding the wishlist to it
     */
    private function addWishlistToCart(string $uuid): array
    {
        $lastOrderId = $this->connection()->fetchOne('SELECT COALESCE(MAX(id), 0) FROM sylius_order');
        self::assertIsNumeric($lastOrderId);

        $this->client->request('GET', sprintf('/en_US/wishlists/%s/add-to-cart', $uuid));
        self::assertResponseRedirects(sprintf('/en_US/wishlists/%s', $uuid));

        $variantIds = [];
        foreach ($this->connection()->fetchFirstColumn(
            "SELECT oi.variant_id FROM sylius_order_item oi JOIN sylius_order o ON o.id = oi.order_id WHERE o.id > ? AND o.state = 'cart' ORDER BY oi.id",
            [$lastOrderId],
        ) as $variantId) {
            self::assertIsNumeric($variantId);
            $variantIds[] = (int) $variantId;
        }

        return $variantIds;
    }

    /**
     * @return string the decoded HTML of the page redirected to
     */
    private function followRedirect(): string
    {
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();

        return html_entity_decode((string) $this->client->getResponse()->getContent(), \ENT_QUOTES);
    }

    /**
     * @param 'EXISTS'|'NOT EXISTS' $options EXISTS for a configurable product, NOT EXISTS for a simple one
     *
     * @return array{id: int, name: string, variant_id: int}
     */
    private function fetchProduct(string $options, int $offset = 0): array
    {
        $row = $this->connection()->fetchAssociative(sprintf(
            "SELECT p.id, t.name, MIN(v.id) AS variant_id FROM sylius_product p
                JOIN sylius_product_translation t ON t.translatable_id = p.id AND t.locale = 'en_US'
                JOIN sylius_product_variant v ON v.product_id = p.id
                WHERE p.enabled = 1 AND %s (SELECT 1 FROM sylius_product_options po WHERE po.product_id = p.id)
                GROUP BY p.id, t.name ORDER BY p.id LIMIT 1 OFFSET %d",
            $options,
            $offset,
        ));
        self::assertIsArray($row, 'Load the fixtures (sylius:fixtures:load) before running the functional tests');
        self::assertIsNumeric($row['id']);
        self::assertIsString($row['name']);
        self::assertIsNumeric($row['variant_id']);

        return ['id' => (int) $row['id'], 'name' => $row['name'], 'variant_id' => (int) $row['variant_id']];
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
