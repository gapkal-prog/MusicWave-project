=== MusicWave ===
Contributors: manacore
Tags: block-theme, music, woocommerce, rtl, accessibility
Requires at least: 6.6
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.9.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

MusicWave is a block theme for music catalogs and stores. It uses MusicWave Core for catalog data and supports WooCommerce plus MusicWave VIP protected downloads.

== Changelog ==

= 0.9.0 =
* Feature: the "سربرگ (استریم)" template part (`header-stream`) becomes a streaming-app shell: a vertical side rail at the inline-start edge (right in RTL) with the brand and four destinations (خانه، پیشنهادی، جستجو، موسیقی من) rendered as core navigation blocks with mask icons, plus a slim top bar with the "جستجو در سانگ سرا" search field, the theme toggle and a round account link. The rail collapses to a 72px icon strip with a warm highlight for the current destination; `assets/rail.js` adds the toggle (`aria-expanded`, `aria-controls`), stores the choice in `localStorage` (`musicwave-rail`), preloads it before paint to avoid layout shift, marks custom destinations (search, account) as current, and survives the persistent-player soft navigation. Without JavaScript the rail is simply expanded. Below 64rem the same markup docks as a bottom tab bar above the global player. New tokens `--mw-rail-width*`, `--mw-rail-active`, `--mw-rail-on-active`; new module `components/rail.css`; editor styles keep the rail as an ordinary row in the canvas.
* Feature: new page templates "سفارش آهنگ اختصاصی" (`page-request-song`) and "همکاری" (`page-request-collab`) that compose the Core request form in its dedicated modes (Core 0.14.0+) with a matching FAQ, and "صفحهٔ استریم (نوار کناری)" (`page-stream`) that uses the rail header and opens with an "آلوم جدید" release shelf ("نمایش بیشتر" link) followed by new singles and most-played shelves. All three are repaired automatically on existing installs.

= 0.8.2 =
* New page template "درخواست آهنگ و همکاری" (`page-requests`) composed of the Core request form block plus an FAQ, and a `musicwave/request-cta` pattern for promoting song requests and collaborations on any page. The template is repaired automatically on existing installs like the other custom templates.
* The request form needs MusicWave Core 0.13.0 or newer; without Core the block outputs nothing on the front end and the editor shows the standard missing-block notice.

= 0.8.1 =
* Feature: comments section on the single release template (listener discussion with threaded replies, pagination, and a styled form) via the new `comments.css` module; light and dark palettes covered.
* Improvement: single release page on phones and tablets — the hero no longer bleeds past the viewport, the action bar centers with comfortable tap targets, and the track list renders as a two-line card (title + artist, duration and actions on one row) with a tablet column layout in between.
* Fix: horizontal shelf arrows (`.mw-release-shelf__nav-button`) work in right-to-left layouts; the slider handler measures rows in direction-aware logical pixels, advances by whole visible cards, hides arrows on rows that do not overflow, keeps enabled arrows visible on touch devices, and mirrors SVG chevrons in RTL. Community playlist shelves get the same arrows.
* Design: playlist artwork (`.mw-playlists__art-grid`) is now a fanned cover stack — covers layered like record sleeves that spread on hover/focus — across the account list, the public playlists block (square, landscape, portrait, circle) and client-rendered cards.

= 0.8.0 =
* New: light mode now remaps the theme.json colour presets (dark text on light surfaces) instead of deriving colours; every style variation declares its light and dark palette in `settings.custom.scheme`.
* New: the header search is a 44px icon button that expands on demand (core `button-only` Search block).
* New: theme preference toggle uses three inline SVG icons and keeps every instance (header, footer, sidebar) in sync.
* Fix: one header breakpoint (64rem) for all four header parts; the hamburger overlay is complete, scrollable, admin-bar aware, and no longer disappears between 600px and 767px.
* Fix: sticky headers keep their z-index and admin-bar offset above core's position support; anchors respect `scroll-padding-top`.
* Fix: the global preview player is fully responsive (three-zone bar, docked layout on phones), uses SVG icons, shows elapsed/total time on every viewport, exposes `aria-valuetext`, gains a mute control, and reserves bottom space via `html.mw-has-player`.
* Fix: footer social icons follow the palette presets; corrected a malformed selector in playlists.css.
* Fix: the feature shelf's side-list view counts use the scheme-aware muted token instead of a fixed white, so they stay readable in light mode; the release hero tint re-runs after soft navigations.

= 0.7.4 =
* Fix: registered the public playlists, cart, and checkout templates in the Site Editor.
* Fix: removed duplicate footer widget composition from the sidebar page template.
* Fix: unified presentation block metadata and shared release-card rendering helpers.

