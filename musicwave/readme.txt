=== MusicWave ===
Contributors: manacore
Tags: block-theme, music, woocommerce, rtl, accessibility
Requires at least: 6.6
Tested up to: 6.8
Requires PHP: 7.4
Version: 0.5.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

MusicWave is a block theme for music catalogs and stores. It uses MusicWave Core for catalog data and supports WooCommerce plus MusicWave VIP protected downloads.

== Installation ==

1. Install MusicWave Core.
2. Upload and activate this theme.
3. Configure menus and Site Editor templates.
4. Use MusicWave > Setup & diagnostics to verify the store environment.

== Features ==

* Dark, light, and system display preference.
* Three Site Editor style variations: Aurora, Cassette, and Studio.
* Release, artist, genre, shop, and product block templates.
* Native catalog search, taxonomy filters, safe sorting, and active-filter removal.
* Modular component CSS with RTL-friendly logical layout properties.

== Styling in the Site Editor ==

1. Open Appearance > Editor > Styles.
2. Select Browse styles and choose the default design, Aurora, Cassette, or Studio.
3. Use Colors, Typography, and Layout to adjust the palette, font family, spacing, and block appearance tools.

The style variation updates the shared `theme.json` presets. MusicWave Core dynamic blocks consume those presets through their WordPress block wrapper attributes and the theme CSS tokens, so their colors, spacing, borders, and typography remain aligned with the selected style.

Templates for the home page, release archive and single release, artist taxonomy, and genre taxonomy are editable from Appearance > Editor > Templates. Keep the MusicWave dynamic blocks in place when editing a template; their Inspector controls provide supported per-block spacing, color, border, and typography changes.

== The Music sidebar ==

The vertical Music sidebar is a standard template part. Add, move, or remove blocks inside it under Appearance > Editor > Template Parts > Music sidebar. It accepts any block (search, navigation, headings, images, custom HTML, and MusicWave blocks).

To remove the sidebar from an individual page:

1. Edit the page, open Settings > Page in the sidebar.
2. In Template, select "Page (no sidebar)".
3. The two-column grid is only applied when the sidebar exists, so the content group automatically spans the full template width — no custom CSS is required.

Alternatively, in the Site Editor you can delete the "Music sidebar" block from any of the shipped templates. If you delete or break a bundled template-part file, open Appearance > MusicWave template repair to restore the validated theme files.
