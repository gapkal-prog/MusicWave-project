# MusicWave — راهنمای ارتقاء حرفه‌ای بلوک‌ها | Professional Block Upgrade Roadmap

> این سند دو بخش دارد: بخش اول **فارسی** برای مالک پروژه، بخش دوم **انگلیسی** برای عامل هوشمند (Agent) تا در جلسه‌های بعدی دقیقاً از همین نقطه ادامه دهد.
> This document has two parts: Part 1 (Persian) for the project owner, Part 2 (English) as an actionable continuation spec for any AI agent working on this repository.

- تاریخ آخرین به‌روزرسانی / Last updated: 2026-08-16
- وضعیت فعلی / Current status: `tests/run.php` ✅ PASS — `PHPStan level 3 (2G)` ✅ 0 errors — `PHPCS` ✅ 0 errors / 0 warnings — فاز ۰ و ۱ و ۲ کامل شدند (block.json، دسته بلوک، Style Variations، ابزارسازی npm/ESLint/Playwright). ادامه: فاز ۳

---

# بخش ۱ — فارسی (برای شما)

## ۱. آیا هنوز می‌شود حرفه‌ای‌تر کرد؟

**بله، زیاد.** بلوک‌ها از نظر «تنظیمات اینسپکتور» در جلسه قبل کامل شدند، اما برای رسیدن به سطح **قالب‌ها و افزونه‌های تجاری مارکت‌هایی مثل ThemeForest / WooCommerce Marketplace / WordPress.org** هنوز این فاصله‌ها وجود دارد:

| # | وضعیت فعلی | استاندارد محصولات جهانی |
|---|------------|--------------------------|
| ۱ | ثبت بلوک فقط با PHP (`register_block_type` آرایه‌ای) | متادیتای `block.json` برای هر بلوک (استاندارد وردپرس ۶+ و الزامی برای دایرکتوری رسمی و اکثر مارکت‌ها) |
| ۲ | جاوااسکریپت ادیتور دست‌نویس با `createElement` بدون ابزار build | استفاده از `@wordpress/scripts` (wp-scripts) یا حداقل فرایند build و فایل‌های کامپایل‌شده |
| ۳ | بدون Style Variations و Block Examples در Inserter | `styles`، `example`، `variations` برای پیش‌نمایش هنگام درج بلوک |
| ۴ | PHPCS روی کل مخزن خطا می‌دهد (خطاهای قدیمی نام‌گذاری فایل و داک‌بلاک) | قوانین دقیق‌شده در `phpcs.xml` + عبور کامل CI |
| ۵ | بدون تست E2E / Playwright | حداقل چند سناریوی E2E برای ادیتور |
| ۶ | i18n فقط `.pot` سمت سرور | ترجمه اسکریپت‌های JS با `wp_set_script_translations` |
| ۷ | بدون Lazy loading هوشمند دارایی‌ها | `should_load_separate_core_block_assets`, defer کردن اسکریپت پلیر |

## ۲. نقشه راه (به ترتیب اولویت)

### فاز ۰ — پاک‌سازی بدهی کیفیت 
هدف: تمام دروازه‌های کیفیت (Quality Gates) بدون خطا شوند.
1. **اصلاح `phpcs.xml`**: اسنیف‌های «نام فایل با حروف کوچک» و «پیشوند class-» با معماری PSR-4 پروژه در تضاد ذاتی هستند؛ باید با `<exclude-pattern>` یا `<exclude name="WordPress.Files.FileName"/>` فقط برای دایرکتوری `src/` غیرفعال شوند (نه حذف کلی استاندارد).
2. **خط ۱۱۷۰ در `ReleaseBlocks.php`**: خواندن `$_GET[mw_sort]` بدون sanitize از نظر PHPCS — باید با `sanitize_key()` یا همان `normalize_sort()` قبل از استفاده پوشش داده شود و کامنت ` translators:` برای رشته‌های دارای placeholder اضافه شود.
3. **اسکریپت composer**: اضافه کردن `--memory-limit=1G` به `check:phpstan` (PHPStan با 512M کرش می‌کند).
4. اجرای `composer make-pot` برای رشته‌های جدید جلسه قبل.