= 0.7.2 =
* Fix: removed hardcoded root-relative links in the Footer widgets and Sidebar template parts that pointed to non-existent URLs and escaped the site root on subdirectory installs; starter links now use safe placeholders for editing in the Site Editor.

== Installation ==

1. Install MusicWave Core.
2. Upload and activate this theme.
3. Configure menus and Site Editor templates.
4. Use MusicWave > Setup & diagnostics to verify the store environment.

== Features ==

* Dark, light, and system display preference.
* Four Site Editor style variations: SonicStream, Aurora, Cassette, and Studio.
* Four header template parts: default, centered, minimal, and stream (app-bar).
* Release, artist, genre, shop, and product block templates.
* Native catalog search, taxonomy filters, safe sorting, and active-filter removal.
* Modular component CSS with RTL-friendly logical layout properties.

== Styling in the Site Editor — no code needed ==

**For buyers who are not developers — every color, font, spacing, border, and layout can be changed visually:**

1. Open **Appearance → Editor → Styles** (or **Appearance → Editor** then click Styles).
2. Click **Browse styles** and try **Default, SonicStream, Aurora, Cassette, or Studio** — each instantly recolors the whole site.
3. Click the pencil icon on **Colors, Typography, Layout, or Shadows** and adjust:
   - **Palette**: edit Canvas / Surface / Text / Accent colors live — sliders, shelves, player, and all MusicWave blocks follow automatically via `theme.json` tokens.
   - **Typography**: switch between System Sans / Editorial Serif / Studio Mono and resize Small → Hero with live preview.
   - **Spacing & Borders**: drag spacing (X Small → XX Large) and corner radius per block — no CSS.
   - **Shadows & Gradients**: pick Card / Floating shadows and Accent gradients from the list.
4. Click **Save** — changes apply site-wide; undo is always available in the Site Editor history.

**Templates & Template Parts (header / footer / sidebars):**
- Templates live at **Appearance → Editor → Templates** (Home, Release Archive, Single Release, Artist/Genre archives, Browse, Account, Cart/Checkout, Page with/without sidebar, etc.). Keep MusicWave dynamic blocks in place — their **Inspector (right sidebar)** offers spacing, color, border, and typography without code.
- **Header:** Appearance → Editor → Template Parts → Header — logo, site title, navigation, and the light/dark toggle. Drag to reorder, replace the navigation block, or add social icons. Three alternate parts ship too: **Header (وسط‌چین)** centers the brand over the menu, **Header (ساده)** is a compact logo + menu bar, and **Header (استریم)** is the SonicStream app bar with centered navigation between the brand and the search/toggle cluster.
- **Footer:** Appearance → Editor → Template Parts → Footer — automatically shows the Footer widgets columns + copyright bar. Edit the widget columns inside it, or replace navigation links.
- **Sidebar & Footer widgets (the “Widgets” experience):** Block themes do not use the old **Appearance → Widgets** screen; instead, widget areas are editable template parts:
  - **Appearance → Editor → Template Parts → Sidebar** — add any block: Search, Navigation, Categories, Latest releases (MusicWave shelves), Calendar, Custom HTML, or a **Legacy Widget** block. Used by the **Page with sidebar** template.
  - **Appearance → Editor → Template Parts → Footer widgets** — 4 columns (About / Browse / Support / Stay tuned). Add newsletter, social, recent posts, legacy widgets, or custom HTML. Changes appear on every page using the Footer.
- **Pattern library:** Appearance → Editor → Patterns → MusicWave — prebuilt hero, shelves, sliders, browse categories, footer columns, and sidebar layouts. Click **Add pattern** to insert; all patterns are fully editable after insertion.

**Page templates for buyers:**
- **Default page:** full-width content without sidebar.
- **Page with sidebar:** select **Template → Page with sidebar** in the page editor (Settings → Page → Template). The sidebar column appears automatically; the content spans full width if the Sidebar part is emptied — no custom CSS required.
- **Page (no sidebar):** legacy alias kept for compatibility.
- If a template is broken after heavy customization, open **Appearance → MusicWave template repair** and click **Restore validated theme templates** — posts, releases, menus, and global styles are never deleted.

**Legacy widget compatibility:**
- If you must use a classic widget (provided by another plugin), add a **Legacy Widget** block inside Sidebar or Footer widgets — it renders the classic widget without code. Classic sidebars `musicwave-sidebar` and `musicwave-footer-widgets` are also registered for plugins that call `dynamic_sidebar()` directly.
