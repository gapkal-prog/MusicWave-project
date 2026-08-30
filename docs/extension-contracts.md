# Extension and event contracts

Status: Phase 2 Stage 3 (PROJECT_PLAN.md deliverable 7). This file is the authoritative
inventory of public extension points, their stability level, and the deprecation policy.

## Deprecation policy

- **Stable** hooks keep signature compatibility within a major version. New parameters are
  appended only (additive).
- Removing or changing a stable hook requires: a deprecation notice in the changelog and
  readme, a compatibility shim for **one minor release cycle**, and a `_deprecated_hook()`
  runtime notice where WordPress is available.
- **Experimental** hooks may change in any release; they are marked below.

## Provider composition (stable)

| Hook | Type | Contract |
|---|---|---|
| `music_wave_membership_provider` | filter | Return a `MembershipProvider`; invalid values fall back to the null provider (deny). |
| `music_wave_manual_access_provider` | filter | Return a `ManualAccessProvider`; same fallback. |
| `music_wave_download_provider` | filter | Return a `DownloadProvider` (optionally `StreamableDownloadProvider`). Deny-by-default when absent. |
| `music_wave_core_modules` | filter | Append `Module` implementations composed at boot. |

## Entitlement and delivery (stable)

| Hook | Type | Contract |
|---|---|---|
| `music_wave_purchase_owns_release` | filter | `(bool $owns, int $user_id, int $release_id)` — adjust purchase ownership (ADR 0004). |
| `music_wave_can_assign_download_asset` | filter | `(?bool $authorized, string $asset_id, int $release_id, int $user_id)` — provider-side assignment authorization; `null` defers to the dedicated capability. |
| `music_wave_manage_asset_caps` | filter | Primitive capabilities mapped to `manage_mw_protected_assets` (default `manage_options`). |
| `music_wave_download_event` | action | `(string $event, int $release_id, int $user_id, array $context)` — structured, PII-free audit stream. Context added in 0.9.x (additive). Persisting integrations own retention/export/erasure. |
| `music_wave_download_rate_limit` / `music_wave_download_rate_window` | filter | Token-issue rate limit tuning. |
| `music_wave_download_daily_quota` | filter | Per-user daily delivery quota; `0` disables (default). |

## VIP delivery (stable)

| Hook | Type | Contract |
|---|---|---|
| `music_wave_vip_download_provider` / `music_wave_vip_membership_provider` | filter | Replace composed VIP adapters. |
| `music_wave_vip_remote_download_url` | filter | Return a complete signed URL; the result must pass the HTTPS host allowlist or delivery fails closed. |
| `music_wave_vip_membership_levels_for_user` | filter | Map user roles/sources to membership level slugs. VIP adds active plan levels at priority 20. |
| `music_wave_vip_granting_statuses` | filter | `array<int, string>` WooCommerce order statuses that grant VIP plans (default `processing`, `completed`; ADR 0004 parity). |
| `music_wave_vip_plan_granted` | action | `(int $user_id, int $order_id, array $levels)` after a paid order grants plan levels. |
| `music_wave_vip_plan_revoked` | action | `(int $user_id, int $order_id)` after a voided order revokes its plan grants. |
| `music_wave_vip_grants_write_failed` | action | `(string $event, int $user_id, int $order_id)` when a grant meta write exhausts its compare-and-set retries under webhook contention. |

## Catalog, presentation, and privacy (stable)

| Hook | Type | Contract |
|---|---|---|
| `music_wave_library_items` | filter | Adjust normalized personal-library items for display paths. |
| `music_wave_library_item_added` / `music_wave_library_item_removed` | action | `$type, $item_id, $user_id` after a library mutation (`release`, `artist`, `wishlist`, `presave`). |
| `music_wave_presave_fulfilled` | action | `$release_id, $user_id` when a pre-saved release becomes available; the notification integration point. |
| `music_wave_listening_retention_days` | filter | Listening-history retention window in days (default 180). |
| `music_wave_recommendations` | filter | Curate recommendation items; each item must keep a machine `reason` and a translated `explanation`. |
| `music_wave_catalog_search_adapter` | filter | Return a `CatalogSearchAdapter` for external search. It proposes candidate release IDs only; Core still enforces release visibility. |
| `music_wave_catalog_suggestions` | filter | Curate autocomplete suggestions; entries must stay public (published releases and public terms). |
| `music_wave_catalog_discovery_ttl` | filter | Cache lifetime for autocomplete/facet payloads (default 300s). |
| `music_wave_discovery_rate_limit` / `music_wave_discovery_rate_window` | filter | Public discovery rate limit per actor and window (default 60/60s). |
| `music_wave_dashboard_panels` | filter | Add/remove account dashboard panels (key => icon/label/description/content). |
| `music_wave_release_json_ld` | filter | Adjust public JSON-LD; must not add private assets, entitlements, or user data. |
| `music_wave_json_ld_enabled` | filter | Toggle Core JSON-LD output. |
| `music_wave_cover_import_budgets` | filter | Cover import timeout/byte/pixel budgets. |
| `music_wave_provider_health` | filter | Report provider health: slug => `{status: ok|misconfigured|unreachable|rate_limited|failed, summary}`; surfaced in Site Health. |

## Experimental

| Hook | Type | Notes |
|---|---|---|
| `music_wave_admin_integrations` / `music_wave_admin_integration_settings` | filter/action | Admin integration cards; UI may change. |

## Events lifecycle

`music_wave_core_loaded` (action) fires once after module composition; integrations must
register providers via the filters above **before** it fires (normal plugin load order).
