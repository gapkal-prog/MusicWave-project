# Translations and RTL release process

MusicWave ships three independent text domains:

| Component | Text domain | POT output |
|---|---|---|
| Theme | `musicwave` | `musicwave/languages/musicwave.pot` |
| Core | `music-wave-core` | `music-wave-core/languages/music-wave-core.pot` |
| VIP | `music-wave-vip` | `music-wave-vip/languages/music-wave-vip.pot` |

## Generate templates

Run:

```bash
composer make-pot
```

The command regenerates every POT from the current PHP and JavaScript source without adding a runtime dependency. Review and commit changed POT files with the matching code change. Translation files are distribution assets and are included in release archives.

Theme default copy is rendered through presentation-only `musicwave/theme-text` and `musicwave/theme-toggle` blocks, so default template headings, empty states, and accessibility labels can be translated without placing domain logic in templates. Merchant-edited template content remains ordinary WordPress content and is intentionally not overwritten.

## Persian and RTL QA

Before a Persian release, install the translated MO files, set the site language to Persian, and check:

1. Theme toggle, default homepage heading, 404 page, cart, checkout, account, and footer strings.
2. Release filters, sort labels, active-filter removal labels, result counts, access states, and download errors.
3. Artist metadata, collection ordering, preview player controls, account library, and WooCommerce checkout.
4. Keyboard focus order, logical CSS spacing, mixed Latin catalog numbers, dates, and audio durations.
5. The same pages in LTR after changing the site language back to English.

Do not translate taxonomy slugs, release-type slugs, opaque asset identifiers, membership keys, download tokens, REST namespaces, or query variables such as `mw_sort`.
