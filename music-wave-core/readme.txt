=== MusicWave Core ===
Contributors: manacore
Tags: music, catalog, releases, woocommerce, downloads
Requires at least: 6.6
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.11.3
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

MusicWave Core provides catalog data, release relationships, WooCommerce mapping, customer music library, access decisions, secure-download contracts, onboarding, and diagnostics for the MusicWave theme.

== Changelog ==

= 0.11.3 =
* Improvement: the persistent preview player renders inline SVG controls, a separate duration read-out, a mute button, and state attributes (`data-state`, `data-mw-volume`) so the theme can style playing, paused, loading and muted states without text glyph swaps.
* Improvement: the seek bar announces "elapsed / total" through `aria-valuetext`; buffering shows a spinner while the toggle stays usable; `html.mw-has-player` flags an active player for layout offsets.
* Fix: persistent navigation now keeps the already-hydrated site header (hamburger overlay, expanding search) alive across soft page swaps and only refreshes its current-item markers, so the mobile menu keeps working after the first in-place navigation; pages with other Interactivity API regions (lightbox images, enhanced query pagination) fall back to a native load instead of arriving inert.
* Fix: soft navigations merge the incoming page's block-support and per-block stylesheets before painting, execute newly required scripts in dependency order before `mw-page-rendered`, sync `lang`/`dir`/`data-mw-scheme` on `<html>`, and tag history entries with the Interactivity API session id so browser back/forward no longer forces a reload.
* Maintenance: the JavaScript lint gate (`npm run lint:js`) passes again across both packages.

= 0.11.2 =
* Maintenance: normalized the editor asset formatting so the JavaScript quality gate passes cleanly.

= 0.11.1 =
* Maintenance: indexed catalog, playlist, and listening integrations.

== Installation ==

1. Upload and activate MusicWave Core.
2. Activate the MusicWave block theme.
3. Save WordPress permalinks.
4. Open MusicWave > Setup & diagnostics and resolve any required checks.
5. Activate MusicWave VIP before assigning private download assets.

== Privacy ==

Core does not persist download audit events by default. Integrations that store `music_wave_download_event` data must provide data retention plus exporter and eraser behavior.

== Changelog ==

= 0.11.1 =
* Fix: URLs generated from the current request no longer double the install folder on subdirectory installs (playlist toggles, sign-in redirects, queue redirects, and History API updates now resolve correctly).
* Fix: instant catalog filtering re-dispatches `mw-page-rendered` after swapping results, so the search suggest combobox and other re-entrant enhancements rebind to the fresh markup.

= 0.11.0 =
* New: dedicated playlist tables (schema 0.11.0) with a per-user playlist manager, public playlist directory with instant search and pagination, add-to-playlist picker, and shareable playlists with share tokens.
* New: continue-listening block — server-rendered guest, consent, and history states with a one-click REST opt-in.
* Hardening: atomic download rate limiting, per-identity guest quota buckets, one-time download tokens are consumed only after the quota check passes, and expired opaque tickets are pruned daily.
* Hardening: follow notifications fan out in deferred batches, pre-save fulfillment is deferred off the publish request, and metadata lookup is capability-gated and rate-limited.
* Fixes: share tokens rotate only when playlist visibility changes, playlist item swaps are transactional, library and playlist cleanup is post-type-guarded, and download asset lists are capped and validated before persistence.

= 0.10.0 =
* New: indexed listening-activity table (schema 0.10.0) with explicit consent controls, a durable playback queue with no-JavaScript reorder/remove/shuffle/repeat forms, and progress plus continue-listening REST endpoints.
* New: wishlist and pre-save library types with automatic fulfillment when a pre-saved release ships.
