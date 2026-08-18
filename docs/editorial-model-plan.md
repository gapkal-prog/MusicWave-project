# Editorial model implementation plan

The current implementation is M1–M6 complete, with Slice A delivered as schema version `0.3.0`. The remaining slices prepare richer album/podcast/artist workflows without introducing parallel content models prematurely.

## Slice A — schema and repository (before M7) — Complete

1. `mw_collection_items` is in the canonical schema with a bounded, strict object-list sanitizer.
2. Migration `0.3.0` and repository methods provide replace, append, remove, ordered read, and cached reverse lookup operations.
3. Repository validation rejects unclassified/incompatible relations, self-links, duplicates, missing releases, and cycles.
4. REST reads expose the ordered relation. Writes are rejected unless the caller can edit the collection and every referenced child. Protected asset fields remain private.

## Slice B — type-aware authoring — In progress

1. The **MusicWave release details** metabox exposes shared metadata, protected download qualities, and conditional Track/Podcast Episode fields in one editor location.
2. Collection types use an editor-only REST search with a maximum of 20 results, duplicate prevention, and server-side relation validation. Albums, EPs, mixes, and playlists accept Tracks or Singles; podcast shows accept Podcast Episodes.
3. Directional move buttons provide keyboard-accessible reordering. REST validation remains the authoritative guard for autosaves and revisions.
4. The PHP metabox remains the no-JavaScript fallback. A build pipeline and richer inline validation remain release-hardening work.

## Slice C — artist and podcast editorial depth — In progress

1. Artist term meta and a native-media authoring panel now support biography, image, and canonical links. The `music-wave/artist-profile` block renders these fields on artist archives.
2. `podcast_show`/`podcast_episode` release types and episode-specific fields are registered in the canonical release schema.
3. Introduce a dedicated Artist CPT only if permissions, revisions, or long-form editorial requirements cannot be met by term meta.

## Slice D — media and secure delivery — In progress

1. `mw_download_asset_id` remains provider-neutral and opaque; Core never derives or stores a public URL.
2. MusicWave VIP provides an authorized local protected-asset browser/uploader and stores only `local:` identifiers.
3. Token issuance/resolution now has expiry, nonce binding, replay protection, byte-range streaming, and action hooks. Persisted audit logs and remote providers remain integration work.

## Public preview player

MusicWave uses `mw_preview_url` only for public HTTPS previews. The global queue player reads preview metadata from release buttons, supports previous/next navigation, keyboard-accessible controls, progress seeking, reduced-motion preferences, and the browser Media Session API when available. It never receives `mw_download_asset_id`, download tokens, product mappings, or membership data.

## Quality gates

- Every new field has a dictionary entry, sanitizer, REST policy, migration, and repository test.
- Every relation mutation has capability, nonce, validation, and rollback behavior.
- No theme code performs business or access decisions.
- Archive/single rendering must remain safe for anonymous, purchaser, member, administrator, and restricted states.
- PHP 7.4 compatibility, WordPress coding standards, REST permission tests, and a fresh staging install are release gates.
