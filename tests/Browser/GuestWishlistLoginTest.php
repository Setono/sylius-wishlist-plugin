<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Tests\Browser;

use PHPUnit\Framework\Attributes\Test;
use function Playwright\Testing\expect;

final class GuestWishlistLoginTest extends BrowserTestCase
{
    private const string EMAIL = 'shop@example.com';

    protected function setUp(): void
    {
        parent::setUp();

        // The fixtures don't verify the customer, and Sylius doesn't let unverified customers log in
        $this->connection()->executeStatement(
            'UPDATE sylius_shop_user SET verified_at = COALESCE(verified_at, NOW()) WHERE username = ?',
            [self::EMAIL],
        );
    }

    #[Test]
    public function a_guest_keeps_the_wishlist_after_logging_in(): void
    {
        $product = $this->simpleProduct();

        $page = $this->visitProduct($product);
        $this->toggleButton($page)->click();
        expect($this->toggleButton($page))->toHaveClass('ssw-toggle ssw-added');

        $page = $this->visit('/en_US/login');
        $page->locator('[name="_username"]')->fill(self::EMAIL);
        $page->locator('[name="_password"]')->fill('sylius');
        $this->clickAndFollowRedirect($page->getByRole('button', ['name' => 'Login']));

        // The product is still on the (now the customer's) wishlist
        $page = $this->visitProduct($product);
        expect($this->toggleButton($page))->toHaveClass('ssw-toggle ssw-added');
        expect($this->wishlistItem($this->visitWishlist(), $product['code']))->toBeVisible();
    }
}
