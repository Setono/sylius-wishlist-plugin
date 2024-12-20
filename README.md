# Wishlist Plugin for Sylius

[![Latest Version][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE)
[![Build Status][ico-github-actions]][link-github-actions]
[![Code Coverage][ico-code-coverage]][link-code-coverage]
[![Mutation testing][ico-infection]][link-infection]

This plugin adds a wishlist feature to Sylius. It comes with these features:

- Add products to a wishlist (whether the visitor is logged in or not)
- Allow multiple wishlists per user (not implemented yet)
- Share wishlists with others
- Add products to cart from wishlist
## Installation

```bash
composer require setono/sylius-wishlist-plugin
```

### Add plugin class to your `bundles.php`

Make sure you add it before `SyliusGridBundle`, otherwise you'll get
`You have requested a non-existent parameter "setono_sylius_wishlist.model.wishlist.class".` exception.

```php
<?php
$bundles = [
    // ...
    Setono\SyliusWishlistPlugin\SetonoSyliusWishlistPlugin::class => ['all' => true],
    Sylius\Bundle\GridBundle\SyliusGridBundle::class => ['all' => true],
    // ...
];
```

### Import routing

```yaml
# config/routes/setono_sylius_wishlist.yaml
setono_sylius_wishlist:
    resource: "@SetonoSyliusWishlistPlugin/Resources/config/routes.yaml"
```

or if your app doesn't use locales:

```yaml
# config/routes/setono_sylius_wishlist.yaml
setono_sylius_wishlist:
    resource: "@SetonoSyliusWishlistPlugin/Resources/config/routes_no_locale.yaml"
```

### Update your database

```shell
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

### Update your templates

TODO


[ico-version]: https://poser.pugx.org/setono/sylius-wishlist-plugin/v/stable
[ico-license]: https://poser.pugx.org/setono/sylius-wishlist-plugin/license
[ico-github-actions]: https://github.com/Setono/sylius-wishlist-plugin/workflows/build/badge.svg
[ico-code-coverage]: https://codecov.io/gh/Setono/sylius-wishlist-plugin/branch/master/graph/badge.svg
[ico-infection]: https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2FSetono%2Fsylius-wishlist-plugin%2Fmaster

[link-packagist]: https://packagist.org/packages/setono/sylius-wishlist-plugin
[link-github-actions]: https://github.com/Setono/sylius-wishlist-plugin/actions
[link-code-coverage]: https://codecov.io/gh/Setono/sylius-wishlist-plugin
[link-infection]: https://dashboard.stryker-mutator.io/reports/github.com/Setono/sylius-wishlist-plugin/master
