<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Tests\Browser;

use PHPUnit\Framework\Attributes\Test;
use function Playwright\Testing\expect;

/**
 * The wishlist button on the product page, toggled by public/js/wishlist-action-handler.js
 */
final class ToggleWishlistTest extends BrowserTestCase
{
    #[Test]
    public function a_visitor_adds_a_product_to_the_wishlist_and_removes_it_again(): void
    {
        $product = $this->simpleProduct();

        $page = $this->visitProduct($product);
        expect($this->toggleButton($page))->toHaveClass('ssw-toggle');

        $this->toggleButton($page)->click();
        expect($this->toggleButton($page))->toHaveClass('ssw-toggle ssw-added');

        // The server renders the state too, so it survives a reload
        $page->reload();
        expect($this->toggleButton($page))->toHaveClass('ssw-toggle ssw-added');
        expect($this->wishlistItem($this->visitWishlist(), $product['code']))->toBeVisible();

        $page = $this->visitProduct($product);
        $this->toggleButton($page)->click();
        expect($this->toggleButton($page))->toHaveClass('ssw-toggle');
        expect($this->wishlistItem($this->visitWishlist(), $product['code']))->toHaveCount(0);
    }

    #[Test]
    public function toggling_dispatches_an_event_with_the_number_of_items_on_the_wishlist(): void
    {
        $page = $this->visitProduct($this->simpleProduct());
        $page->evaluate('() => {
            window.sswToggled = [];
            document.addEventListener("ssw:product-toggled", (event) => window.sswToggled.push(event.detail));
        }');

        $this->toggleButton($page)->click();
        $page->waitForFunction('() => window.sswToggled.length === 1');
        $this->toggleButton($page)->click();
        $page->waitForFunction('() => window.sswToggled.length === 2');

        self::assertSame([
            ['event' => 'added', 'wishlistItemsCount' => 1],
            ['event' => 'removed', 'wishlistItemsCount' => 0],
        ], $page->evaluate('() => window.sswToggled'));
    }
}
