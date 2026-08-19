<?php
/**
 * Block-theme template and dynamic-block regression checks.
 */

declare(strict_types=1);

$theme_directory = dirname( __DIR__ ) . '/musicwave';
$theme_json      = json_decode( (string) file_get_contents( $theme_directory . '/theme.json' ), true );
mw_assert_same( true, is_array( $theme_json ), 'theme.json must contain valid JSON.' );

$registered_parts = array();
foreach ( isset( $theme_json['templateParts'] ) && is_array( $theme_json['templateParts'] ) ? $theme_json['templateParts'] : array() as $part ) {
	if ( ! is_array( $part ) || empty( $part['name'] ) ) {
		continue;
	}
	$registered_parts[] = (string) $part['name'];
	mw_assert_same(
		true,
		is_file( $theme_directory . '/parts/' . $part['name'] . '.html' ),
		'Every template part declared by theme.json must have a matching file.'
	);
}

$block_files = array_merge(
	glob( $theme_directory . '/templates/*.html' ) ?: array(),
	glob( $theme_directory . '/parts/*.html' ) ?: array(),
	glob( $theme_directory . '/patterns/*.php' ) ?: array()
);

$registered_patterns = array();
foreach ( glob( $theme_directory . '/patterns/*.php' ) ?: array() as $pattern_file ) {
	$pattern_content = (string) file_get_contents( $pattern_file );
	if ( preg_match( '/^\s*\*\s+Slug:\s+([a-z0-9-]+\/[a-z0-9-]+)/m', $pattern_content, $slug_match ) ) {
		$registered_patterns[] = $slug_match[1];
	}
}

$registered_custom_templates = array();
foreach ( isset( $theme_json['customTemplates'] ) && is_array( $theme_json['customTemplates'] ) ? $theme_json['customTemplates'] : array() as $custom_template ) {
	mw_assert_same( true, is_array( $custom_template ) && isset( $custom_template['name'] ), 'Each custom template must declare a name.' );
	$registered_custom_templates[] = (string) $custom_template['name'];
	mw_assert_same(
		true,
		is_file( $theme_directory . '/templates/' . $custom_template['name'] . '.html' ),
		'Every custom template declared by theme.json must have a matching templates/*.html file.'
	);
}
mw_assert_same(
	true,
	in_array( 'page-no-sidebar', $registered_custom_templates, true ),
	'theme.json must register the "page-no-sidebar" layout template so editors can remove the Music sidebar per page.'
);
mw_assert_same(
	true,
	in_array( 'page-account', $registered_custom_templates, true ),
	'theme.json must register the unified "page-account" template for the account pages.'
);

$layout_css = (string) file_get_contents( $theme_directory . '/assets/css/layout.css' );
mw_assert_same(
	true,
	false !== strpos( $layout_css, '.mw-app-shell:has(> .mw-sidebar)' ),
	'The app-shell grid must apply only when the Music sidebar template part is present.'
);
mw_assert_same(
	true,
	false !== strpos( $layout_css, '.mw-app-shell:not(:has(> .mw-sidebar))' ),
	'Pages without the Music sidebar must fall back to a single-column layout.'
);
mw_assert_same(
	false,
	1 === preg_match( '/\.mw-app-shell\s*\{[^{}]*grid-template-columns/', $layout_css ),
	'The unconditional .mw-app-shell grid rule is removed in favour of sidebar-aware selectors.'
);

$no_sidebar_template = (string) file_get_contents( $theme_directory . '/templates/page-no-sidebar.html' );
mw_assert_same(
	false,
	false !== strpos( $no_sidebar_template, 'music-sidebar' ),
	'The no-sidebar page template must not reference the Music sidebar template part.'
);
mw_assert_same(
	false,
	false !== strpos( $no_sidebar_template, 'mw-app-shell' ),
	'The no-sidebar page template must not use the app-shell layout class.'
);

