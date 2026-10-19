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
vendor/bin/phpunit --coverage-text          # line coverage (needs pcov or Xdebug, and the test database for the functional suite)
vendor/bin/rector process --dry-run         # refactor check
vendor/bin/composer-dependency-analyser     # unused/missing composer deps (see note above)
vendor/bin/infection                        # mutation testing (minMsi 99, minCoveredMsi 99)
composer validate --strict                  # validate composer.json
composer normalize --dry-run                # check composer.json normalization (run `composer normalize` after `composer require`)
symfony lsp:check                           # Symfony-aware diagnostics for the plugin and its test app (needs Symfony CLI >= 5.20)
```

User shell aliases map to these: `ca` (analyse), `cf` (fix-style), `cfca` (fix then analyse), `crc` (dependency check). `phpunit`, `rector`, `infection` aliases call the project-local `vendor/bin`.

PHPUnit has three suites (`phpunit.xml.dist`): `unit`, `functional` and `browser`. A plain `vendor/bin/phpunit` (and `composer phpunit`) runs `unit` and `functional`; run one with `vendor/bin/phpunit --testsuite unit` / `functional` / `browser`, a file with `vendor/bin/phpunit tests/path/ToTest.php`, or a test with `vendor/bin/phpunit --filter it_does_something`. See [Testing](#testing).

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
- `GuestWishlist` — keyed by a `clientId` (anonymous visitor). After its owner logs in it can be an empty placeholder whose `mergedInto` points at the user wishlist its items went to.
- `UserWishlist` — bound to a Sylius `UserInterface`.

The bundle still extends `AbstractResourceBundle` (overriding `getPath()` → repo root and `getConfigFilesPath()` → `config/doctrine/model`, since resources moved out of `src/Resources/`). Doctrine mapping is **XML** in `config/doctrine/model/*.orm.xml` (ORM 3 compatible; not annotations/attributes). Validation constraints are XML too, in `config/validation/*.xml` (group `setono_sylius_wishlist`, picked up by FrameworkBundle from the bundle path).

### Provider chain — "whose wishlists are these?"

`WishlistProviderInterface` answers "the current visitor's wishlists" and is the central abstraction other services depend on. The wiring (`config/services/provider.php`):

1. `CompositeWishlistProvider` is the public service (aliased to the interface). It is assembled by `Setono\CompositeCompilerPass` from all services tagged `setono_sylius_wishlist.wishlist_provider`, and returns the **first non-empty** result by tag priority.
2. Tagged providers, highest priority first: `UserWishlistProvider` (-40, logged-in user via `Security`) then `GuestWishlistProvider` (-50, anonymous via `setono/client-bundle` `ClientContext`).
3. `CachedWishlistProvider` **decorates** the interface (decoration-priority 64) to memoize results within a request. It is tagged `kernel.reset` (like `CachedWishlistChecker`) so long-running workers don't leak one visitor's wishlists to the next; any new memoizing service needs the same.

To add a new wishlist source, implement `WishlistProviderInterface` and tag it — do not edit the composite.

### Guest → user conversion

`EventSubscriber/ConvertGuestWishlistToUserWishlistSubscriber` listens for Symfony's `LoginSuccessEvent` (shop users only; admin logins fire it too). It reads the guest wishlist from `GuestWishlistProvider` (not the composite, which answers with the user's wishlists once logged in), copies its items onto the customer's first wishlist (creating one if needed, skipping products already on it), empties it, clears its `clientId` and points `mergedInto` at the user wishlist. `ShowWishlistAction` redirects only that wishlist's owner from the old guest URL; anyone else gets a 404 (see #13). Doctrine can't change an entity's discriminator, which is why the items are copied. Guest identity comes from `setono/client-bundle`'s cookie-based `ClientContextInterface`.

### Checker + Twig + shop UI

`WishlistCheckerInterface::onWishlist(product|variant)` reports whether a product is already wishlisted; `CachedWishlistChecker` decorates it (memoizes by `spl_object_hash`). `WishlistItemAvailabilityCheckerInterface` decides whether an item can still be bought in a channel (product enabled and in the channel; the chosen variant enabled and priced there). `AddWishlistToCartAction` and the wishlist page both use it, so they agree; the action additionally checks stock like Sylius' `CartItemAvailabilityValidator`. Exposed to templates through `Twig/Runtime` (`onWishlist()`, `hasWishlist()`, `isAvailable()`).

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
- Classes are `final` by default and stateless services `final readonly`; resource models and repositories are deliberate extension points and aren't. Every service has an interface next to it, and consumers depend on the interface.
- Use `Webmozart\Assert\Assert` for runtime assertions, never PHP's `assert()`. Comments explain why, not what.
- Services that memoize per request implement `Symfony\Contracts\Service\ResetInterface` and are tagged `kernel.reset`.
- Doctrine models are mapped as `<entity>` (single-table inheritance for guest/user wishlists), not as `<mapped-superclass>` like the skeleton suggests, and the plugin ships no migrations.

## Workflow

- Before each commit run `composer fix-style`, `composer analyse` and `composer phpunit`, and fix what they flag. Never weaken a gate to get green: no PHPStan baselines, no lowered thresholds, no `--no-verify`.
- Pull requests target `2.x` (pass `--base 2.x` to `gh pr create`) and are merged with a merge commit (`gh pr merge --merge --delete-branch`), not a squash.
- Commit subjects are imperative, sentence case and describe the outcome, without Conventional Commit prefixes. The body explains why and links upstream issues or PRs.
- When behaviour users see changes, update `README.md`. When users must act during an upgrade (renamed/removed classes, services, templates, config keys, changed defaults, schema changes), document it in `UPGRADE.md`.
- Keep this file current: when a tool, dependency or convention changes how things are done, update CLAUDE.md in the same change.

## Testing

- Every change ships with tests: unit tests by default, functional tests when behaviour is only visible with the kernel or the database. Assert values and outcomes, not just that methods were called, so mutants get killed.
- `tests/Unit/` mirrors `src/` and never boots the kernel or touches the database. `tests/Functional/` boots `tests/Application` and runs against MySQL with the default fixtures (from `tests/Application`: `APP_ENV=test bin/console doctrine:database:create`, `doctrine:schema:create`, `sylius:fixtures:load -n`). Some functional tests drive the real shop/admin login through `KernelBrowser` as `shop@example.com` / `sylius` (admin `sylius` / `sylius`).
- `dama/doctrine-test-bundle` runs each test in a transaction that is rolled back afterwards, so functional tests may write to the database (and change fixture data) without cleaning up.
- **Browser tests** (`tests/Browser/`, suite `browser`) drive the shop in a real Chromium with [playwright-php/playwright-symfony](https://github.com/playwright-php/playwright-symfony): requests from the browser are handled by the test kernel in the same process, so the rolled-back transaction covers them too. Write one when behaviour depends on the browser (JavaScript, the toggle button, click-to-edit, the copy button) or for a key flow end to end; everything else stays functional. They extend `Tests\Browser\BrowserTestCase` and use the Playwright page API with `Playwright\Testing\expect()`.
  - Setup: Node 20+, `vendor/bin/playwright-install chromium` once, and the bundles' assets installed as **copies** (`cd tests/Application && bin/console assets:install public`, no `--symlink`): playwright-symfony refuses to serve a file whose real path is outside `tests/Application/public`, so a symlinked `public/bundles/setonosyliuswishlistplugin` (the plugin's JavaScript) would 404. Re-run it after changing `public/`.
  - Find elements by role and accessible name; when that isn't enough, add a hook with `{{ sylius_test_html_attribute('name', value) }}` (rendered only in the test env / debug), never a raw `data-test-*` attribute. Discover products and customers from the fixtures instead of hardcoding ids.
  - `BrowserTestCase::beforeRequest()` shuts the kernel down before each request, so every request gets fresh services like under PHP-FPM / `KernelBrowser`. Without it, state that isn't reset between requests leaks: `setono/client-bundle`'s `CachedClientContext` hands the first visitor's client id to every later browser ([Setono/client-bundle#10](https://github.com/Setono/client-bundle/issues/10), which also affects worker-mode deployments), and stale entities make products look unavailable.
  - Click links/buttons whose request redirects with `clickAndFollowRedirect()`: since Playwright 1.64 such a click hangs until the JSON-RPC timeout (fixed by [playwright-php/playwright#186](https://github.com/playwright-php/playwright/pull/186); a plain `click()` will do once that's released). `assertResponseIsSuccessful()` after `visit()` checks whatever the kernel handled last (an image, say), so assert on the page instead.
  - CI runs them in the `browser-tests` job: the shared `functional-tests` action sets the app up (it runs no suite without a `testsuite` input), then the job installs the assets as copies and Chromium (`--with-deps`), since the `setono/sylius-plugin` actions don't install Playwright browsers.
- **Use Prophecy for all test doubles** (`Prophecy\PhpUnit\ProphecyTrait`, `$this->prophesize()`), never PHPUnit's `createMock()`/`createStub()`. Classes Prophecy can't double (final ones, e.g. Doctrine's `Query`) are tested functionally instead. Once any method of a double is configured, every other call the code makes on it must be stubbed too, and `->will()` callbacks must be `static` closures (Prophecy re-binds `$this`, so copy what they need into locals).
- Mark tests with `#[Test]` (not `/** @test */`) and name them in snake_case BDD style (`it_does_something`). Use `self::assert…()`; data providers are `public static`. Kernel and web tests call `restore_exception_handler()` in `tearDown()`.
- DI code is tested with `matthiasnoback/symfony-dependency-injection-test` (`AbstractExtensionTestCase`) and `symfony-config-test`; form types extend `Symfony\Component\Form\Test\TypeTestCase` and are created through `$this->factory`.
- If Infection ever reports every mutant as an error (`E`) yet a 100% score, mutation testing is broken, not perfect — that happened while `tests/Application/config/bootstrap.php` had a malformed autoload path that plain PHP tolerated but Infection's include interceptor didn't.

## Quality gates

- **PHPStan** runs at level max (Symfony, Doctrine, PHPUnit and Prophecy extensions) without a baseline. When an ignore is unavoidable, scope it to the error identifier and line, with a comment saying why.
- **Code coverage** must be at least 90% for the project and for the lines each change adds or modifies (`codecov.yml`). Both suites count: the `code-coverage` CI job sets up MySQL, the fixtures and the test app's assets like `functional-tests` does.
- **Infection** (`infection.json5`: `minMsi` 99, `minCoveredMsi` 99) runs the unit suite only, because the `mutation-tests` CI job has no database (`testFrameworkOptions`), so logic covered only by functional tests isn't mutated. The thresholds only ever go up: raise them in the same change when tests raise the score. CI runs the global `infection` from setup-php.
- **composer-dependency-analyser** runs with `require-dev` removed: every package used in `src/` or `config/` must be in `require` (e.g. `sylius/channel`, `sylius/inventory`).
- **Dev-only version pins** go in `require-dev`, never `conflict`, and prefer a floor on the fixed package over holding back others. `gedmo/doctrine-extensions: ^3.22.2` is one: older versions declare a template bound that `symfony/type-info` >= 7.4.6 rejects, which breaks loading the API routes (doctrine-extensions/DoctrineExtensions#3044). It can go once `sylius/sylius` requires >= 3.22.2 itself.
- **Symfony LSP:** the shared `coding-standards` CI job runs `symfony lsp:check` because `.symfony-lsp.json` exists. It reports unknown routes, services, templates and translation keys in the plugin and the test app. Two kinds of false positives are suppressed line by line with `{# @symfony-lsp-ignore <code> (…) #}` comments that link the upstream issue: Sylius' translation keys (`translation.not_found`, [symfony/language-tools#121](https://github.com/symfony/language-tools/issues/121): only the `en_US` catalogue is read, not its `en` fallback) and Sylius' anonymous components such as `sylius_shop:main_image` (`twig_component.not_found`, [symfony/language-tools#122](https://github.com/symfony/language-tools/issues/122): Sylius' decorated component template finder is ignored). Once an issue is fixed, remove its ignores (`grep -rn 'language-tools/issues/12[12]' templates`); otherwise suppress only real false positives, with a reason.
- Before pushing dependency changes, sanity-check them with `composer update --prefer-lowest && composer analyse && composer phpunit`.
