# Upgrade

## From 1.x to 2.0

Version 2.0 targets **Sylius 2.2**. It is a hard major upgrade — there is no backwards-compatibility
layer for Sylius 1.x. If you are still on Sylius 1.x, stay on the `1.x` line of this plugin.

### 1. Requirements

| Package | 1.x            | 2.0              |
|---------|----------------|------------------|
| PHP     | `^8.1`         | `^8.2`           |
| Sylius  | `^1.13`        | `^2.2`           |
| Symfony | `^6.4`         | `^6.4 \|\| ^7.4` |
| Doctrine ORM | `^2`      | `^2.8 \|\| ^3`   |

### 2. Default value / behavior changes

- **The wishlist toggle button is now injected automatically** on the product show page (via the
  `sylius_shop.product.show.content.info.summary` Twig hook). In 1.x you had to include the partial
  yourself. If you already include `@SetonoSyliusWishlistPlugin/shop/wishlist/_toggle_button.html.twig`
  manually you may now get it twice — remove your manual include, or override the hook to reposition it.
- The shop templates were rewritten for the new **Bootstrap 5** Sylius 2 shop theme (they previously
  used Semantic UI). If you overrode any of the plugin's shop templates, re-create your overrides against
  the new markup.

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

Service definitions were converted from XML to the PHP DSL and now use **fully-qualified class names** as
service IDs (with interface aliases). If you fetched a plugin service by a snake-cased ID, use the FQCN /
interface instead. The Sylius ResourceBundle-managed IDs are unchanged:

| Unchanged (resource-bundle managed) |
|-------------------------------------|
| `setono_sylius_wishlist.factory.*`  |
| `setono_sylius_wishlist.repository.*` |
| `setono_sylius_wishlist.manager.*`  |
| `setono_sylius_wishlist.controller.add_product_to_wishlist` (and the `*_variant` / `remove_*` siblings) |

### 6. Removed

- The `sylius_ui` event blocks `setono_sylius_wishlist__styles` / `setono_sylius_wishlist__javascripts`
  (replaced by Twig hooks, see §4).
- All XML service / config files under `src/Resources/config/` (replaced by PHP DSL in `config/`).