$functions_source = (string) file_get_contents( $theme_directory . '/functions.php' );
mw_assert_same(
	true,
	false !== strpos( $functions_source, "'page-no-sidebar'" ),
	'functions.php must whitelist the no-sidebar template slug for template repair.'
);

$sidebar_templates = array(
	'archive-mw_release.html',
	'archive-product.html',
	'archive.html',
	'home.html',
	'index.html',
	'page-account.html',
	'page-browse.html',
	'page-music-home.html',
	'page.html',
	'search.html',
	'single-mw_release.html',
	'single-product.html',
	'single.html',
	'taxonomy-mw_artist.html',
	'taxonomy-mw_genre.html',
);
foreach ( $sidebar_templates as $template_file ) {
	$contents = (string) file_get_contents( $theme_directory . '/templates/' . $template_file );
	mw_assert_same(
		true,
		false !== strpos( $contents, 'className":"mw-app-shell"' ) || false !== strpos( $contents, 'mw-app-shell' ),
		$template_file . ' must use the shared app-shell layout wrapper.'
	);
	// The bundled templates ship without a sidebar; layout.css must still adapt
	// automatically when an administrator adds one to a saved template.
	if ( false !== strpos( $contents, '"slug":"music-sidebar"' ) ) {
		mw_assert_same(
			true,
			in_array( 'music-sidebar', $registered_parts, true ),
			$template_file . ' references a Music sidebar that theme.json must register.'
		);
	}
}

foreach ( $block_files as $block_file ) {
	$content = (string) file_get_contents( $block_file );
	$label   = basename( $block_file );

	preg_match_all( '/<!--\s+wp:template-part\s+(\{.*?\})\s+\/-->/', $content, $template_part_matches );
	foreach ( isset( $template_part_matches[1] ) ? $template_part_matches[1] : array() as $attributes_json ) {
		$attributes = json_decode( $attributes_json, true );
		$slug       = is_array( $attributes ) && isset( $attributes['slug'] ) ? (string) $attributes['slug'] : '';
		mw_assert_same( true, '' !== $slug, $label . ' must use a valid template-part slug.' );
		mw_assert_same( true, in_array( $slug, $registered_parts, true ), $label . ' must reference a template part registered in theme.json.' );
		mw_assert_same( true, is_file( $theme_directory . '/parts/' . $slug . '.html' ), $label . ' must reference an available template-part file.' );
	}

	preg_match_all( '/<!--\s+wp:pattern\s+(\{.*?\})\s+\/-->/', $content, $pattern_matches );
	foreach ( isset( $pattern_matches[1] ) ? $pattern_matches[1] : array() as $attributes_json ) {
		$attributes = json_decode( $attributes_json, true );
		$slug       = is_array( $attributes ) && isset( $attributes['slug'] ) ? (string) $attributes['slug'] : '';
		mw_assert_same( true, in_array( $slug, $registered_patterns, true ), $label . ' must reference an available bundled pattern.' );
	}

	preg_match_all( '/<!--\s+wp:((?:music-wave|musicwave)\/[a-z0-9-]+)(.*?)-->/', $content, $dynamic_matches, PREG_SET_ORDER );
	foreach ( $dynamic_matches as $dynamic_match ) {
		mw_assert_same(
			true,
			'/' === substr( rtrim( $dynamic_match[2] ), -1 ),
			$label . ' must store dynamic MusicWave blocks as self-closing comments without fallback HTML.'
		);
	}

	mw_assert_same( false, false !== strpos( $content, 'â' ), $label . ' must not contain mojibake sequences.' );
	mw_assert_same( false, false !== strpos( $content, 'Ã' ), $label . ' must not contain mojibake sequences.' );

	preg_match_all( '/<!--\s*(\/?)wp:([a-z0-9-]+\/)?([a-z0-9-]+)(.*?)(\/?)-->/', $content, $comments, PREG_SET_ORDER );
	$stack = array();
	foreach ( $comments as $comment ) {
		$is_closing = '/' === $comment[1];
		$is_self    = '/' === $comment[5] || ( ! $is_closing && '/' === substr( rtrim( $comment[4] ), -1 ) );
		$block_name = (string) $comment[2] . $comment[3];
		if ( $is_self ) {
			continue;
		}
		if ( ! $is_closing ) {
			$stack[] = $block_name;
			continue;
		}
		$opened = array_pop( $stack );
		mw_assert_same( $block_name, $opened, $label . ' must close blocks in the same order they were opened.' );
	}
	mw_assert_same( array(), $stack, $label . ' must close every non-dynamic block comment.' );
}

