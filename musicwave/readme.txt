=== MusicWave ===
Contributors: manacore
Tags: block-theme, music, woocommerce, rtl, accessibility
Requires at least: 6.6
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.7.2
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

MusicWave is a block theme for music catalogs and stores. It uses MusicWave Core for catalog data and supports WooCommerce plus MusicWave VIP protected downloads.

== Changelog ==

= 0.7.2 =
* Fix: removed hardcoded root-relative links in the Footer widgets and Sidebar template parts that pointed to non-existent URLs and escaped the site root on subdirectory installs; starter links now use safe placeholders for editing in the Site Editor.

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

== Styling in the Site Editor — no code needed ==

**For buyers who are not developers — every color, font, spacing, border, and layout can be changed visually:**

1. Open **Appearance → Editor → Styles** (or **Appearance → Editor** then click Styles).
2. Click **Browse styles** and try **Default, Aurora, Cassette, or Studio** — each instantly recolors the whole site.
3. Click the pencil icon on **Colors, Typography, Layout, or Shadows** and adjust:
   - **Palette**: edit Canvas / Surface / Text / Accent colors live — sliders, shelves, player, and all MusicWave blocks follow automatically via `theme.json` tokens.
   - **Typography**: switch between System Sans / Editorial Serif / Studio Mono and resize Small → Hero with live preview.
   - **Spacing & Borders**: drag spacing (X Small → XX Large) and corner radius per block — no CSS.
   - **Shadows & Gradients**: pick Card / Floating shadows and Accent gradients from the list.
4. Click **Save** — changes apply site-wide; undo is always available in the Site Editor history.

**Templates & Template Parts (header / footer / sidebars):**
- Templates live at **Appearance → Editor → Templates** (Home, Release Archive, Single Release, Artist/Genre archives, Browse, Account, Cart/Checkout, Page with/without sidebar, etc.). Keep MusicWave dynamic blocks in place — their **Inspector (right sidebar)** offers spacing, color, border, and typography without code.
- **Header:** Appearance → Editor → Template Parts → Header — logo, site title, navigation, and the light/dark toggle. Drag to reorder, replace the navigation block, or add social icons.
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