### فاز ۱ — مدرن‌سازی بلوک‌های ادیتور 
1. **`block.json` برای هر ۱۴ بلوک**: فایل `music-wave-core/blocks/<name>/block.json` شامل `name, title, category, icon, description, keywords, attributes, supports, example`. ثبت از مسیر فایل: `register_block_type( __DIR__ . '/blocks/release-meta' )`. مزیت: شناسایی خودکار توسط وردپرس، سازگاری با ابزارهای آینده، استاندارد مارکت.
2. **Block Examples**: خصوصیت `example` در block.json تا هنگام هاور در Inserter پیش‌نمایش واقعی دیده شود.
3. **Style Variations**: ثبت `register_block_style` برای دکمه پیش‌نمایش (solid/outline/ghost به‌جای attribute سفارشی — یا نگهداری attribute برای سازگاری عقب‌گرد و افزودن style به‌عنوان جایگزین مدرن).
4. **دسته‌بندی اختصاصی بلوک**: ثبت دسته `music-wave` در Inserter با آیکون اختصاصی.
5. **پنل‌های اینسپکتور پیشرفته‌تر**: `__experimentalBorder`، پشتیبانی از Gradient در `BlockSupport::appearance_tools()`، و `InspectorControls` گروه‌بندی‌شده با `PanelBody` (الگوی `fieldConfig` فعلی حفظ شود — فقط گسترش یابد).
6. ** هماهنگی با تنظیمات سراسری**: هر بلوک باید حالت «Inherit from settings» داشته باشد (الگوی `visibility_attribute` موجود است و باید به همه بلوک‌ها تعمیم یابد).

### فاز ۲ — ابزارسازی و Build 
1. افزودن `package.json` با `@wordpress/scripts` و اسکریپت‌های `build`/`start`/`lint:js`.
2. انتقال `blocks.js` به ساختار `src/` یا نگهداری نسخه دست‌نویس + مستندسازی دلیل آن (تصمیم معماری — هر دو قابل دفاع هستند؛ برای مارکت رسمی، build ترجیح داده می‌شود).
3. افزودن Playwright با `@wordpress/e2e-test-utils` برای سناریوهای: درج بلوک، تغییر تنظیمات، ذخیره، رندر فرانت.

### فاز ۳ — بین‌المللی‌سازی و دسترسی‌پذیری 
1. ترجمه JS با `wp_set_script_translations()` و تولید فایل‌های `languages/music-wave-core-<locale>-<handle>.json`.
2. بازبینی a11y: کنتراست رنگ‌ها (WCAG AA)، `aria-live` برای نتایج فیلتر کاتالوگ، مدیریت فوکوس در پلیر پیش‌نمایش.
3. پشتیبانی کامل RTL (قالب باید در حالت RTL سایت‌های فارسی/عربی بی‌نقص باشد — تست دستی الزامی).

### فاز ۴ — بسته‌بندی تجاری 
1. فایل `LICENSE` (هنوز وجود ندارد — برای فروش الزامی است).
2. یکسان‌سازی نسخه در `readme.txt` هر سه محصول + هدر پلاگین‌ها + یک Constant مرکزی.
3. مستندسازی هر بلوک در `docs/` به‌صورت «Block Reference» (مشخصات، attributeها، مثال).
4. اعتبارسنجی خروجی `tools/package-release.php` با ابزار رسمی مارکت هدف.


## ۳. معیارهای پذیرش «سطح جهانی»

- [ ] `composer check:syntax && composer test && composer check:phpcs && composer check:phpstan` همه سبز
- [ ] هر بلوک دارای `block.json` + آیکون + کلمات کلیدی + example
- [ ] هر رشته ترجمه‌پذیر در `.pot` موجود باشد (تولید خودکار)
- [ ] هر attribute جدید در ۴ جا باشد: PHP سرور + `Rendering.php` + `blocks.js` + CSS (قانون Parity که تست `template-integrity.php` آن را تضمین می‌کند)
- [ ] قالب در RTL و LTR و هر ۳ Style Variation (studio/aurora/cassette) بی‌نقص رندر شود
- [ ] مستندات `docs/` به‌روز باشد

---

# Part 2 — English (Agent Continuation Spec)

> **Purpose**: Any agent continuing this project must read this section first, then follow the phases in order. Do NOT deviate from the established architectural patterns listed in §2.3.