$editor_script = (string) file_get_contents( dirname( __DIR__ ) . '/music-wave-core/assets/blocks.js' );
mw_assert_same(
	true,
	false !== strpos( $editor_script, "'music-wave/release-meta': {" )
		&& false !== strpos( $editor_script, 'showCatalogNumber' ),
	'Release metadata must expose the release picker in the editor.'
);
mw_assert_same(
	false,
	false !== strpos( $editor_script, 'Nothing to show yet.' ),
	'Dynamic blocks must use actionable, block-specific empty states.'
);

$release_blocks_source = (string) file_get_contents( dirname( __DIR__ ) . '/music-wave-core/src/Blocks/ReleaseBlocks.php' );
$rendering_module      = (string) file_get_contents( dirname( __DIR__ ) . '/music-wave-core/src/Modules/Rendering.php' );

foreach ( array( 'layout', 'columns', 'imageShape', 'showArtwork', 'showPreview', 'showAction', 'sameArtistSection', 'similarSection', 'matchGenre', 'matchMood', 'matchType', 'orderBy', 'showSectionLink' ) as $related_attribute ) {
	mw_assert_same(
		true,
		false !== strpos( $release_blocks_source, '\'' . $related_attribute . '\'' ),
		'Related releases must register the ' . $related_attribute . ' block attribute.'
	);
	mw_assert_same(
		true,
		false !== strpos( $rendering_module, '\'' . $related_attribute . '\'' ),
		'The editor metadata must mirror the related-releases ' . $related_attribute . ' attribute.'
	);
}

foreach ( array( 'showWhenGranted', 'grantedMessage', 'purchaseMessage', 'purchaseCtaLabel', 'membershipMessage', 'membershipCtaLabel', 'membershipCtaUrl' ) as $panel_attribute ) {
	mw_assert_same(
		true,
		false !== strpos( $release_blocks_source, '\'' . $panel_attribute . '\'' ) && false !== strpos( $rendering_module, '\'' . $panel_attribute . '\'' ),
		'The access panel override ' . $panel_attribute . ' must be registered for the server and the editor.'
	);
}

foreach ( array( 'showHeading', 'showRole', 'groupByRole' ) as $credits_attribute ) {
	mw_assert_same(
		true,
		false !== strpos( $release_blocks_source, '\'' . $credits_attribute . '\'' ) && false !== strpos( $rendering_module, '\'' . $credits_attribute . '\'' ),
		'The credits option ' . $credits_attribute . ' must be registered for the server and the editor.'
	);
}

foreach ( array( 'showPosition', 'showArtwork', 'showDuration', 'showTotalDuration', 'showPreview', 'showDownload', 'groupByDisc' ) as $collection_attribute ) {
	mw_assert_same(
		true,
		false !== strpos( $release_blocks_source, '\'' . $collection_attribute . '\'' ) && false !== strpos( $rendering_module, '\'' . $collection_attribute . '\'' ),
		'The collection option ' . $collection_attribute . ' must be registered for the server and the editor.'
	);
}

foreach ( array( 'showDescription', 'showQuality', 'showStream', 'downloadLabel', 'playLabel', 'loginLabel' ) as $download_attribute ) {
	mw_assert_same(
		true,
		false !== strpos( $release_blocks_source, '\'' . $download_attribute . '\'' ) && false !== strpos( $rendering_module, '\'' . $download_attribute . '\'' ),
		'The secure download option ' . $download_attribute . ' must be registered for the server and the editor.'
	);
}

