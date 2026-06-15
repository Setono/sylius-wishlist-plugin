# Wishlist Plugin for Sylius

[![Latest Version][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE)
[![Build Status][ico-github-actions]][link-github-actions]
[![Code Coverage][ico-code-coverage]][link-code-coverage]
[![Mutation testing][ico-infection]][link-infection]

Add a **wishlist** (a.k.a. _favourites_ / _save for later_) to your Sylius store. Visitors can save products
whether or not they're logged in, the wishlist follows them from anonymous browsing into their account, and they
can move everything to the cart in one click.

## Features

- 🛍️ **Guest _and_ customer wishlists** – anonymous visitors get a wishlist tied to a cookie; logged-in customers
  get one tied to their account.
- 🔄 **Automatic hand-off on login** – when a guest with a wishlist logs in, their items are moved onto their
  customer wishlist, so nothing is lost.
- ❤️ **Drop-in toggle button** – a ready-made "heart" button that adds/removes a product (or a specific variant)
  over AJAX, plus a JS event you can hook into.
- 🛒 **Add the whole wishlist to the cart** in a single request.
- 🔗 **Shareable wishlists** – every wishlist has a UUID, so it can be viewed via a stable, shareable URL.
- 🧩 **Extensible** – all resources (models, repositories, factories) are overridable, and the "whose wishlist is
  this?" logic is a tagged service you can plug into.

> **Heads up:** the data model already supports _multiple wishlists per customer_, but the UI/flow for choosing
> between them is still a work in progress (the `select-wishlists` endpoint currently returns _Not implemented_).

## How it works

A few concepts are worth understanding before you integrate the plugin.

### Wishlists and items

`Wishlist` is an abstract resource with two concrete subclasses:

| Class           | Belongs to                                   | Identified by                          |
|-----------------|----------------------------------------------|----------------------------------------|
| `GuestWishlist` | an anonymous visitor                         | a `clientId` (cookie, see below)       |
| `UserWishlist`  | a logged-in `Sylius\…\User\Model\UserInterface` | the user                            |

Each wishlist holds `WishlistItem`s. An item references either a **product** or a specific **product variant**
(plus a quantity), and every wishlist exposes a `uuid` (UUID v7) used in its public, shareable URL.

### Who owns the current wishlist?

Everything in the plugin asks one service – `WishlistProviderInterface` – for *"the current visitor's
wishlists"*. It's a composite built from two tagged providers, consulted by priority, returning the first
non-empty result:

1. `UserWishlistProvider` – the wishlists of the logged-in customer (via Symfony Security).
2. `GuestWishlistProvider` – the guest wishlist for the current cookie client id.

The result is memoized for the duration of the request by a caching decorator. To add another source (e.g. a
shared/team wishlist), implement `WishlistProviderInterface` and tag it – you never touch the composite itself.

### Guest identity

Anonymous visitors are identified through [`setono/client-bundle`][link-client-bundle], which assigns a stable
client id stored in a cookie. That id keys the guest wishlist. When the visitor authenticates, a login subscriber
(`ConvertGuestWishlistToUserWishlistSubscriber`) converts the guest wishlist into a user wishlist.

### Templates & the toggle flow

The plugin exposes two Twig functions so your templates can react to wishlist state:

```twig
{# is this product (or variant) on the current visitor's wishlist? #}
{{ setono_sylius_wishlist_on_wishlist(product) }}   {# returns a bool #}

{# does the current visitor have any non-empty wishlist? #}
{{ setono_sylius_wishlist_has_wishlist() }}          {# returns a bool #}
```

Adding/removing happens against small JSON endpoints. The bundled `wishlist-action-handler.js` listens for clicks
on `button.ssw-toggle`, calls the endpoint, flips the button state, and dispatches a `ssw:product-toggled` event
you can listen to (e.g. to update a header counter).

## Requirements

| Package | Version |
|---------|---------|
| PHP     | `^8.1`  |
| Sylius  | `^1.13` |
| Symfony | `^6.4`  |
| Twig    | `^3.8`  |

## Installation

### 1. Require the package

```bash
composer require setono/sylius-wishlist-plugin
```

### 2. Register the plugin

Add the bundle to `config/bundles.php` **before** `SyliusGridBundle`, otherwise you'll get a
`You have requested a non-existent parameter "setono_sylius_wishlist.model.wishlist.class"` exception:

```php
<?php
# config/bundles.php
return [
    // ...
    Setono\SyliusWishlistPlugin\SetonoSyliusWishlistPlugin::class => ['all' => true],
    Sylius\Bundle\GridBundle\SyliusGridBundle::class => ['all' => true],
    // ...
];
```

### 3. Import the routing

```yaml
# config/routes/setono_sylius_wishlist.yaml
setono_sylius_wishlist:
    resource: "@SetonoSyliusWishlistPlugin/Resources/config/routes.yaml"
```

If your shop doesn't use locales in the URL, import `routes_no_locale.yaml` instead:

```yaml
# config/routes/setono_sylius_wishlist.yaml
setono_sylius_wishlist:
    resource: "@SetonoSyliusWishlistPlugin/Resources/config/routes_no_locale.yaml"
```

### 4. Update your database

The plugin ships Doctrine mappings; generate and run a migration for your app:

```bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

### 5. Install the assets

The toggle button's stylesheet and script are injected into the shop layout automatically (via `sylius_ui`
events), but the JS file still needs to be published:

```bash
php bin/console assets:install
```

## Usage

### Add a wishlist button to your product page

Include the bundled partial wherever you render a product (e.g. the product box or the product show page),
passing the `product` and – optionally – the selected `productVariant`:

```twig
{% include '@SetonoSyliusWishlistPlugin/shop/wishlist/_toggle_button.html.twig' with {
    'product': product,
    'productVariant': variant ?? null,
} %}
```

It renders a heart `button.ssw-toggle` whose `data-url` points at the correct add/remove route and whose state
reflects `setono_sylius_wishlist_on_wishlist(...)`. No extra wiring is needed – the CSS/JS are already on the
page.

### React to toggles in JavaScript

Every toggle dispatches a bubbling `ssw:product-toggled` event:

```js
document.addEventListener('ssw:product-toggled', (e) => {
    // e.detail.event === 'added' | 'removed'
    // e.detail.wishlistItemsCount === total items across the visitor's wishlists
    document.querySelector('#wishlist-counter').textContent = e.detail.wishlistItemsCount;
});
```

You can override the handler's defaults by setting `window.sswWishlist` before the script loads (e.g. to change
the `selector.toggle` or provide your own `callback.onToggle`).

### Link to the wishlist

The wishlist index and a single (shareable) wishlist are available at these routes:

| Route name                                              | Method     | Path                                     |
|---------------------------------------------------------|------------|------------------------------------------|
| `setono_sylius_wishlist_shop_wishlist_index`            | GET        | `/wishlists`                             |
| `setono_sylius_wishlist_shop_wishlist_show`             | GET, PATCH | `/wishlists/{uuid}`                      |
| `setono_sylius_wishlist_shop_wishlist_add_to_cart`      | GET        | `/wishlists/{uuid}/add-to-cart`          |
| `setono_sylius_wishlist_shop_wishlist_item_remove`      | GET        | `/wishlists/{uuid}/remove-item/{id}`     |
| `setono_sylius_wishlist_shop_wishlist_add_product`      | GET        | `/wishlist/add-product/{id}`             |
| `setono_sylius_wishlist_shop_wishlist_remove_product`   | GET        | `/wishlist/remove-product/{id}`          |

(The `*_product_variant` variants of the add/remove routes exist too.) Because a wishlist is addressed by its
`uuid`, the `_show` URL can be shared with anyone.

## Customization

### Override a resource

Models, repositories and factories follow the standard Sylius Resource configuration. To swap in your own model:

```yaml
# config/packages/setono_sylius_wishlist.yaml
setono_sylius_wishlist:
    resources:
        wishlist:
            classes:
                model: App\Entity\Wishlist\Wishlist
        # guest_wishlist, user_wishlist and wishlist_item are configurable too
```

### Provide your own wishlists

Implement `Setono\SyliusWishlistPlugin\Provider\WishlistProviderInterface` and tag it; the composite provider
picks it up automatically (higher priority is consulted first):

```yaml
# config/services.yaml
services:
    App\Wishlist\Provider\SharedWishlistProvider:
        tags:
            - { name: 'setono_sylius_wishlist.wishlist_provider', priority: 0 }
```

### Edit authorization

Edit actions are guarded by `WishlistVoter` (the `wishlist_edit` attribute), which grants access only when the
subject wishlist belongs to the current visitor. Override the voter if you need different rules.

## Development

The repository contains a full test Sylius application under `tests/Application` used for integration testing.

```bash
composer install
composer analyse        # PHPStan (level max)
composer check-style    # Easy Coding Standard
composer phpunit        # unit tests

# commands that need the booted app:
(cd tests/Application && bin/console lint:container)
```

## License

This plugin is released under the [MIT License](LICENSE).

[ico-version]: https://poser.pugx.org/setono/sylius-wishlist-plugin/v/stable
[ico-license]: https://poser.pugx.org/setono/sylius-wishlist-plugin/license
[ico-github-actions]: https://github.com/Setono/sylius-wishlist-plugin/workflows/build/badge.svg
[ico-code-coverage]: https://codecov.io/gh/Setono/sylius-wishlist-plugin/branch/master/graph/badge.svg
[ico-infection]: https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2FSetono%2Fsylius-wishlist-plugin%2Fmaster

[link-packagist]: https://packagist.org/packages/setono/sylius-wishlist-plugin
[link-github-actions]: https://github.com/Setono/sylius-wishlist-plugin/actions
[link-code-coverage]: https://codecov.io/gh/Setono/sylius-wishlist-plugin
[link-infection]: https://dashboard.stryker-mutator.io/reports/github.com/Setono/sylius-wishlist-plugin/master
[link-client-bundle]: https://packagist.org/packages/setono/client-bundle
