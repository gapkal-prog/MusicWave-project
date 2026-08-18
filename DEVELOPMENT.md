# MusicWave development guide

MusicWave is ManaCore's commercial WordPress music platform: a block theme for presentation and a companion plugin for catalog, player, commerce, access, and integration logic.

## Repository layout

- `musicwave/`: block theme templates, patterns, global styles, and presentation.
- `music-wave-core/`: catalog, player, application services, public APIs, and integration contracts.
- `music-wave-vip/`: role-based membership and protected-download integrations.
- `MusicWave-Backlog.md`: product scope, architecture decisions, milestones, and acceptance criteria.

## Requirements

- PHP 7.4 or newer; PHP 8.2 is the primary local development runtime.
- WordPress 6.6 or newer.
- WooCommerce 9.x or newer for commerce features.
- Composer 2 for development quality tools.
- Node.js 20+ / npm for JavaScript linting and the Playwright E2E suite (`npm install`).

## JavaScript tooling decision (Phase 2)

MusicWave ships **dependency-free, hand-rolled editor scripts** (`wp.element.createElement` + a declarative `fieldConfig` registry) instead of a compiled JSX build. Rationale:

- The runtime depends only on WordPress-provided `wp-*` script packages, so there is no build artifact to ship, version, or audit in the marketplace zip.
- `tests/template-integrity.php` asserts exact parity strings inside `music-wave-core/assets/blocks.js`; keeping the source hand-written keeps that contract directly verifiable.
- Supply-chain exposure stays at zero for a commercial product.

`@wordpress/scripts` is therefore used as a **quality gate, not a compiler**:

```bash
npm install
npm run lint:js        # ESLint over plugin + theme JS assets
npm run lint:js:fix
npm run build          # CI alias that runs the lint gate
composer check:js      # runs the same gate, skipping cleanly when node_modules is absent
```

The ESLint profile (`.eslintrc.json`) extends `plugin:@wordpress/eslint-plugin/recommended` with ES5/script-mode relaxations suited to the hand-rolled runtime files.

## Editor E2E tests (Playwright)

The suite in `tests/e2e/` covers every MusicWave dynamic block: insert on a page, change one inspector setting, publish, and assert the saved attributes plus the front-end markup where the block renders without release context.

```bash
npx playwright install chromium   # once
# start the staging install (see docs/staging.md), then:
npm run test:e2e
```

Connection settings default to `docs/staging.md` values and can be overridden via `WP_BASE_URL`, `WP_USERNAME`, `WP_PASSWORD`.

> Note: the committed JS sources are validated by `node --check`-compatible syntax at all times; `npm install` requires registry access, so the ESLint/Playwright gates activate on machines that can reach the npm registry.

## Current status

MusicWave Core `0.7.0` provides the canonical release catalog, WooCommerce product mapping, access decisions, multi-quality protected downloads, collection relationships, global public preview player, and type-aware release authoring. The block theme provides single and archive experiences, including native taxonomy search, safe archive sorting, result counts, and removable active filters.

The release archive deliberately relies on WordPress-native archive queries. It allow-lists only `latest`, `oldest`, `title_asc`, and `title_desc` for the `mw_sort` query argument, and never queries or exposes protected access metadata. The translation pipeline and RTL QA guidance are now available. Full compatibility QA and the final license/asset audit remain before a public release.

## Local checks

```bash
composer validate --strict
composer check:syntax
composer install
composer check:phpcs
composer check:phpstan
composer make-pot
```

No production Composer dependency is required by the current runtime.

See `docs/translations.md` for the text-domain contract, dependency-free POT generation, and Persian/RTL release checklist.
See `docs/download-qualities-and-hosting.md` for the protected multi-quality workflow, shared-hosting boundaries, and WooCommerce membership adapter contract.

## Architecture rules

- Business logic and persistent data never belong in the theme.
- WordPress callbacks remain thin and delegate to modules/services.
- Input is sanitized and validated at boundaries; dynamic output is escaped at render time.
- Restricted resources use deny-by-default policies and never expose a direct private-file URL.
- Public code remains PHP 7.4 compatible until the version policy changes in the backlog.

## Distribution

License and marketplace packaging are not finalized. Do not distribute a release package until M10's licensing and third-party asset audit is complete.