foreach ( array( 'showLabels', 'linkTerms', 'showMood', 'showReleaseType' ) as $meta_attribute ) {
	mw_assert_same(
		true,
		false !== strpos( $release_blocks_source, '\'' . $meta_attribute . '\'' ) && false !== strpos( $rendering_module, '\'' . $meta_attribute . '\'' ),
		'The release metadata option ' . $meta_attribute . ' must be registered for the server and the editor.'
	);
}

foreach ( array( 'showSearch', 'showArtistFilter', 'showGenreFilter', 'showMoodFilter', 'showTypeFilter', 'showSort', 'showReset', 'maxTerms', 'searchPlaceholder', 'submitLabel', 'resetLabel' ) as $filter_attribute ) {
	mw_assert_same(
		true,
		false !== strpos( $release_blocks_source, '\'' . $filter_attribute . '\'' ) && false !== strpos( $rendering_module, '\'' . $filter_attribute . '\'' ),
		'The catalog filter option ' . $filter_attribute . ' must be registered for the server and the editor.'
	);
}

foreach ( array( 'showCount', 'showChips' ) as $results_attribute ) {
	mw_assert_same(
		true,
		false !== strpos( $release_blocks_source, '\'' . $results_attribute . '\'' ) && false !== strpos( $rendering_module, '\'' . $results_attribute . '\'' ),
		'The catalog results option ' . $results_attribute . ' must be registered for the server and the editor.'
	);
}

$preview_player_source = (string) file_get_contents( dirname( __DIR__ ) . '/music-wave-core/src/Blocks/PreviewPlayer.php' );
foreach ( array( 'style', 'showIcon', 'fullWidth' ) as $preview_attribute ) {
	mw_assert_same(
		true,
		false !== strpos( $preview_player_source, '\'' . $preview_attribute . '\'' ) && false !== strpos( $rendering_module, '\'' . $preview_attribute . '\'' ),
		'The preview button option ' . $preview_attribute . ' must be registered for the server and the editor.'
	);
}

$artist_profile_source = (string) file_get_contents( dirname( __DIR__ ) . '/music-wave-core/src/Blocks/ArtistProfileBlock.php' );
foreach ( array( 'bioLength', 'showReleaseCount' ) as $artist_attribute ) {
	mw_assert_same(
		true,
		false !== strpos( $artist_profile_source, '\'' . $artist_attribute . '\'' ) && false !== strpos( $rendering_module, '\'' . $artist_attribute . '\'' ),
		'The artist profile option ' . $artist_attribute . ' must be registered for the server and the editor.'
	);
}

foreach ( array( 'sameArtistSection', 'matchGenre', 'groupByRole', 'groupByDisc', 'downloadLabel', 'showWhenGranted', 'imageShape' ) as $editor_control ) {
	mw_assert_same(
		true,
		false !== strpos( $editor_script, $editor_control ),
		'The editor must expose the ' . $editor_control . ' inspector control.'
	);
}

foreach ( array( 'showLabels', 'linkTerms', 'showMood', 'showReleaseType', 'showSearch', 'showArtistFilter', 'maxTerms', 'showCount', 'showChips', 'fullWidth', 'bioLength', 'showReleaseCount' ) as $editor_control ) {
	mw_assert_same(
		true,
		false !== strpos( $editor_script, $editor_control ),
		'The editor must expose the ' . $editor_control . ' inspector control.'
	);
}

$related_css = (string) file_get_contents( $theme_directory . '/assets/css/components/related.css' );
foreach ( array( '.mw-related-releases__section-header', '.mw-related-releases__more' ) as $related_selector ) {
	mw_assert_same(
		true,
		false !== strpos( $related_css, $related_selector ),
		'The theme must style the related-releases variant ' . $related_selector . '.'
	);
}