## 2.1 Verified Current State (2026-08-16, post Phase 0 + Phase 1)

- `php tests/run.php` → "MusicWave domain smoke tests passed." ✅
- `phpstan analyse --configuration=phpstan.neon --memory-limit=2G` → 0 errors ✅ (NOTE: PHPStan now needs **2G**; 1G crashes the parallel worker. The composer script `check:phpstan` carries the flag.)
- `phpcs --standard=phpcs.xml music-wave-core musicwave` → ✅ exit 0, zero errors and warnings. PSR-4 `src/` trees are excluded from `WordPress.Files.FileName`; WordPress docblock-layout sniffs are excluded (PHPDoc-style annotations are the project standard); every security sniff remains active with point fixes or justified inline ignores.
- `DemoContentImporter::$result` is typed with the array shape `array{created: int, existing: int, skipped: int, removed: int, messages: array<int, string>}` on all by-ref params (fixes PHPStan `paramOut.type` widening).
- `ReleaseRepository` interface now declares `replace_collection_items()`; the `method_exists()` guard in `DemoContentImporter::sync_collection()` was intentionally removed (owner edit). Keep it removed.
- Every dynamic block registers through `BlockSupport::register_dynamic()`: the bundled `blocks/<name>/block.json` is the metadata source of truth; the PHP attribute arrays remain ONLY as a fallback for installs missing `blocks/` and as the parity anchor. `DownloadTokenService` keeps `json_encode()` on purpose (WP-stub domain tests) with a justified ignore.

## 2.2 Block Inventory (14 dynamic blocks)

| Block name | Registered in | Renderer |
|---|---|---|
| `music-wave/release-meta` | `ReleaseBlocks.php` | `render_meta` |
| `music-wave/access-panel` | `ReleaseBlocks.php` | `render_access_panel` |
| `music-wave/release-credits` | `ReleaseBlocks.php` | `render_credits` |
| `music-wave/collection-list` | `ReleaseBlocks.php` | `render_collection_list` |
| `music-wave/catalog-filters` | `ReleaseBlocks.php` | `render_catalog_filters` |
| `music-wave/catalog-results` | `ReleaseBlocks.php` | `render_catalog_results` |
| `music-wave/preview-player` | `ReleaseBlocks.php` | `render_preview_player` |
| `music-wave/download-button` | `ReleaseBlocks.php` | `render_download_button` |
| `music-wave/related-releases` | `ReleaseBlocks.php` | `render_related_releases` |
| `music-wave/preview-button` | `PreviewPlayer.php` | via `button_markup` |
| `music-wave/artist-profile` | `ArtistProfileBlock.php` | `render` |
| `music-wave/music-library` | `LibraryBlocks.php` | — |
| `music-wave/library-button` | `LibraryBlocks.php` | — |
| account library block | `Commerce/AccountLibrary.php` | — |

## 2.3 Architecture Patterns — DO NOT DEVIATE

1. **Server registration**: `ReleaseBlocks::register_dynamic_block( $name, $attributes, $callback )` — API v3, `uses_context: [postId, postType]`, `supports: BlockSupport::appearance_tools()`.
2. **Attribute helpers** (in `ReleaseBlocks.php`): `bool_attribute`, `text_attribute`, `key_attribute`, `range_attribute`, `message_attribute` (block attr → global `Settings::get()` → fallback), `visibility_attribute` (tri-state inherit/enabled/disabled), `url_attribute`. Always add new attributes through these helpers.
3. **Editor parity contract**: every attribute MUST exist in (a) the server block PHP, (b) `Modules/Rendering.php::editor_blocks()` localization of `musicWaveDynamicBlocks`, (c) `assets/blocks.js` inspector config, (d) theme CSS. `tests/template-integrity.php` asserts this — extend it for every new attribute.
4. **Editor JS**: hand-rolled `wp.element.createElement` + declarative `fieldConfig` descriptors `['text'|'intText'|'toggle'|'select'|'range', attr, label, ...]` with `groups` arrays of PanelBody sections; `ServerSideRender` for previews. Preserve test-required strings `'music-wave/release-meta': {` and `showCatalogNumber`.
5. **CSS**: BEM-style `mw-*` classes with `--modifier` variants, theme vars (`--mw-color-accent`, `--wp--preset--spacing--*`, `color-mix()`). Reuse shared shelf/layout classes across blocks instead of new ones.
6. **Backward compatibility**: all changes additive; modifier classes appended only when non-default; optional trailing params on shared methods.
7. **Settings harmony**: default sort / labels resolve through `Settings::get()` (option `music_wave_settings`, e.g. `archive_default_sort` normalized via `ReleaseArchiveQuery::normalize_sort()`).

