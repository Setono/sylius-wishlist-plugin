# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A Sylius plugin (`setono/sylius-wishlist-plugin`) that adds a wishlist feature to Sylius stores. It is a library/bundle, not an application — the only runnable app is the test Sylius application under `tests/Application/`, which exists to boot the plugin for integration testing and local development.

Target runtime: PHP >= 8.2, Symfony `^6.4 || ^7.4`, Sylius `^2.2`. This is the `2.x` line; the `1.x` branch is for Sylius 1. Use the `8.2` shell alias locally — PHP 8.2 is the declared floor, so PHPStan/Rector flag any 8.3+-only feature; Rector targets `UP_TO_PHP_82`. Config is `phpstan.neon`.

The dev toolchain comes from the **`setono/sylius-plugin`** meta dev-pack (`require-dev`), which provides PHPStan (level max + Symfony/Doctrine/PHPUnit extensions), ECS (`sylius-labs/coding-standard`), Rector, Infection, `shipmonk/composer-dependency-analyser`, Prophecy, and the reusable `setono/sylius-plugin/*@v2` GitHub composite actions used by `.github/workflows/build.yaml`.

The `static-code-analysis` and `dependency-analysis` CI jobs run `composer remove sylius/sylius` (or strip `require-dev`) first, so the analysers see the real split component packages (`sylius/core`, `sylius/order`, …) rather than the `sylius/sylius` monolith pinned in `require-dev`. Locally, with `sylius/sylius` installed, `composer-dependency-analyser` reports false positives (the split packages look "unused" and `sylius/sylius` looks like a prod dep, because the monolith `replace`s them) — that's expected; trust CI.

**api-platform dev pin (gotcha):** `require-dev` pins `api-platform/symfony: ~4.2.1`, not the skeleton's `^4.3.3`. api-platform 4.3.x emits an `object|<class>` PHPDoc union that `symfony/type-info` 7.4.x rejects (`Cannot create union with both "object" and class type`) while loading the API routes, which prevents the whole test app from booting. Sylius 2.2 is built against api-platform 4.2.x, so we pin to it. This only affects the test app (api-platform is not a runtime dep of the plugin). Revisit when the upstream incompatibility is fixed.

## Commands

Quality tooling runs from the repo root (composer scripts wrap the binaries):

```bash
composer analyse        # PHPStan static analysis (level max)
composer check-style    # ECS dry-run (sylius-labs/coding-standard)
composer fix-style      # ECS auto-fix
composer phpunit        # PHPUnit (both test suites)
vendor/bin/rector process --dry-run         # refactor check
vendor/bin/composer-dependency-analyser     # unused/missing composer deps (see note above)
vendor/bin/infection                        # mutation testing (minMsi 25, minCoveredMsi 95)
```

User shell aliases map to these: `ca` (analyse), `cf` (fix-style), `cfca` (fix then analyse), `crc` (dependency check). `phpunit`, `rector`, `infection` aliases call the project-local `vendor/bin`.

PHPUnit is split into two suites (`phpunit.xml.dist`): `tests/Unit/` (no kernel boot, fast) and `tests/Functional/` (boots the kernel, needs MySQL). Run one: `vendor/bin/phpunit --testsuite unit` / `--testsuite functional`, or `vendor/bin/phpunit --filter testMethodName`. Use the `#[Test]` attribute (not the `/** @test */` annotation — deprecated in PHPUnit 11). `infection.json5` pins `minMsi` 25 / `minCoveredMsi` 95 — covered-code MSI is ~100%, so add a unit test under `tests/Unit/` for any new source.

Commands that need the booted application run from inside the test app (they mirror the CI `build.yaml`):

```bash
cd tests/Application
bin/console lint:yaml ../../config
bin/console lint:twig ../../templates
bin/console lint:container
bin/console doctrine:schema:validate -vvv
```

To preview the shop in a browser: from `tests/Application`, run `bin/console doctrine:database:create`, `doctrine:schema:create`, `sylius:fixtures:load default`, `assets:install public`, `yarn install && yarn build`, then `symfony server:start`. (Local MySQL is reachable on `127.0.0.1:3306` as `root` with no password.)

## Architecture

### Resource model (Sylius ResourceBundle)

Four configurable Sylius resources are registered in `DependencyInjection/SetonoSyliusWishlistExtension::load()` via `registerResources()`, with classes overridable under the `setono_sylius_wishlist.resources.*` config tree (`Configuration.php`): `wishlist`, `guest_wishlist`, `user_wishlist`, `wishlist_item`.

`Wishlist` is an **abstract base** (`Model/Wishlist.php`) holding items, uuid (UUID v7), name, user, and the item/product/variant manipulation logic. Two concrete subclasses:
- `GuestWishlist` — keyed by a `clientId` (anonymous visitor), can `convertToUserWishlist()`.
- `UserWishlist` — bound to a Sylius `UserInterface`.

