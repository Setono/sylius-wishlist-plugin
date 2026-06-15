# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A Sylius plugin (`setono/sylius-wishlist-plugin`) that adds a wishlist feature to Sylius stores. It is a library/bundle, not an application — the only runnable app is the test Sylius application under `tests/Application/`, which exists to boot the plugin for integration testing and local development.

Target runtime: PHP >= 8.1, Symfony 6.4 (Sylius 1.x does not support Symfony 7), Sylius ^1.13. The dev tools (PHPStan 2 at `level: max`, PHPUnit 10, Infection, ECS, Rector, composer-normalize) are listed individually in `require-dev` — **not** via the `setono/code-quality-pack` meta-pack — pinned to versions that all run on PHP 8.1, so `composer install` and every tool work on 8.1 (use the `8.1` shell alias). PHPStan analyses against the 8.1 floor declared in `composer.json`, so it flags any 8.2-only feature; Rector targets `UP_TO_PHP_81`. Config is `phpstan.dist.neon`.

Two `require` floors are raised specifically to keep PHPStan level-max green across the whole `lowest`..`highest` CI range: `sylius/* ^1.13` (1.13 is where Sylius made factory/repository interfaces like `CartItemFactoryInterface` `@template`-generic — older Sylius would fail the `<...>` annotations) and `twig/twig ^3.8` (3.8 is where Twig typed `TwigFunction`'s `$callable` to accept the `[Runtime::class, 'method']` array). The `static-code-analysis` CI job runs `composer remove sylius/sylius` first, so PHPStan analyses the real split component packages (which float to the latest 1.14) rather than the `sylius/sylius` monolith pinned in `require-dev`.

## Commands

Quality tooling runs from the repo root (composer scripts wrap the binaries):

```bash
composer analyse        # PHPStan static analysis (level max)
composer check-style    # ECS dry-run (sylius-labs/coding-standard)
composer fix-style      # ECS auto-fix
composer phpunit        # PHPUnit
vendor/bin/rector process --dry-run         # refactor check
vendor/bin/composer-dependency-analyser     # unused/missing composer deps
vendor/bin/infection                        # mutation testing (requires 100% MSI)
```

User shell aliases map to these: `ca` (analyse), `cf` (fix-style), `cfca` (fix then analyse), `crc` (dependency check). `phpunit`, `rector`, `infection` aliases call the project-local `vendor/bin`.

Run a single PHPUnit test: `vendor/bin/phpunit --filter testMethodName path/to/SomeTest.php`.

Commands that need the booted application must run from inside the test app (they mirror the CI `build.yaml` job):

```bash
cd tests/Application
bin/console lint:yaml ../../src/Resources
bin/console lint:twig ../../src/Resources
bin/console lint:container
bin/console doctrine:schema:validate -vvv
```

Note: there are currently no PHPUnit/spec test classes in `tests/` (only the test Application). `infection.json.dist` pins `minMsi`/`minCoveredMsi` to 100, so adding source without covering tests will fail the mutation job.

## Architecture

### Resource model (Sylius ResourceBundle)

Four configurable Sylius resources are registered in `DependencyInjection/SetonoSyliusWishlistExtension::load()` via `registerResources()`, with classes overridable under the `setono_sylius_wishlist.resources.*` config tree (`Configuration.php`): `wishlist`, `guest_wishlist`, `user_wishlist`, `wishlist_item`.

`Wishlist` is an **abstract base** (`Model/Wishlist.php`) holding items, uuid (UUID v7), name, user, and the item/product/variant manipulation logic. Two concrete subclasses:
- `GuestWishlist` — keyed by a `clientId` (anonymous visitor), can `convertToUserWishlist()`.
- `UserWishlist` — bound to a Sylius `UserInterface`.

Doctrine mapping lives in `Resources/config/doctrine/model/*.orm.xml` (not annotations/attributes).

### Provider chain — "whose wishlists are these?"

`WishlistProviderInterface` answers "the current visitor's wishlists" and is the central abstraction other services depend on. The wiring (`Resources/config/services/provider.xml`):

1. `CompositeWishlistProvider` is the public service (aliased to the interface). It is assembled by `Setono\CompositeCompilerPass` from all services tagged `setono_sylius_wishlist.wishlist_provider`, and returns the **first non-empty** result by tag priority.
2. Tagged providers, highest priority first: `UserWishlistProvider` (-40, logged-in user via `Security`) then `GuestWishlistProvider` (-50, anonymous via `setono/client-bundle` `ClientContext`).
3. `CachedWishlistProvider` **decorates** the interface (decoration-priority 64) to memoize results within a request.

To add a new wishlist source, implement `WishlistProviderInterface` and tag it — do not edit the composite.

### Guest → user conversion

`EventSubscriber/ConvertGuestWishlistToUserWishlistSubscriber` listens for Symfony's `LoginSuccessEvent`; on login it attaches the guest's wishlist items to the now-authenticated user. Guest identity comes from `setono/client-bundle`'s cookie-based `ClientContextInterface`.

### Checker + Twig

`WishlistCheckerInterface::onWishlist(product|variant)` reports whether a product is already wishlisted; `CachedWishlistChecker` decorates it (memoizes by `spl_object_hash`). Exposed to templates through `Twig/Runtime` (`onWishlist()`, `hasWishlist()`). The extension's `prepend()` injects the plugin's `_styles`/`_javascripts` into the Sylius shop layout via `sylius_ui` events, so the front-end JS (`Resources/public/js/wishlist-action-handler.js`) loads without host-app template edits.

### Controllers & routing

Controllers are single-action invokable classes under `Controller/` (e.g. `AddToWishlistAction`). Add/remove actions return JSON (`Controller/DTO/ToggleWishlistResponse`) consumed by the front-end handler; the toggle endpoints fire the same event and flip add/remove. Routes are in `Resources/config/routes/shop.yaml` (route names are `setono_sylius_wishlist_shop_wishlist_*`). `Controller/AddToWishlistAction` is registered twice (product / variant) with a `className` argument; see `services/controller.xml`.

### Security

`Security/Voter/WishlistVoter` gates the `wishlist_edit` attribute by checking the subject wishlist is among the current visitor's wishlists (from the provider) — this is how guest/user ownership is enforced on edit actions.

## Conventions

- **Services are declared in XML**, split by concern under `Resources/config/services/` and imported by `services.xml`. There is no attribute/annotation autoconfiguration — register new services in the matching XML file.
- Prefer the established decorator + composite-tag patterns over adding conditionals to existing providers/checkers.
- Never commit debug calls (`dd`, `dump`, `var_dump`, `print_r`, `exit`). Note: the Psalm `forbiddenFunctions` guard that previously enforced this was dropped in the PHPStan migration — there is no automated check for it now.
- Doctrine access in subscribers/controllers uses `Setono\Doctrine\ORMTrait` (`getManager()`), not a directly injected `EntityManager`.
- Branch versioning mirrors Sylius (e.g. `1.12.x`); `master` is the dev branch.