## 2.4 Phase 0 — Quality Debt Cleanup (DONE 2026-08-16)

- [x] `phpcs.xml`: PSR-4 `src/` trees excluded from `WordPress.Files.FileName`; WordPress docblock-layout sniffs excluded; `.staging/`, `tests/`, `tools/` excluded from the default file set; `WordPress.WP.Capabilities` learned the custom `edit_mw_releases` capability via `custom_capabilities`.
- [x] All real findings fixed: `translators:` comments added, `$_GET`/`$_POST` reads sanitized at the point of read (`sanitize_key()`/`sanitize_title()`/`absint()`), short ternaries expanded in `AccessPolicyEngine.php` and `musicwave/functions.php`, reserved-keyword params renamed (`$class` → `$class_name`, `$default` → `$default_value`), escape-output findings escaped or justified, 486 formatting violations fixed by `phpcbf`.
- [x] `composer.json` scripts: `check:phpstan` carries `--memory-limit=2G`.
- [x] `composer make-pot` regenerated for all three products.
- [x] Verified: `composer check:syntax`, `composer test`, `composer check:phpcs` (exit 0), `composer check:phpstan` all green.

## 2.5 Phase 1 — Editor Block Modernization (DONE 2026-08-16)

1. [x] **block.json migration**: all 14 blocks ship `music-wave-core/blocks/<name>/block.json` with `$schema`, `apiVersion: 3`, `name`, `title`, `category: music-wave`, `description`, `keywords`, `icon`, `attributes` (exact copies of the PHP maps), `supports` (mirrors `BlockSupport::appearance_tools()`), `usesContext`, `example`, and `textdomain`. Registration goes through `BlockSupport::register_dynamic()` which loads the metadata folder and attaches only the PHP `render_callback`; PHP attribute arrays remain as fallback + parity anchor. `Rendering.php::editor_blocks()` enriches the hand-rolled JS registry with `category`/`example` from block.json at runtime (single source of truth). `tests/template-integrity.php` now asserts block.json existence, required metadata fields, and attribute parity with `Rendering.php` for all 14 blocks.
2. [x] **Inserter experience**: `example` in every block.json; dedicated `music-wave` block category registered via `block_categories_all` (Rendering::register_block_category); `register_block_style()` variations registered for `preview-player` + `preview-button` (outline/ghost), `release-meta` (inline/stack), `catalog-filters` (stacked). Renderers translate the chosen `is-style-*` class into the existing modifier classes via `BlockSupport::style_variation()` — the variation takes precedence over the legacy attribute when selected.
3. [x] **Inspector upgrades in `blocks.js`**: declarative `fieldConfig` extended — `text`/`select` descriptors now support per-control help; help texts added for catalog-results, account-dashboard, preview style/icon, and layout selects (with Styles-panel cross-references); label help documents the inherit-from-MusicWave-defaults behavior. Client registration now uses category `music-wave` and safely replaces a server-hydrated bare block type (unregister + re-register, preserving server `styles`/`example`).
4. [x] **Accessibility**: hidden labels stay `screen-reader-text`; new toggles that hide visible text keep SR-only counterparts; help copy documents the a11y behavior.

Next continuation point: Phase 3 (i18n JS translations, a11y/RTL audit), then Phase 4. On a machine with npm registry access, finish the Phase 2 operator follow-up listed in §2.6.

## 2.6 Phase 2 — Build Tooling (DONE 2026-08-16)

