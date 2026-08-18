=== MusicWave Core ===
Contributors: manacore
Tags: music, catalog, releases, woocommerce, downloads
Requires at least: 6.6
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.9.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

MusicWave Core provides catalog data, release relationships, WooCommerce mapping, customer music library, access decisions, secure-download contracts, onboarding, and diagnostics for the MusicWave theme.

== Installation ==

1. Upload and activate MusicWave Core.
2. Activate the MusicWave block theme.
3. Save WordPress permalinks.
4. Open MusicWave > Setup & diagnostics and resolve any required checks.
5. Activate MusicWave VIP before assigning private download assets.

== Privacy ==

Core does not persist download audit events by default. Integrations that store `music_wave_download_event` data must provide data retention plus exporter and eraser behavior.
