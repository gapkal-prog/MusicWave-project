=== MusicWave Core ===
Contributors: manacore
Tags: music, catalog, releases, woocommerce, downloads
Requires at least: 6.6
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.13.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

MusicWave Core provides catalog data, release relationships, WooCommerce mapping, customer music library, access decisions, secure-download contracts, onboarding, and diagnostics for the MusicWave theme.

== Changelog ==

= 0.13.0 =
* Fix: site search now finds Persian and Arabic-script content regardless of the keyboard used. Every search word is expanded into its equivalent spellings (Arabic vs Persian ye/kaf, zero-width non-joiner vs space, Persian/Arabic/Latin digits, diacritics, kashida, heh variants) at the SQL layer, artist/genre/mood/label/type term names and descriptive release metadata (album, catalog number, ISRC, credits) are matched through bounded sub-queries, and the header autocomplete applies the same normalization. Latin-only phrases keep core behaviour. Filters: `music_wave_script_aware_search`, `music_wave_search_taxonomies`, `music_wave_search_meta_keys`; query vars `mw_script_search`, `mw_search_taxonomies`, `mw_search_meta_keys`.
* Feature: custom song request and collaboration module. New `music-wave/request-form` block (progressive, works without JavaScript, honeypot + timing + per-actor rate limit, field-level validation, post/redirect/get with a random token instead of personal data in the URL, receipt email with a `MW-YYYY-000000` reference) and a full management screen under MusicWave → Requests & collaboration (pinned above MusicWave VIP): unread badge, statistics, status tabs, type/role/priority/search filters, bulk actions, detail view with email replies and canned templates, internal notes, accept/decline/archive, field editing, manual entry, settings (recipients, Reply-To, receipts, signature, rate limit, enabled request types, custom success notice), contextual help, privacy exporter/eraser. Requests are a private `mw_request` post type with registered statuses; no database schema change (schema stays 0.11.0). Filters: `music_wave_manage_request_caps`, `music_wave_request_message`, `music_wave_request_reply_templates`; actions `music_wave_request_created`, `music_wave_request_status_changed`.
* Note: request data is kept when the plugin is uninstalled (no uninstall routine exists); use the privacy eraser or delete requests from the management screen before removing the plugin if required.

= 0.12.0 =
* Feature: release permalinks now follow the release type. Albums live under `/album/`, tracks and singles under `/track/`, EPs under `/ep/`, mixes under `/mix/`, playlists under `/playlist/`, podcast shows under `/podcast/` and episodes under `/episode/`; releases without a mapped type keep `/music/`. The map is filterable through `music_wave_release_permalink_bases`, every base registers a full WordPress permastruct (pagination, comment pages, feeds, embeds, endpoints), and legacy or stale `/music/<slug>/` links are redirected permanently to the canonical URL.
* Rewrite rules regenerate themselves once per permalink base map (`music_wave_release_permalink_rules` option), so existing installs resolve the new URLs on the first request after the update without re-saving permalinks. No database schema change (schema stays 0.11.0).
* Feature: releases support comments; the theme's single release template renders a styled discussion section (threaded replies, pagination, comment form).
* Improvement: `GET /music-wave/v1/playlists/public` returns `covers` (up to four published cover thumbnails) so client-rendered community playlist cards show real artwork; the client-side card builder now emits the same cover-stack markup as the server.

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