- [x] `package.json` added with `@wordpress/scripts`, `@playwright/test`, and `@wordpress/e2e-test-utils-playwright`; scripts: `lint:js`, `lint:js:fix`, `build` (lint gate alias), `test:e2e`, `test:e2e:headed`.
- [x] **Decision (option b)**: the dependency-free hand-rolled editor scripts are KEPT; `@wordpress/scripts` serves as a lint gate, not a compiler. Rationale documented in `DEVELOPMENT.md` (marketplace zip stays artifact-free, parity test strings stay verifiable, zero supply-chain exposure). ESLint profile in `.eslintrc.json` extends `plugin:@wordpress/eslint-plugin/recommended` with ES5/script-mode relaxations.
- [x] Playwright E2E added: `tests/e2e/playwright.config.js` + `tests/e2e/editor-blocks.spec.js` — data-driven scenario per block: insert on a page, change one inspector setting, assert the saved attribute fragment, publish, and assert front-end markup (`.mw-catalog-filters--stacked`, `.mw-music-library`, dashboard/catalog-results wrappers) or a clean no-PHP-fatal load for context-bound blocks. Targets the staging install from `docs/staging.md` (`WP_BASE_URL`/`WP_USERNAME`/`WP_PASSWORD` overridable).
- [x] Soft composer gate `check:js` (`tools/check-js.php`) runs `npm run lint:js` when `node_modules` exists and skips cleanly otherwise; all committed JS is `node --check` valid.
- Operator follow-up on a machine with npm registry access: `npm install`, `npm run lint:js` (tighten `.eslintrc.json` rules if warnings appear), `npx playwright install chromium`, start staging, `npm run test:e2e`.

## 2.7 Phase 3 — i18n / a11y / RTL

- `wp_set_script_translations( 'music-wave-blocks', 'music-wave-core', plugin_dir_path(...) . 'languages' )` for the editor script; generate JSON translation files.
- RTL audit of all `mw-*` component CSS (logical properties `margin-inline-start` etc. where directional hardcoding exists).
- WCAG AA contrast check for badge/pill colors (`--outline`, `--ghost`, `__count` use `color-mix()` — verify on all three style variations studio/aurora/cassette).

## 2.8 Phase 4 — Marketplace Packaging

- Add `LICENSE` at repo root and reference it in both plugin headers + theme `style.css`.
- Single source of truth for version: define once, inject into `readme.txt` Stable tag and file headers via `tools/package-release.php` (verify the script already does this; if not, add).
- Write `docs/block-reference.md`: one section per block — attributes table (name/type/default/description), supports, screenshot path, since-version.

## 2.9 Verification Checklist (run before declaring any phase done)

```powershell
php tests\run.php
php vendor\bin\phpstan analyse --configuration=phpstan.neon --no-progress --memory-limit=1G
php vendor\bin\phpcs --standard=phpcs.xml music-wave-core musicwave
php tools\check-templates.php
```

Plus for any block touched: confirm the four parity locations (§2.3.3) and a manual render on staging (`.staging/` smoke flow, `tests/staging-smoke.php`).

## 2.10 Known Gotchas (learned the hard way)

- Never run `php tests/template-integrity.php` directly — `mw_assert_same()` is defined by `tests/run.php`.
- `@var` annotations do NOT override PHPStan tracking of by-reference params; use precise array-shape `@param` types instead (see `DemoContentImporter`).
- PHPStan needs `--memory-limit=2G` here (1G now crashes the parallel worker).
- WPCS doc-comment alignment sniffs require exact column alignment of `$param` names AND descriptions; iterate with phpcs output ("Expected N spaces...").
- `get_block_wrapper_attributes()` + custom classes: append modifier classes conditionally, never replace the default block class.
- PowerShell only: use `;` not `&&`.
- `register_block_type( $folder, $args )` merges `$args` OVER block.json — pass only `render_callback` there, or PHP arrays silently shadow the metadata file.
- `tests/run.php` stubs a minimal WP environment: do not swap `json_encode()` for `wp_json_encode()` in code paths the domain tests exercise (DownloadTokenService).
- When editing attribute lists, keep all parity locations in sync: block.json + PHP fallback arrays + `Rendering.php` + `blocks.js` fieldConfig + theme CSS; the integrity test now validates block.json ↔ Rendering.php automatically.

## 2.11 Definition of Done (marketplace-grade)

The project is "marketplace-ready" when: all four quality gates are green, every block ships a `block.json` with icon/keywords/example, the `.pot` + JS translation pipeline is wired, `LICENSE` exists, `docs/block-reference.md` covers all 14 blocks, and RTL + 3 style variations render without visual regressions.
