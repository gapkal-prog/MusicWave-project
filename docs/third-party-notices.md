# Third-party code and asset provenance

Status: verified 2026-08-19 during Phase 2 Stage 0 (PROJECT_PLAN.md).

## Shipped runtime code

The distributable packages (`music-wave-core`, `music-wave-vip`, `musicwave`) contain **only
first-party code and assets**. A grep audit of `music-wave-core/assets` and `musicwave/assets`
found no bundled third-party libraries, no vendored minified scripts, and no external license
headers. All JavaScript is hand-rolled against WordPress-provided `wp-*` script packages, which
are supplied by WordPress core at runtime and are not redistributed here.

Rules for future changes:

1. Any bundled third-party library, font, icon set, or image must be GPL-2.0-or-later
   compatible and must be recorded in this file with name, version, source URL, and license.
2. Protected master audio, demo audio, and demo artwork must never be committed to the
   repository or included in release archives.
3. Release packaging (Stage 7) must re-verify this inventory before producing archives.

## Development-only dependencies (not distributed)

Composer (`composer.json`, dev):

| Package | License |
|---|---|
| dealerdirect/phpcodesniffer-composer-installer | MIT |
| php-stubs/wordpress-stubs | MIT |
| phpstan/phpstan | MIT |
| phpcompatibility/php-compatibility(-wp) | LGPL-3.0-or-later |
| squizlabs/php_codesniffer | BSD-3-Clause |
| wp-coding-standards/wpcs | MIT |

npm (`package.json`, dev):

| Package | License |
|---|---|
| @playwright/test | Apache-2.0 |
| @wordpress/e2e-test-utils-playwright | GPL-2.0-or-later |
| @wordpress/scripts | GPL-2.0-or-later |

These tools run only on developer and CI machines; none of their code enters the release
archives, so their licenses impose no distribution obligations on the shipped product.

## Product licensing

All three shipped packages and the repository root are licensed **GPL-2.0-or-later**
(see `LICENSE`). The former `proprietary` marker in the root `composer.json` was a
contradiction with the shipped readme headers and was corrected in Stage 0.