The bundle still extends `AbstractResourceBundle` (overriding `getPath()` → repo root and `getConfigFilesPath()` → `config/doctrine/model`, since resources moved out of `src/Resources/`). Doctrine mapping is **XML** in `config/doctrine/model/*.orm.xml` (ORM 3 compatible; not annotations/attributes).

### Provider chain — "whose wishlists are these?"

`WishlistProviderInterface` answers "the current visitor's wishlists" and is the central abstraction other services depend on. The wiring (`config/services/provider.php`):

1. `CompositeWishlistProvider` is the public service (aliased to the interface). It is assembled by `Setono\CompositeCompilerPass` from all services tagged `setono_sylius_wishlist.wishlist_provider`, and returns the **first non-empty** result by tag priority.
2. Tagged providers, highest priority first: `UserWishlistProvider` (-40, logged-in user via `Security`) then `GuestWishlistProvider` (-50, anonymous via `setono/client-bundle` `ClientContext`).
3. `CachedWishlistProvider` **decorates** the interface (decoration-priority 64) to memoize results within a request.

To add a new wishlist source, implement `WishlistProviderInterface` and tag it — do not edit the composite.

### Guest → user conversion

`EventSubscriber/ConvertGuestWishlistToUserWishlistSubscriber` listens for Symfony's `LoginSuccessEvent`; on login it attaches the guest's wishlist items to the now-authenticated user. Guest identity comes from `setono/client-bundle`'s cookie-based `ClientContextInterface`.

### Checker + Twig + shop UI

`WishlistCheckerInterface::onWishlist(product|variant)` reports whether a product is already wishlisted; `CachedWishlistChecker` decorates it (memoizes by `spl_object_hash`). Exposed to templates through `Twig/Runtime` (`onWishlist()`, `hasWishlist()`).

Shop templates live in `templates/shop/` and target the **Sylius 2 Bootstrap 5 shop theme** (extend `@SyliusShop/shared/layout/base.html.twig`; icons via `ux_icon('tabler:...')`). The extension's `prepend()` registers the plugin's `_styles`/`_javascripts` and the product-page toggle button via **Twig hooks** (`sylius_twig_hooks`): `sylius_shop.base#stylesheets`, `sylius_shop.base#javascripts`, and `sylius_shop.product.show.content.info.summary`. So the CSS/JS (`public/js/wishlist-action-handler.js`, published by `assets:install`) and the toggle button load without host-app template edits. Sylius 2 removed the `sylius_ui` `events` mechanism — use Twig hooks.

### Controllers & routing

Controllers are single-action invokable classes under `Controller/` (e.g. `AddToWishlistAction`). Add/remove actions return JSON (`Controller/DTO/ToggleWishlistResponse`) consumed by the front-end handler; the toggle endpoints flip add/remove. Routes are in `config/routes/shop.yaml` (route names are `setono_sylius_wishlist_shop_wishlist_*`). `Controller/AddToWishlistAction` is registered twice (product / variant) as children of an abstract parent with a `$className` named argument; see `config/services/controller.php`.

### Security

`Security/Voter/WishlistVoter` gates the `wishlist_edit` attribute by checking the subject wishlist is among the current visitor's wishlists (from the provider) — this is how guest/user ownership is enforced on edit actions.

## Conventions

- **Services are declared in the PHP DSL** under `config/services/*.php` (split by concern, imported by `config/services.php`), using the `namespace Symfony\Component\DependencyInjection\Loader\Configurator;` idiom so `service()`/`param()` are available unqualified. Service IDs are **FQCNs** with interface aliases; there is no autowiring/autoconfiguration — list args and tags explicitly. Leave ResourceBundle-managed IDs (`setono_sylius_wishlist.factory.*`, `.repository.*`, `.manager.*`) as-is.
- Configuration that consumers shouldn't have to wire (Twig hooks, etc.) goes in the extension's `prepend()`, not in a shipped YAML import.
- Prefer the established decorator + composite-tag patterns over adding conditionals to existing providers/checkers.
- Never commit debug calls (`dd`, `dump`, `var_dump`, `print_r`, `exit`). There is no automated guard for this.
- Doctrine access in subscribers/controllers uses `Setono\Doctrine\ORMTrait` (`getManager()`), not a directly injected `EntityManager`.
- Always update **all** translation locales (`translations/messages.*.yaml`, `flashes.*.yaml`), not just English.
- Verify UI changes in a real browser (Playwright) — Twig hook misconfiguration only surfaces at render time.
- Branch versioning mirrors Sylius: `2.x` is the default/dev branch for Sylius 2; `1.x` remains for Sylius 1.
