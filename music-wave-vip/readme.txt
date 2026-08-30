=== MusicWave VIP Integration ===
Contributors: manacore
Tags: music, membership, downloads, protected-files, woocommerce
Requires at least: 6.6
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.5.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

MusicWave VIP supplies the local protected-file provider, private asset upload/browser, WooCommerce plan memberships, and role-based membership adapter for MusicWave Core.

== Description ==

MusicWave VIP extends MusicWave Core with:

* Protected (VIP) downloads and streams from a private directory outside the public web root, or a remote HTTPS host with HMAC-signed short-lived URLs.
* Membership adapters: WordPress roles, developer filters, WooCommerce plan products, WooCommerce Memberships, and WooCommerce Subscriptions.

VIP plan products turn a standard WooCommerce product into a membership plan. When a customer purchases a plan product, their account is promoted from a simple user to a VIP account instantly: they gain the configured membership level, full playback, protected downloads, and the music-wave-vip role. Refunded, cancelled, or failed orders revoke those grants immediately — entitlement is re-checked live on every access decision, so there is no cached access window.

== Installation ==

1. Activate MusicWave Core before activating this plugin.
2. Set `MUSIC_WAVE_VIP_PROTECTED_ROOT` in `wp-config.php`, or configure Settings > MusicWave VIP.
3. The protected directory must be readable and outside the public WordPress root.
4. Upload or select assets from the MusicWave release sidebar.

== WooCommerce VIP plans ==

1. Create a normal WooCommerce product for each plan (e.g. "Gold Membership").
2. In Settings > MusicWave VIP, enable the "WooCommerce plan products" membership source.
3. Add one plan per line: `level:product-id` (optional `:days` duration; omit for lifetime), e.g. `vipgold:123`.
4. On protected releases, set Access Policy to *Membership* and enter the matching level key (e.g. `vipgold`).
5. A customer who buys the plan product instantly gains VIP playback and download access; refunds revoke it.

== Security ==

The provider stores opaque `local:` identifiers and streams files only after the Core access policy and signed one-time token are validated. Plan grants are stored in user meta keyed by order and re-evaluated on every decision; they never rely on a cached state.

== Changelog ==

= 0.5.0 =
* Fix: saving the MusicWave Core integrations tab no longer resets the VIP module. The settings page marks its own full form, so unchecked-checkbox semantics only apply when the VIP form itself is submitted.
* Fix: protected assets are verified against their stored SHA-256 checksum at delivery time; legacy rows without a checksum keep the size check.
* Hardening: an empty membership-source selection now fails closed instead of silently restoring the defaults.
* Hardening: the membership expiry sweep is throttled to a 15-minute cooldown on admin and login hooks; the scheduled cron sweep is unchanged.

= 0.4.0 =
* New: WooCommerce plan products as a membership source (`VipPlans`). A plan is a standard Woo product: paid orders grant the configured level (and the `mw_vip` role), refunds/cancellations/failures revoke it, lifetime or day-bounded durations are supported.
* New: `promote_vip_role` setting and auto-registered read-only `mw_vip` role so VIP accounts are identifiable without granting elevated capabilities.
* New: plan level visibility in the account dashboard through the existing `music_wave_vip_membership_levels_for_user` filter.
* Hardening: grant and revocation meta writes use compare-and-set with retry, so two concurrent WooCommerce order webhooks for one customer can never clobber each other's membership levels.
* Hardening: defensive role access for user objects lacking a roles array.

= 0.3.3 =
* Protected delivery hardening per MusicWave Stage 2.
