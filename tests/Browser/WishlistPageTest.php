<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Tests\Browser;

use PHPUnit\Framework\Attributes\Test;
use function Playwright\Testing\expect;

final class WishlistPageTest extends BrowserTestCase
{
    #[Test]
    public function the_overview_links_to_the_wishlist(): void
    {
        $product = $this->simpleProduct();
        $this->addToWishlist($product);

        $page = $this->visit('/en_US/wishlists');
        $page->getByRole('link', ['name' => 'Wishlist', 'exact' => true])->click();

        expect($page->getByRole('heading', ['level' => 1]))->toHaveText('Wishlist');
        expect($this->wishlistItem($page, $product['code']))->toBeVisible();
    }

    #[Test]
    public function the_owner_renames_the_wishlist(): void
    {
        $this->addToWishlist($this->simpleProduct());
        $page = $this->visitWishlist();

        // Clicking the name turns it into an input
        $heading = $page->getByRole('heading', ['level' => 1]);
        $heading->getByRole('button', ['name' => 'Wishlist'])->click();
        $heading->getByRole('textbox')->fill('Birthday');
        $this->clickAndFollowRedirect($this->updateButton($page));

        expect($page->getByText('Your wishlist has been updated'))->toBeVisible();
        expect($page->getByRole('heading', ['level' => 1]))->toHaveText('Birthday');
    }

    #[Test]
    public function the_owner_changes_the_quantity_and_the_note_of_an_item(): void
    {
        $product = $this->simpleProduct();
        $this->addToWishlist($product);
        $page = $this->visitWishlist();

        $item = $this->wishlistItem($page, $product['code']);
        $item->getByRole('spinbutton')->fill('3');
        $item->getByRole('textbox')->fill('Size M, please');
        $this->clickAndFollowRedirect($this->updateButton($page));

        // The page after the update is rendered from what was saved
        expect($page->getByText('Your wishlist has been updated'))->toBeVisible();
        $item = $this->wishlistItem($page, $product['code']);
        expect($item->getByRole('spinbutton'))->toHaveValue('3');
        expect($item->getByRole('textbox'))->toHaveValue('Size M, please');
    }

    #[Test]
    public function the_owner_chooses_a_variant_and_adds_the_wishlist_to_the_cart(): void
    {
        $product = $this->configurableProduct();
        $this->addToWishlist($product);
        $page = $this->visitWishlist();

        $item = $this->wishlistItem($page, $product['code']);
        expect($item)->toContainText('Select a variant and click "Update" to be able to add this product to your cart');

        // The first variant after the "Select variant..." placeholder
        $variant = $item->getByRole('combobox');
        $value = $variant->evaluate('select => select.options[1].value');
        self::assertIsString($value);
        $variant->selectOption($value);
        $this->clickAndFollowRedirect($this->updateButton($page));

        expect($page->getByText('Your wishlist has been updated'))->toBeVisible();
        expect($this->wishlistItem($page, $product['code']))->not()->toContainText('Select a variant');

        $this->clickAndFollowRedirect($page->getByRole('link', ['name' => 'Add wishlist to cart']));

        expect($page->getByText('Products from the wishlist have been added to your cart'))->toBeVisible();
        expect($page->locator('[data-test-cart-quantity]'))->toHaveAttribute('data-test-cart-quantity', '1');
    }

    #[Test]
    public function the_owner_removes_an_item(): void
    {
        $first = $this->simpleProduct();
        $second = $this->simpleProduct(1);
        $this->addToWishlist($first);
        $this->addToWishlist($second);
        $page = $this->visitWishlist();

        $this->clickAndFollowRedirect($this->wishlistItem($page, $first['code'])->getByRole('link', ['name' => 'Remove']));

        expect($this->wishlistItem($page, $first['code']))->toHaveCount(0);
        expect($this->wishlistItem($page, $second['code']))->toBeVisible();
    }

    #[Test]
    public function the_copy_button_copies_the_wishlist_url(): void
    {
        $this->addToWishlist($this->simpleProduct());
        $page = $this->visitWishlist();
        $page->context()->grantPermissions(['clipboard-read', 'clipboard-write']);

        $url = $page->locator('[data-test-wishlist-url]');
        $page->getByRole('button', ['name' => 'Copy'])->click();

        // The URL is also selected, for browsers without the Clipboard API
        expect($url)->toBeFocused();
        $wishlistUrl = $url->inputValue();
        self::assertSame([0, strlen($wishlistUrl)], $url->evaluate('input => [input.selectionStart, input.selectionEnd]'));
        $page->waitForFunction('async (expected) => (await navigator.clipboard.readText()) === expected', $wishlistUrl);
    }

    #[Test]
    public function someone_the_wishlist_is_shared_with_sees_it_read_only(): void
    {
        $product = $this->simpleProduct();
        $this->addToWishlist($product);
        $page = $this->visitWishlist();
        $this->wishlistItem($page, $product['code'])->getByRole('textbox')->fill('Size M, please');
        $this->clickAndFollowRedirect($this->updateButton($page));
        expect($page->getByText('Your wishlist has been updated'))->toBeVisible();

        $path = parse_url($page->locator('[data-test-wishlist-url]')->inputValue(), \PHP_URL_PATH);
        self::assertIsString($path);

        // Another browser, e.g. a friend who got the link
        $friend = self::createPlaywrightClient()->visit($path);

        $item = $this->wishlistItem($friend, $product['code']);
        expect($item)->toContainText('Size M, please');
        expect($item->getByRole('textbox'))->toHaveCount(0);
        expect($item->getByRole('link', ['name' => 'Remove']))->toHaveCount(0);
        expect($this->updateButton($friend))->toHaveCount(0);
        expect($friend->getByRole('heading', ['level' => 1])->getByRole('button'))->toHaveCount(0);
    }
}