// Related rails reuse the MusicWave release shelf layout system so they stay
// visually identical to the shelf block.
foreach ( array( 'mw-release-shelf--', 'mw-release-shelf__items', 'mw-release-shelf__item', 'mw-release-shelf__art--' ) as $shelf_marker ) {
	mw_assert_same(
		true,
		false !== strpos( $release_blocks_source, $shelf_marker ),
		'Related releases must render with the release-shelf layout class ' . $shelf_marker . '.'
	);
}

$access_css = (string) file_get_contents( $theme_directory . '/assets/css/components/access.css' );
mw_assert_same(
	true,
	false !== strpos( $access_css, '.mw-access-panel--stack' ),
	'The theme must style the stacked access-panel layout.'
);

$catalog_css = (string) file_get_contents( $theme_directory . '/assets/css/components/catalog.css' );
foreach ( array( '.mw-release-meta--inline', '.mw-release-meta--stack' ) as $meta_selector ) {
	mw_assert_same(
		true,
		false !== strpos( $catalog_css, $meta_selector ),
		'The theme must style the release metadata variant ' . $meta_selector . '.'
	);
}

$player_css = (string) file_get_contents( $theme_directory . '/assets/css/components/global-player.css' );
foreach ( array( '.mw-preview-button--outline', '.mw-preview-button--ghost', '.mw-preview-button--block' ) as $preview_selector ) {
	mw_assert_same(
		true,
		false !== strpos( $player_css, $preview_selector ),
		'The theme must style the preview button variant ' . $preview_selector . '.'
	);
}

$catalog_filters_css = (string) file_get_contents( $theme_directory . '/assets/css/components/catalog-filters.css' );
mw_assert_same(
	true,
	false !== strpos( $catalog_filters_css, '.mw-catalog-filters--stacked' ),
	'The theme must style the stacked catalog-filters layout.'
);

$artist_css = (string) file_get_contents( $theme_directory . '/assets/css/components/artist.css' );
mw_assert_same(
	true,
	false !== strpos( $artist_css, '.mw-artist-profile__count' ),
	'The theme must style the artist profile release count badge.'
);

$collections_css = (string) file_get_contents( $theme_directory . '/assets/css/components/collections.css' );
foreach ( array( '.mw-release-credits__list--grid', '.mw-release-credits__list--inline', '.mw-collection-list__disc', '.mw-collection-list__artwork' ) as $collection_selector ) {
	mw_assert_same(
		true,
		false !== strpos( $collections_css, $collection_selector ),
		'The theme must style the collection/credits variant ' . $collection_selector . '.'
	);
}

$downloads_css = (string) file_get_contents( $theme_directory . '/assets/css/components/downloads.css' );
mw_assert_same(
	true,
	false !== strpos( $downloads_css, '.mw-download-file-row--no-quality' ),
	'The theme must style download rows without a quality selector.'
);

// The library, dashboard, and My Account pages share one unified account template.
$account_template_contents = (string) file_get_contents( $theme_directory . '/templates/page-account.html' );
mw_assert_same(
	true,
	false !== strpos( $account_template_contents, '"slug":"musicwave/account-hub"' ),
	'page-account.html must render the unified account hub pattern.'
);
foreach ( array( 'page-library.html', 'page-dashboard.html', 'page-my-account.html' ) as $retired_template ) {
	mw_assert_same(
		false,
		is_file( $theme_directory . '/templates/' . $retired_template ),
		'The retired ' . $retired_template . ' template must be merged into page-account.html.'
	);
}
mw_assert_same(
	true,
	false !== strpos( $functions_source, 'musicwave_alias_account_template' )
		&& false !== strpos( $functions_source, "'page-account'" ),
	'functions.php must alias legacy account templates to the unified page-account template.'
);

