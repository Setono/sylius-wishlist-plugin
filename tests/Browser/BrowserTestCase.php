<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Tests\Browser;

use Doctrine\DBAL\Connection;
use Playwright\Locator\LocatorInterface;
use Playwright\Page\PageInterface;
use Playwright\Symfony\Test\PlaywrightTestCase;
use function Playwright\Testing\expect;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Runs the shop in a real browser against the test application's database and default fixtures. Like the functional
 * tests, every test runs in a transaction that is rolled back afterwards (dama/doctrine-test-bundle)
 */
abstract class BrowserTestCase extends PlaywrightTestCase
{
    /**
     * playwright-symfony handles every request with the same kernel, like a worker-mode server. Like KernelBrowser
     * (and PHP-FPM), each request gets fresh services instead, so state that isn't reset between requests doesn't leak
     * from one visitor or request into the next: setono/client-bundle's cached client would otherwise be handed to
     * every later browser (https://github.com/Setono/client-bundle/issues/10). The database transaction survives this,
     * because dama/doctrine-test-bundle keeps its connection in a static
     */
    public function beforeRequest(Request $request): void
    {
        parent::beforeRequest($request);

        self::currentKernel()?->shutdown();
    }

    /**
     * KernelTestCase::$kernel is nullable from Symfony 7, but declared non-nullable on 6.4 even though it can be null
     */
    private static function currentKernel(): ?KernelInterface
    {
        return self::$kernel;
    }

    /**
     * A simple product (one variant, no options) that is enabled and sold in the channel
     *
     * @return array{id: int, code: string, name: string, slug: string}
     */
    protected function simpleProduct(int $offset = 0): array
    {
        return $this->fetchProduct('NOT EXISTS', $offset);
    }

    /**
     * A product with options, so the visitor has to choose a variant
     *
     * @return array{id: int, code: string, name: string, slug: string}
     */
    protected function configurableProduct(): array
    {
        return $this->fetchProduct('EXISTS', 0);
    }

    /**
     * @param array{name: string, slug: string} $product
     */
    protected function visitProduct(array $product): PageInterface
    {
        // assertResponseIsSuccessful() would check whatever the kernel handled last, e.g. an image, not the page
        $page = $this->visit(sprintf('/en_US/products/%s', $product['slug']));
        expect($page->getByRole('heading', ['name' => $product['name'], 'level' => 1]))->toBeVisible();

        return $page;
    }

    /**
     * The wishlist button the plugin adds to the product page
     */
    protected function toggleButton(PageInterface $page): LocatorInterface
    {
        return $page->getByRole('button', ['name' => 'Toggle wishlist']);
    }

    /**
     * Puts a product on the visitor's wishlist without going through the product page. ToggleWishlistTest covers the
     * button itself
     *
     * @param array{id: int} $product
     */
    protected function addToWishlist(array $product): void
    {
        $this->visit(sprintf('/en_US/wishlist/add-product/%d', $product['id']));
    }

    /**
     * Opens the visitor's (first) wishlist
     */
    protected function visitWishlist(): PageInterface
    {
        $page = $this->visit('/en_US/wishlists/redirect-to-first');
        expect($page->getByRole('heading', ['level' => 1]))->toBeVisible();

        return $page;
    }

    /**
     * The row of the product on the wishlist page
     */
    protected function wishlistItem(PageInterface $page, string $productCode): LocatorInterface
    {
        return $page->locator(sprintf('[data-test-wishlist-item="%s"]', $productCode));
    }

    protected function updateButton(PageInterface $page): LocatorInterface
    {
        return $page->getByRole('button', ['name' => 'Update', 'exact' => true]);
    }

    /**
     * Clicks a link or submit button whose request answers with a redirect, and follows the redirect.
     *
     * Since Playwright 1.64, a click whose navigation ends in a redirect never settles in playwright-php and hangs until
     * the JSON-RPC timeout (fixed by https://github.com/playwright-php/playwright/pull/186). Stopping the browser on
     * the redirect and following it with a separate navigation avoids that. Once the fix is released, the callers can
     * go back to a plain click()
     */
    protected function clickAndFollowRedirect(LocatorInterface $locator): void
    {
        $client = self::getPlaywrightClient();
        $client->followRedirects(false);

        try {
            $locator->click();
            self::assertResponseRedirects();
            $client->followRedirect();
        } finally {
            $client->followRedirects(true);
        }
    }

    protected function connection(): Connection
    {
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        self::assertInstanceOf(Connection::class, $connection);

        return $connection;
    }

    /**
     * @param 'EXISTS'|'NOT EXISTS' $options
     *
     * @return array{id: int, code: string, name: string, slug: string}
     */
    private function fetchProduct(string $options, int $offset): array
    {
        $row = $this->connection()->fetchAssociative(sprintf(
            "SELECT p.id, p.code, t.name, t.slug FROM sylius_product p
                JOIN sylius_product_translation t ON t.translatable_id = p.id AND t.locale = 'en_US'
                WHERE p.enabled = 1 AND %s (SELECT 1 FROM sylius_product_options po WHERE po.product_id = p.id)
                ORDER BY p.id LIMIT 1 OFFSET %d",
            $options,
            $offset,
        ));
        self::assertIsArray($row, 'Load the fixtures (sylius:fixtures:load) before running the browser tests');
        self::assertIsNumeric($row['id']);
        self::assertIsString($row['code']);
        self::assertIsString($row['name']);
        self::assertIsString($row['slug']);

        return ['id' => (int) $row['id'], 'code' => $row['code'], 'name' => $row['name'], 'slug' => $row['slug']];
    }
}
