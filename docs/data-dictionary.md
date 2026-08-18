# MusicWave data dictionary

Schema version: `0.8.0`

## Post type

| Key | Purpose | REST | Public |
|---|---|---:|---:|
| `mw_release` | Canonical track, single, EP, album, mix, playlist, or podcast release | Yes | Yes |

## Taxonomies

| Key | Hierarchical | REST | Purpose |
|---|---:|---:|---|
| `mw_artist` | No | Yes | Primary and featured artists |
| `mw_genre` | Yes | Yes | Genre families and subgenres |
| `mw_mood` | No | Yes | Discovery by mood |
| `mw_label` | No | Yes | Record label or publisher |
| `mw_release_type` | Yes | Yes | Track, single, EP, album, mix, playlist, podcast show, or podcast episode classification |

## Artist term metadata

| Key | Type | REST | Admin | Notes |
|---|---|---:|---:|---|
| `mw_artist_biography` | string | Yes | Yes | Sanitized editorial biography for the public artist archive |
| `mw_artist_image_id` | integer | Yes | Yes | WordPress media attachment ID; image attachments only |
| `mw_artist_canonical_url` | string | Yes | Yes | Optional HTTPS official artist or label URL |
| `_mw_metadata_aliases` | array | No | Yes | Provider-facing and previous names used to match a translated or renamed Artist, Genre, or Label |
| `_mw_metadata_{provider}_id` | string | No | No | Stable provider identity used before visible-name matching |

## Release metadata

| Key | Type | REST | Admin | Notes |
|---|---|---:|---:|---|
| `mw_catalog_number` | string | Yes | Yes | Human-facing catalog identifier |
| `mw_album` | string | Yes | Yes | Album or collection title returned by metadata lookup |
| `mw_release_year` | integer | Yes | Yes | Four-digit year when a provider does not supply a complete date |
| `mw_release_date` | string | Yes | Yes | ISO `YYYY-MM-DD` date |
| `mw_isrc` | string | Yes | Yes | Canonical 12-character International Standard Recording Code |
| `mw_duration` | integer | Yes | Yes | Total duration in seconds |
| `mw_preview_duration` | integer | Yes | Yes | Preview cutoff in seconds; defaults to 30 and accepts 10–120 |
| `mw_bpm` | integer | Yes | Yes | 0 or 20–300 |
| `mw_musical_key` | string | Yes | Yes | Short musical key label |
| `mw_explicit` | boolean | Yes | Yes | Explicit-content marker |
| `mw_preview_url` | string | Yes | Yes | HTTPS preview URL; never a protected master file |
| `mw_track_number` | integer | Yes | Yes | Track/single ordinal; `0` when not applicable |
| `mw_episode_number` | integer | Yes | Yes | Podcast episode ordinal; `0` when not applicable |
| `mw_season_number` | integer | Yes | Yes | Podcast season ordinal; `0` when not applicable |
| `mw_credits` | array | Yes | No | Structured credit objects; editor UI follows after MVP authoring baseline |
| `mw_collection_items` | array | Yes (ordered IDs) | No | Validated child releases with `release_id`, `position`, optional `disc`, and `role` (`track` or `episode`) |
| `mw_access_mode` | string | Yes | Yes | `public`, `purchase`, `membership`, `purchase_or_membership`, or fail-safe `restricted` |
| `mw_product_ids` | array | No | Yes | Canonical WooCommerce product mapping |
| `mw_membership_levels` | array | No | Yes | Provider-neutral membership identifiers |
| `mw_external_id` | string | No | No | Provider-side resource identifier |
| `mw_metadata_source` | string | No | No | Private HTTPS source URL used for the most recent metadata application |
| `mw_metadata_provider` | string | No | No | Private normalized provider key used for the most recent metadata application |
| `mw_download_asset_id` | string | No | No | Legacy single protected asset reference; automatically represented as a `standard` quality when edited |
| `mw_download_assets` | array | No | Yes | Ordered quality variants with a key, customer-facing label, opaque provider asset identifier, and optional private file metadata |

## Product metadata

| Key | Type | REST | Purpose |
|---|---|---:|---|
| `_mw_release_ids` | array | No | Reverse index maintained by the product mapper |

## Invariants

- Product IDs must refer to existing `product` posts.
- Release IDs must refer to existing `mw_release` posts.
- `purchase` access requires at least one product mapping.
- `membership` access requires at least one membership level.
- Incomplete paid access configuration is normalized to `restricted`, never to public.
- Artist, genre, mood, and label terms may store private `_mw_metadata_aliases` plus provider-specific IDs. Auto-fill resolves these stable identities before visible names, so translated or renamed terms are reused instead of duplicated.
- Protected asset and external provider identifiers are never public REST fields.
- A protected asset identifier is opaque. Core does not infer a public URL from it.
- A release may contain any number of unique download qualities. Each quality has a unique technical key; quality keys and provider IDs are never exposed in public HTML or REST.
- A quality may store private editor metadata: `file_name`, `format`, `bitrate` (kbps), `duration` (seconds), and `file_size` (bytes). These values are never emitted in public HTML or REST.
- Albums and podcast shows contain tracks or episodes by relation. Add quality variants to each child release once; collections reuse those children without duplicating files.
- Deleting a product removes it from every release mapping without deleting catalog content.

## Relationship invariants

- Only collection-shaped release types (`album`, `ep`, `mix`, `playlist`, and `podcast_show`) may own children.
- Track roles accept track/single releases; episode roles accept `podcast_episode` releases and only belong to podcast shows.
- A collection cannot contain itself, a duplicate child, or duplicate positions. Reverse lookups are maintained in private `_mw_collection_ids` metadata.
- REST reads expose the ordered relation for public catalog rendering; writes require `edit_post` on the collection and every referenced release.
