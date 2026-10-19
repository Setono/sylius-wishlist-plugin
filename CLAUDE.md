# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A Sylius plugin (`setono/sylius-wishlist-plugin`) that adds a wishlist feature to Sylius stores. It is a library/bundle, not an application — the only runnable app is the test Sylius application under `tests/Application/`, which exists to boot the plugin for integration testing and local development.

Target runtime: PHP >= 8.3, Symfony `^6.4 || ^7.4`, Sylius `^2.2` (the split `sylius/*` packages in `require`; never `sylius/sylius`). The test app and CI run on Sylius 2.3 (`require-dev` pins `sylius/sylius: ~2.3.1`), which needs PHP 8.3 — that is why the floor is 8.3 even though Sylius 2.2 itself allows 8.2. Lowest-deps static analysis still covers the 2.2.0 split packages. This is the `2.x` line; the `1.x` branch is for Sylius 1. Use the `8.3` shell alias locally — PHP 8.3 is the declared floor, so Rector targets `UP_TO_PHP_83` and the CI `coding-standards` job is pinned to `php-version: "8.3"` (it must always run on the lowest supported PHP; bump it together with the `composer.json` floor). Config is `phpstan.neon`.

The dev toolchain comes from the **`setono/sylius-plugin`** meta dev-pack (`require-dev`), which provides PHPStan (level max + Symfony/Doctrine/PHPUnit extensions), ECS (`sylius-labs/coding-standard`), Rector, Infection, `shipmonk/composer-dependency-analyser`, Prophecy, and the reusable `setono/sylius-plugin/*@v2` GitHub composite actions used by `.github/workflows/build.yaml`.

The `static-code-analysis` and `dependency-analysis` CI jobs run `composer remove sylius/sylius` (or strip `require-dev`) first, so the analysers see the real split component packages (`sylius/core`, `sylius/order`, …) rather than the `sylius/sylius` monolith pinned in `require-dev`. Locally, with `sylius/sylius` installed, `composer-dependency-analyser` reports false positives (the split packages look "unused" and `sylius/sylius` looks like a prod dep, because the monolith `replace`s them) — that's expected; trust CI.

**api-platform dev dep:** `require-dev` lists `api-platform/symfony` (`^4.3.17 || ^5.0`, the same range Sylius 2.3 allows) even though `sylius/sylius` already pulls it in. The `static-code-analysis` job removes `sylius/sylius`, and PHPStan still boots the test kernel, which registers the API Platform bundle. It is not a runtime dependency of the plugin.

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

PHPUnit is split into two suites (`phpunit.xml.dist`): `tests/Unit/` (no kernel boot, fast) and `tests/Functional/` (boots the kernel, needs MySQL with the schema and default fixtures loaded in the `test` env, as CI does: from `tests/Application`, `APP_ENV=test bin/console doctrine:database:create`, `doctrine:schema:create`, `sylius:fixtures:load -n`; some tests drive the real shop/admin login through `KernelBrowser` as `shop@example.com` / `sylius`). Run one: `vendor/bin/phpunit --testsuite unit` / `--testsuite functional`, or `vendor/bin/phpunit --filter testMethodName`. Use the `#[Test]` attribute (not the `/** @test */` annotation — deprecated in PHPUnit 11). `infection.json5` pins `minMsi` 25 / `minCoveredMsi` 95 — covered-code MSI is ~98% (the remaining escapes are equivalent mutants), so add a unit test under `tests/Unit/` for any new source, including DI code (`tests/Unit/DependencyInjection/` uses `matthiasnoback/symfony-dependency-injection-test` / `symfony-config-test`). If Infection ever reports every mutant as an error (`E`) yet a 100% score, mutation testing is broken, not perfect — that happened while `tests/Application/config/bootstrap.php` had a malformed autoload path that plain PHP tolerated but Infection's include interceptor didn't. CI runs the global `infection` tool from setup-php (newer than the vendored one).

Commands that need the booted application run from inside the test app (they mirror the CI `build.yaml`):

```bash
cd tests/Application
bin/console lint:yaml ../../config
bin/console lint:twig ../../templates
bin/console lint:container
bin/console doctrine:schema:validate -vvv
```

To preview the shop in a browser: from `tests/Application`, run `bin/console doctrine:database:create`, `doctrine:schema:create`, `sylius:fixtures:load default`, `assets:install public`, `yarn install && yarn build` (needs Node `^22.13 || >=24` for Webpack Encore 6), then `symfony server:start`. (Local MySQL is reachable on `127.0.0.1:3306` as `root` with no password.)

## Architecture

### Resource model (Sylius ResourceBundle)

Four configurable Sylius resources are registered in `DependencyInjection/SetonoSyliusWishlistExtension::load()` via `registerResources()`, with classes overridable under the `setono_sylius_wishlist.resources.*` config tree (`Configuration.php`): `wishlist`, `guest_wishlist`, `user_wishlist`, `wishlist_item`.

`Wishlist` is an **abstract base** (`Model/Wishlist.php`) holding items, uuid (UUID v7), name, user, and the item/product/variant manipulation logic. Two concrete subclasses:
- `GuestWishlist` — keyed by a `clientId` (anonymous visitor), can `convertToUserWishlist()`.
- `UserWishlist` — bound to a Sylius `UserInterface`.

The bundle still extends `AbstractResourceBundle` (overriding `getPath()` → repo root and `getConfigFilesPath()` → `config/doctrine/model`, since resources moved out of `src/Resources/`). Doctrine mapping is **XML** in `config/doctrine/model/*.orm.xml` (ORM 3 compatible; not annotations/attributes). Validation constraints are XML too, in `config/validation/*.xml` (group `setono_sylius_wishlist`, picked up by FrameworkBundle from the bundle path).

### Provider chain — "whose wishlists are these?"

`WishlistProviderInterface` answers "the current visitor's wishlists" and is the central abstraction other services depend on. The wiring (`config/services/provider.php`):

1. `CompositeWishlistProvider` is the public service (aliased to the interface). It is assembled by `Setono\CompositeCompilerPass` from all services tagged `setono_sylius_wishlist.wishlist_provider`, and returns the **first non-empty** result by tag priority.
2. Tagged providers, highest priority first: `UserWishlistProvider` (-40, logged-in user via `Security`) then `GuestWishlistProvider` (-50, anonymous via `setono/client-bundle` `ClientContext`).
3. `CachedWishlistProvider` **decorates** the interface (decoration-priority 64) to memoize results within a request. It is tagged `kernel.reset` (like `CachedWishlistChecker`) so long-running workers don't leak one visitor's wishlists to the next; any new memoizing service needs the same.

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