$account_hub_pattern = (string) file_get_contents( $theme_directory . '/patterns/account-hub.php' );
mw_assert_same(
	true,
	false !== strpos( $account_hub_pattern, 'wp:music-wave/account-dashboard' ),
	'The account hub must render the unified account dashboard.'
);
mw_assert_same(
	true,
	false === strpos( $account_hub_pattern, 'wp:music-wave/music-library' )
		&& false === strpos( $account_hub_pattern, 'woocommerce_my_account' ),
	'The account hub must not duplicate the library or WooCommerce account surfaces; the dashboard already contains those panels (PROJECT_PLAN.md §14).'
);

$account_library_source = (string) file_get_contents( dirname( __DIR__ ) . '/music-wave-core/src/Commerce/AccountLibrary.php' );
mw_assert_same(
	false,
	false !== strpos( $account_library_source, "data-mw-dashboard-panel=\"' . esc_attr( \$key ) . '\" hidden" ),
	'Dashboard panels must not be server-hidden; content has to stay visible when JavaScript fails (PROJECT_PLAN.md §14).'
);

$dashboard_script = (string) file_get_contents( dirname( __DIR__ ) . '/music-wave-core/assets/dashboard.js' );
mw_assert_same(
	true,
	false !== strpos( $dashboard_script, 'closeAll();' ),
	'The dashboard controller must collapse panels on load so tab behavior only applies when JavaScript is available.'
);

$library_blocks_source = (string) file_get_contents( dirname( __DIR__ ) . '/music-wave-core/src/Blocks/LibraryBlocks.php' );
foreach ( array( 'showHeading', 'showFilters', 'showCounts', 'columns', 'itemsToShow', 'showArtist', 'showType', 'showYear', 'showRemove', 'emptyMessage' ) as $library_attribute ) {
	mw_assert_same(
		true,
		false !== strpos( $library_blocks_source, '\'' . $library_attribute . '\'' ) && false !== strpos( $rendering_module, '\'' . $library_attribute . '\'' ),
		'The personal library option ' . $library_attribute . ' must be registered for the server and the editor.'
	);
}

mw_assert_same(
	true,
	false !== strpos( $release_blocks_source, '\'showLibraryButton\'' ) && false !== strpos( $rendering_module, '\'showLibraryButton\'' ),
	'The add-to-library toggle must be registered for release metadata on the server and in the editor.'
);
mw_assert_same(
	true,
	false !== strpos( $editor_script, '\'music-wave/music-library\': {' )
		&& false !== strpos( $editor_script, '\'music-wave/library-button\': {' )
		&& false !== strpos( $editor_script, 'showLibraryButton' ),
	'The editor must expose the personal library blocks and the add-to-library control.'
);

$account_library_css = (string) file_get_contents( $theme_directory . '/assets/css/components/account-library.css' );
foreach ( array( '.mw-music-library__tabs', '.mw-music-library__items--grid', '.mw-library-button', '.mw-user-dashboard__stats', '.mw-release-meta__actions', '.mw-user-dashboard__tabs', '.mw-user-dashboard__panel' ) as $library_selector ) {
	mw_assert_same(
		true,
		false !== strpos( $account_library_css, $library_selector ),
		'The theme must style the personal library component ' . $library_selector . '.'
	);
}

$account_library_source = (string) file_get_contents( dirname( __DIR__ ) . '/music-wave-core/src/Commerce/AccountLibrary.php' );
foreach ( array( 'data-mw-dashboard-tab', 'data-mw-dashboard-panel', 'class_exists( \'WooCommerce\' )', 'MUSIC_WAVE_VIP_FILE' ) as $dashboard_marker ) {
	mw_assert_same(
		true,
		false !== strpos( $account_library_source, $dashboard_marker ),
		'The dashboard must render tabbed sections with plugin-aware visibility (' . $dashboard_marker . ').'
	);
}
mw_assert_same(
	true,
	is_file( dirname( __DIR__ ) . '/music-wave-core/assets/dashboard.js' ),
	'The dashboard tab controller script must ship with the plugin.'
);

