<?php
/**
 * Title: کارت انتشار
 * Slug: musicwave/release-card
 * Categories: musicwave, musicwave-cards
 * Keywords: release, card, archive, query loop, preview, artwork
 * Block Types: core/post-template
 * Inserter: true
 * Viewport Width: 800
 *
 * The canonical MusicWave release card: an artwork group with the preview
 * button as a hover/touch overlay, and a body group holding the title, compact
 * release metadata and excerpt. Drop it inside a Query Loop's post template —
 * every block stays individually selectable, so editors can restyle or remove
 * any part without touching code.
 *
 * This is the same markup the release, search, genre and artist archive
 * templates ship, and the same structure catalog.css styles, so a card built
 * here matches the archives exactly. `mw-release-card__overlay` is what turns
 * the preview button into an artwork overlay; keep the button inside it.
 *
 * @package MusicWave
 */
?>
<!-- wp:group {"className":"mw-release-card mw-surface","layout":{"type":"constrained"}} -->
<div class="wp-block-group mw-release-card mw-surface"><!-- wp:group {"className":"mw-release-card__media","layout":{"type":"constrained"}} -->
<div class="wp-block-group mw-release-card__media"><!-- wp:post-featured-image {"isLink":true,"aspectRatio":"1"} /-->
<!-- wp:group {"className":"mw-release-card__overlay","layout":{"type":"flex","justifyContent":"center","verticalAlignment":"center"}} -->
<div class="wp-block-group mw-release-card__overlay"><!-- wp:music-wave/preview-button /--></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
<!-- wp:group {"className":"mw-release-card__body","layout":{"type":"constrained"}} -->
<div class="wp-block-group mw-release-card__body"><!-- wp:post-title {"isLink":true,"fontSize":"large"} /-->
<!-- wp:music-wave/release-meta {"compact":true,"showLibraryButton":false} /-->
<!-- wp:post-excerpt {"moreText":"مشاهده انتشار","excerptLength":22} /--></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
