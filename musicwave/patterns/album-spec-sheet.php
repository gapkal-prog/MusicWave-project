<?php
/**
 * Title: برگهٔ مشخصات آلبوم (سرمقاله‌ای)
 * Slug: musicwave/album-spec-sheet
 * Categories: musicwave, musicwave-cards
 * Keywords: album, spec, chips, liner notes, editorial, metadata
 * Inserter: true
 * Viewport Width: 1200
 *
 * An editorial fact sheet for a release: taxonomy chips, a spec strip of live
 * release data, and a liner-notes card. Everything the platform can resolve is
 * a dynamic block, so the pattern reads correctly on any release; only the
 * labels and the notes byline are editable text.
 *
 * @package MusicWave
 */

?>
<!-- wp:group {"className":"mw-album-facts","layout":{"type":"default"}} -->
<div class="wp-block-group mw-album-facts">
	<!-- wp:post-terms {"term":"mw_release_type","separator":"","className":"mw-chip-rail mw-chip--accent"} /-->
	<!-- wp:post-terms {"term":"mw_mood","separator":"","className":"mw-chip-rail mw-chip--plain"} /-->

	<!-- wp:group {"className":"mw-metabar","layout":{"type":"flex","flexWrap":"wrap","verticalAlignment":"center"}} -->
	<div class="wp-block-group mw-metabar">
		<!-- wp:music-wave/release-meta {"showCatalogNumber":false,"showReleaseDate":true,"showDuration":false,"showBpm":false,"showKey":false,"showArtist":false,"showGenre":false,"showLibraryButton":false,"showTaxonomyChips":false,"showActions":false,"showMood":false,"showLabel":false,"showReleaseType":false,"compact":true,"layout":"inline","showLabels":true,"metadata":{"name":"تاریخ واقعی انتشار"}} /-->
		<!-- wp:post-terms {"term":"mw_label","separator":"","className":"mw-metabar__item"} /-->
		<!-- wp:post-terms {"term":"mw_genre","separator":"","className":"mw-metabar__item"} /-->
	</div>
	<!-- /wp:group -->

	<!-- wp:group {"className":"mw-spec-strip","layout":{"type":"default"}} -->
	<div class="wp-block-group mw-spec-strip">
		<!-- wp:group {"className":"mw-spec-strip__cell mw-spec-strip__cell--accent","layout":{"type":"default"}} -->
		<div class="wp-block-group mw-spec-strip__cell mw-spec-strip__cell--accent">
			<!-- wp:paragraph {"className":"mw-spec-strip__label"} -->
			<p class="mw-spec-strip__label"><?php echo esc_html__( 'ژانر', 'musicwave' ); ?></p>
			<!-- /wp:paragraph -->
			<!-- wp:post-terms {"term":"mw_genre","separator":"","className":"mw-spec-strip__value"} /-->
		</div>
		<!-- /wp:group -->
		<!-- wp:group {"className":"mw-spec-strip__cell","layout":{"type":"default"}} -->
		<div class="wp-block-group mw-spec-strip__cell">
			<!-- wp:paragraph {"className":"mw-spec-strip__label"} -->
			<p class="mw-spec-strip__label"><?php echo esc_html__( 'حال‌وهوا', 'musicwave' ); ?></p>
			<!-- /wp:paragraph -->
			<!-- wp:post-terms {"term":"mw_mood","separator":"","className":"mw-spec-strip__value"} /-->
		</div>
		<!-- /wp:group -->
		<!-- wp:group {"className":"mw-spec-strip__cell mw-spec-strip__cell--gold","layout":{"type":"default"}} -->
		<div class="wp-block-group mw-spec-strip__cell mw-spec-strip__cell--gold">
			<!-- wp:paragraph {"className":"mw-spec-strip__label"} -->
			<p class="mw-spec-strip__label"><?php echo esc_html__( 'مدت زمان', 'musicwave' ); ?></p>
			<!-- /wp:paragraph -->
			<!-- wp:music-wave/release-meta {"showCatalogNumber":false,"showReleaseDate":false,"showDuration":true,"showBpm":false,"showKey":false,"showArtist":false,"showGenre":false,"showLibraryButton":false,"showTaxonomyChips":false,"showActions":false,"showMood":false,"showLabel":false,"showReleaseType":false,"compact":true,"layout":"inline","showLabels":true,"metadata":{"name":"مدت زمان"}} /-->
		</div>
		<!-- /wp:group -->
	</div>
	<!-- /wp:group -->

	<!-- wp:group {"className":"mw-liner-notes","layout":{"type":"default"}} -->
	<div class="wp-block-group mw-liner-notes">
		<!-- wp:music-wave/synced-lyrics /-->
	</div>
	<!-- /wp:group -->
</div>
<!-- /wp:group -->
