=== MusicWave VIP Integration ===
Contributors: manacore
Tags: music, membership, downloads, protected-files
Requires at least: 6.6
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.3.3
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

MusicWave VIP supplies the local protected-file provider, private asset upload/browser, and role-based membership adapter for MusicWave Core.

== Installation ==

1. Activate MusicWave Core before activating this plugin.
2. Set `MUSIC_WAVE_VIP_PROTECTED_ROOT` in `wp-config.php`, or configure Settings > MusicWave VIP.
3. The protected directory must be readable and outside the public WordPress root.
4. Upload or select assets from the MusicWave release sidebar.

== Security ==

The provider stores opaque `local:` identifiers and streams files only after the Core access policy and signed one-time token are validated.
