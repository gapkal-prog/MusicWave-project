# Secure downloads

MusicWave Core never exposes `mw_download_asset_id` or a quality variant identifier as a URL. An authenticated user first requests a short-lived (five-minute) signed token from `POST /wp-json/music-wave/v1/releases/{id}/download-token?quality=mp3-320`. The token is bound to the release, current user, selected quality, and a WordPress download nonce returned with the token.

The frontend sends the WordPress REST nonce explicitly in `X-WP-Nonce` when requesting a token and includes a fresh REST nonce in the protected delivery URL. Expired sessions return the actionable `mw_authentication_required` response rather than WordPress's generic `rest_forbidden` message.

`GET /wp-json/music-wave/v1/downloads/{id}?token=...&nonce=...` verifies the HMAC signature, expiry, user/release/quality/nonce binding, current `AccessPolicyEngine` decision, and one-time replay marker before delegating delivery. A token therefore cannot be reused, transferred to another account, switched to a higher quality, or remain valid after a purchase/membership entitlement changes.

Core ships deny-by-default: no asset is delivered until an integration supplies a `DownloadProvider` through `music_wave_download_provider`. A provider must stream a protected asset itself and must not reveal a master-file URL. Download tokens are single-use. Stream tokens remain reusable during their short lifetime so browser byte-range requests can seek correctly; entitlement is re-evaluated for every request.

Core emits `music_wave_download_event` with an event name, release ID, and user ID for `token_issued`, `token_denied`, `download_delivered`, and `download_denied`. Integrations that persist these events must define retention and personal-data export/erasure behavior.

MusicWave VIP provides an editor-only protected-asset route at `GET`/`POST /wp-json/music-wave/v1/protected-assets`. It lists at most 50 identifiers and accepts only explicitly allowed audio/archive extensions into the configured directory outside the public web root. The selected value remains an opaque `local:` identifier. The local provider supports single HTTP byte ranges for resumable downloads and audio clients; it does not reveal a master URL. Responses use private/no-store caching, no-referrer, content sniffing protection, RFC-compatible filenames, and buffering controls suitable for protected media.

For WooCommerce customers, Core adds a `My music library` My Account endpoint. It shows only releases that pass the current access decision and have at least one protected asset. When multiple quality variants exist, the customer selects a label such as `MP3 320 kbps` or `FLAC lossless`; its buttons reuse the same token issuance route, so a refunded order, expired membership, or revoked manual grant cannot continue to download merely because an item was once shown in the library.