// block.json parity: every dynamic block ships marketplace-grade metadata,
// and its attributes stay synchronized with the editor localization layer.
$music_wave_block_metadata_dir = dirname( __DIR__ ) . '/music-wave-core/blocks';
$music_wave_expected_blocks    = array(
	'release-meta',
	'access-panel',
	'release-credits',
	'collection-list',
	'catalog-filters',
	'catalog-results',
	'preview-player',
	'download-button',
	'related-releases',
	'preview-button',
	'artist-profile',
	'account-dashboard',
	'music-library',
	'library-button',
	'playlists',
	'add-to-playlist',
);
foreach ( $music_wave_expected_blocks as $music_wave_block_slug ) {
	$music_wave_metadata_file = $music_wave_block_metadata_dir . '/' . $music_wave_block_slug . '/block.json';
	mw_assert_same( true, is_file( $music_wave_metadata_file ), 'Every MusicWave block must ship a block.json metadata file (' . $music_wave_block_slug . ').' );

	$music_wave_metadata = json_decode( (string) file_get_contents( $music_wave_metadata_file ), true );
	mw_assert_same( true, is_array( $music_wave_metadata ), $music_wave_block_slug . ' block.json must contain valid JSON.' );
	mw_assert_same( 'music-wave/' . $music_wave_block_slug, isset( $music_wave_metadata['name'] ) ? (string) $music_wave_metadata['name'] : '', $music_wave_block_slug . ' block.json must declare its canonical block name.' );
	mw_assert_same( 3, isset( $music_wave_metadata['apiVersion'] ) ? (int) $music_wave_metadata['apiVersion'] : 0, $music_wave_block_slug . ' must register as an API v3 block.' );
	mw_assert_same( 'music-wave', isset( $music_wave_metadata['category'] ) ? (string) $music_wave_metadata['category'] : '', $music_wave_block_slug . ' must live in the dedicated music-wave inserter category.' );
	mw_assert_same( 'music-wave-core', isset( $music_wave_metadata['textdomain'] ) ? (string) $music_wave_metadata['textdomain'] : '', $music_wave_block_slug . ' must declare the music-wave-core text domain.' );
	mw_assert_same(
		true,
		! empty( $music_wave_metadata['title'] ) && ! empty( $music_wave_metadata['description'] ) && ! empty( $music_wave_metadata['icon'] ),
		$music_wave_block_slug . ' block.json must declare a title, description, and icon.'
	);
	mw_assert_same(
		true,
		isset( $music_wave_metadata['keywords'] ) && is_array( $music_wave_metadata['keywords'] ) && count( $music_wave_metadata['keywords'] ) > 0,
		$music_wave_block_slug . ' block.json must declare inserter keywords.'
	);
	mw_assert_same(
		true,
		isset( $music_wave_metadata['example']['attributes'] ) && is_array( $music_wave_metadata['example']['attributes'] ),
		$music_wave_block_slug . ' block.json must provide an inserter example.'
	);
	mw_assert_same(
		true,
		isset( $music_wave_metadata['attributes'] ) && is_array( $music_wave_metadata['attributes'] ) && count( $music_wave_metadata['attributes'] ) > 0,
		$music_wave_block_slug . ' block.json must declare its attributes.'
	);

	foreach ( array_keys( isset( $music_wave_metadata['attributes'] ) && is_array( $music_wave_metadata['attributes'] ) ? $music_wave_metadata['attributes'] : array() ) as $music_wave_attribute_key ) {
		mw_assert_same(
			true,
			false !== strpos( $rendering_module, "'" . $music_wave_attribute_key . "'" ),
			'The ' . $music_wave_block_slug . ' attribute ' . $music_wave_attribute_key . ' from block.json must stay localized by Rendering.php for the editor.'
		);
	}
}
mw_assert_same(
	count( $music_wave_expected_blocks ),
	count( glob( $music_wave_block_metadata_dir . '/*/block.json' ) ?: array() ),
	'The blocks/ directory must hold exactly one block.json per MusicWave dynamic block.'
);
