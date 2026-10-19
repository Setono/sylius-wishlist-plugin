# Upgrade

## From 1.x to 2.0

Version 2.0 targets **Sylius 2.2 and 2.3** (it is developed and tested against 2.3). It is a hard major
upgrade — there is no backwards-compatibility layer for Sylius 1.x. If you are still on Sylius 1.x, stay on
the `1.x` line of this plugin. PHP 8.3 is now the minimum, even on Sylius 2.2.

### 1. Requirements

| Package      | 1.x       | 2.0                 |
|--------------|-----------|---------------------|
| PHP          | `>=8.1`   | `>=8.3`             |
| Sylius       | `^1.13`   | `^2.2`              |
| Symfony      | `^6.4`    | `^6.4 \|\| ^7.4`    |
| Doctrine ORM | `^2`      | `^2.18 \|\| ^3.5`   |

### 2. Default value / behavior changes

- **The wishlist toggle button is now injected automatically** on the product show page (via the
  `sylius_shop.product.show.content.info.summary` Twig hook). In 1.x you had to include the partial
  yourself. If you already include `@SetonoSyliusWishlistPlugin/shop/wishlist/_toggle_button.html.twig`
  manually you may now get it twice — remove your manual include, or override the hook to reposition it.
- The shop templates were rewritten for the new **Bootstrap 5** Sylius 2 shop theme (they previously
  used Semantic UI). If you overrode any of the plugin's shop templates, re-create your overrides against
  the new markup.
- **Logging in now hands the guest wishlist over properly.** In 1.x the guest wishlist only got the user
  attached, so it stayed a guest wishlist: the customer couldn't see it on other devices, and the next visitor
  on the same browser could (see [#10](https://github.com/Setono/sylius-wishlist-plugin/issues/10) and
  [#11](https://github.com/Setono/sylius-wishlist-plugin/issues/11)). Now the guest items are copied onto the
  customer's first wishlist (a new one is created if they have none) and the guest wishlist is deleted.
  Only customer (shop user) logins do this; an administrator logging in leaves the guest wishlist alone.
  Guest wishlists that have a user attached are no longer returned for the browser's client id. Run the data
  fix in §7 for the wishlists 1.x already "converted".

### 3. File layout

Following the Sylius 2 plugin convention, everything moved out of `src/Resources/` to the repository root:

| 1.x                       | 2.0            |
|---------------------------|----------------|
| `src/Resources/config/`   | `config/`      |
| `src/Resources/views/`    | `templates/`   |
| `src/Resources/translations/` | `translations/` |
| `src/Resources/public/`   | `public/`      |

**Action required:** update your routing import path:

```diff
 setono_sylius_wishlist:
-    resource: "@SetonoSyliusWishlistPlugin/Resources/config/routes.yaml"
+    resource: "@SetonoSyliusWishlistPlugin/config/routes.yaml"
```

(The Twig namespace `@SetonoSyliusWishlistPlugin/...` for template references is unchanged.)

### 4. Templates → Twig hooks

The plugin no longer injects its stylesheet/script via `sylius_ui` events (removed in Sylius 2). They are
now registered on the `sylius_shop.base#stylesheets` and `sylius_shop.base#javascripts` Twig hooks. No
action is required — assets are still injected automatically.

### 5. Service configuration

Service definitions were converted from XML to the PHP DSL (`config/services.php` + `config/services/*.php`).
Almost every service already used its fully-qualified class name as its ID in 1.x — those IDs, and the
interface aliases, are unchanged. Two IDs were renamed:

| 1.x                                                             | 2.0                                                              |
|-----------------------------------------------------------------|------------------------------------------------------------------|
| `setono_sylius_wishlist.controller.add_to_wishlist` (abstract)      | `Setono\SyliusWishlistPlugin\Controller\AddToWishlistAction`      |
| `setono_sylius_wishlist.controller.remove_from_wishlist` (abstract) | `Setono\SyliusWishlistPlugin\Controller\RemoveFromWishlistAction` |

Both are abstract parent definitions, so this only affects you if you declared your own service with
`parent:` pointing at one of them.

These IDs are also unchanged:

- The Sylius ResourceBundle-managed `setono_sylius_wishlist.factory.*`, `setono_sylius_wishlist.repository.*`
  and `setono_sylius_wishlist.manager.*` services.
- The four concrete add/remove controllers (`setono_sylius_wishlist.controller.add_product_to_wishlist`,
  `…add_product_variant_to_wishlist`, `…remove_product_from_wishlist`, `…remove_product_variant_from_wishlist`).
  They keep their snake-cased IDs because the routes reference them by name, and the same class is registered
  twice, so they can't share one FQCN ID.

### 6. Removed

- The `sylius_ui` event blocks `setono_sylius_wishlist__styles` / `setono_sylius_wishlist__javascripts`
  (replaced by Twig hooks, see §4).
- All XML service / config files under `src/Resources/config/` (replaced by PHP DSL in `config/`).
- `GuestWishlistInterface::convertToUserWishlist()` and its implementation in `GuestWishlist`. Doctrine can't
  change the type of an existing wishlist, so the login subscriber copies the items instead (see §2).
- The first constructor argument of `ConvertGuestWishlistToUserWishlistSubscriber` is now the
  `GuestWishlistProvider` service instead of the composite `WishlistProviderInterface`. The constructor also
  takes the user wishlist repository and the wishlist and wishlist item factories. This only affects you if you
  redefined or extended the service.

### 7. Data fix: guest wishlists converted on login by 1.x

1.x left wishlists like that behind as rows with `kind = 'guest'` and a `user_id`. 2.0 no longer shows those
rows to anyone, so turn them into the customer wishlists they were meant to be:

```sql
UPDATE setono_sylius_wishlist__wishlist SET kind = 'user', clientId = NULL WHERE kind = 'guest' AND user_id IS NOT NULL;
```

A customer who already had a wishlist ends up with two afterwards. Both are theirs, so both appear in their
wishlist list.
