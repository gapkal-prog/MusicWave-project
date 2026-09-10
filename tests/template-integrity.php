<?php
/**
 * Block-theme template and dynamic-block regression checks.
 */

declare(strict_types=1);

if ( ! function_exists( 'mw_assert_same' ) ) {
	/**
	 * Assert one static integrity expectation.
	 *
	 * The complete test suite defines this helper in tests/run.php. Keeping a
	 * guarded local fallback makes this focused integrity check safe to run on
	 * its own as well, without redeclaring the suite helper when included.
	 *
	 * @param mixed  $expected Expected value.
	 * @param mixed  $actual   Actual value.
	 * @param string $message  Failure message.
	 * @return void
	 */
	function mw_assert_same( $expected, $actual, string $message ): void {
		if ( $expected !== $actual ) {
			fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
			fwrite( STDERR, 'Expected: ' . var_export( $expected, true ) . PHP_EOL );
			fwrite( STDERR, 'Actual: ' . var_export( $actual, true ) . PHP_EOL );
			exit( 1 );
		}
	}
}

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
mw_assert_same(
	true,
	in_array( 'page-playlists', $registered_custom_templates, true ),
	'theme.json must register the public playlists template so it is assignable in the Site Editor.'
);
mw_assert_same(
	true,
	in_array( 'page-cart', $registered_custom_templates, true ) && in_array( 'page-checkout', $registered_custom_templates, true ),
	'theme.json must register the commerce page templates for explicit Site Editor assignment.'
);

$layout_css = (string) file_get_contents( $theme_directory . '/assets/css/layout.css' );
$navigation_css = (string) file_get_contents( $theme_directory . '/assets/css/components/navigation.css' );
mw_assert_same(
	true,
	false !== strpos( $navigation_css, '.mw-site-header.is-position-sticky' ) && false !== strpos( $navigation_css, 'position: sticky' ),
	'The header must become sticky through the Site Editor position support class.'
);
mw_assert_same(
	false,
	1 === preg_match( '/\.mw-site-header\s*\{[^{}]*position:\s*sticky/', $navigation_css ),
	'The header must not be sticky when the Site Editor position toggle is disabled.'
);
mw_assert_same(
	true,
	false !== strpos( $navigation_css, 'block-size: 100dvh' ) && false !== strpos( $navigation_css, '.wp-block-navigation__responsive-container.is-menu-open' ),
	'The mobile navigation must provide a full-screen open state.'
);
mw_assert_same(
	true,
	false !== strpos( $navigation_css, 'body.has-modal-open' ) && false !== strpos( $navigation_css, 'overflow: hidden' ),
	'The mobile navigation must lock background scrolling while open.'
);
mw_assert_same(
	true,
	isset( $theme_json['settings']['position']['sticky'], $theme_json['settings']['blocks']['core/group']['position']['sticky'] ) && true === $theme_json['settings']['position']['sticky'] && true === $theme_json['settings']['blocks']['core/group']['position']['sticky'],
	'theme.json must expose sticky positioning for the header Group in Site Editor.'
);
mw_assert_same(
	true,
	false !== strpos( $navigation_css, 'inset-inline-end' ),
	'Header and mobile navigation positioning must use RTL-safe logical insets.'
);
mw_assert_same(
	true,
	false !== strpos( $navigation_css, 'prefers-reduced-motion' ),
	'Header and mobile navigation must respect reduced-motion preferences.'
);

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

$page_with_sidebar_template = (string) file_get_contents( $theme_directory . '/templates/page-with-sidebar.html' );
mw_assert_same(
	false,
	false !== strpos( $page_with_sidebar_template, '"slug":"footer-widgets"' ),
	'page-with-sidebar.html must not render footer-widgets beside footer because footer.html already composes it.'
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
mw_assert_same(
	true,
	false !== strpos( $functions_source, 'musicwave_register_block_category' ),
	'The theme must register the shared MusicWave inserter category when Core is not active.'
);
mw_assert_same(
	true,
	false !== strpos( $functions_source, 'musicwave_register_legacy_presentation_block' )
		&& false !== strpos( $functions_source, "'musicwave/' . \$dir" ),
	'Theme registration must retain hidden musicwave/* compatibility aliases after the namespace migration.'
);

$theme_block_files = glob( $theme_directory . '/blocks/*/block.json' ) ?: array();
$expected_theme_blocks = array(
	'music-wave/release-shelf',
	'music-wave/release-slider',
	'music-wave/theme-text',
	'music-wave/theme-toggle',
);
$theme_block_names = array();
foreach ( $theme_block_files as $theme_block_file ) {
	$metadata = json_decode( (string) file_get_contents( $theme_block_file ), true );
	$name     = is_array( $metadata ) && isset( $metadata['name'] ) ? (string) $metadata['name'] : '';
	$theme_block_names[] = $name;
	$label = basename( dirname( $theme_block_file ) ) . '/block.json';
	mw_assert_same( true, 0 === strpos( $name, 'music-wave/' ), $label . ' must use the canonical music-wave/* namespace.' );
	mw_assert_same( 'https://schemas.wp.org/trunk/block.json', is_array( $metadata ) && isset( $metadata['$schema'] ) ? $metadata['$schema'] : '', $label . ' must declare the WordPress block.json schema.' );
	mw_assert_same( true, is_array( $metadata ) && ! empty( $metadata['keywords'] ), $label . ' must provide localized keywords.' );
	mw_assert_same( true, is_array( $metadata ) && isset( $metadata['attributes'] ) && is_array( $metadata['attributes'] ) && isset( $metadata['supports'] ) && is_array( $metadata['supports'] ) && isset( $metadata['example'] ) && is_array( $metadata['example'] ), $label . ' must provide attributes, supports, and example objects.' );
}
foreach ( $expected_theme_blocks as $expected_theme_block ) {
	mw_assert_same( true, in_array( $expected_theme_block, $theme_block_names, true ), 'Theme metadata must include ' . $expected_theme_block . '.' );
}

$core_block_files = glob( dirname( $theme_directory ) . '/music-wave-core/blocks/*/block.json' ) ?: array();
$core_names       = array();
foreach ( $core_block_files as $core_block_file ) {
	$metadata = json_decode( (string) file_get_contents( $core_block_file ), true );
	$name     = is_array( $metadata ) && isset( $metadata['name'] ) ? (string) $metadata['name'] : '';
	$core_names[] = $name;
	$label = basename( dirname( $core_block_file ) ) . '/block.json';
	mw_assert_same( true, 0 === strpos( $name, 'music-wave/' ), $label . ' must use the canonical music-wave/* namespace.' );
	mw_assert_same( 'https://schemas.wp.org/trunk/block.json', is_array( $metadata ) && isset( $metadata['$schema'] ) ? $metadata['$schema'] : '', $label . ' must declare the WordPress block.json schema.' );
	mw_assert_same( true, is_array( $metadata ) && ! empty( $metadata['keywords'] ) && isset( $metadata['attributes'] ) && is_array( $metadata['attributes'] ) && isset( $metadata['supports'] ) && is_array( $metadata['supports'] ) && isset( $metadata['example'] ) && is_array( $metadata['example'] ), $label . ' must provide complete metadata objects.' );
}
mw_assert_same( 27, count( $core_names ), 'Core metadata inventory must contain 27 blocks.' );
mw_assert_same( count( $core_names ), count( array_unique( $core_names ) ), 'Core metadata names must be unique.' );

$theme_json_blocks = isset( $theme_json['settings']['blocks'] ) && is_array( $theme_json['settings']['blocks'] ) ? $theme_json['settings']['blocks'] : array();
foreach ( array( 'music-wave/release-shelf', 'music-wave/release-slider', 'music-wave/theme-text', 'music-wave/theme-toggle' ) as $theme_block_name ) {
	mw_assert_same( true, isset( $theme_json_blocks[ $theme_block_name ] ), 'theme.json settings must expose the canonical ' . $theme_block_name . ' block.' );
}
foreach ( array( 'musicwave/release-shelf', 'musicwave/release-slider', 'musicwave/theme-text', 'musicwave/theme-toggle' ) as $legacy_block_name ) {
	mw_assert_same( true, isset( $theme_json_blocks[ $legacy_block_name ] ), 'theme.json settings must retain the legacy compatibility block ' . $legacy_block_name . '.' );
}
mw_assert_same( true, isset( $theme_json['styles']['blocks']['music-wave/release-shelf'], $theme_json['styles']['blocks']['music-wave/release-slider'], $theme_json['styles']['blocks']['musicwave/release-shelf'], $theme_json['styles']['blocks']['musicwave/release-slider'] ), 'theme.json styles must include canonical and legacy shelf/slider keys.' );

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

	mw_assert_same(
		false,
		1 === preg_match( '/wp:musicwave\/(?:release-shelf|release-slider|theme-text|theme-toggle)\b/', $content ),
		$label . ' must use canonical music-wave/* block comments in bundled content.'
	);

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
		// Namespaced references such as woocommerce/cart-empty-message are
		// registered at runtime by plugins; only the theme's own namespace is
		// verifiable from the repository.
		$own_namespace = false === strpos( $slug, '/' ) || 0 === strpos( $slug, 'musicwave/' );
		if ( $own_namespace ) {
			mw_assert_same( true, in_array( $slug, $registered_patterns, true ), $label . ' must reference an available bundled pattern.' );
		}
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
foreach ( array( '.mw-release-meta__facts--inline', '.mw-release-meta__facts--stack', '.mw-release-meta--panel', '.mw-release-meta__chip' ) as $meta_selector ) {
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
	'membership-panel',
	'music-library',
	'library-button',
	'playlists',
	'add-to-playlist',
	'public-playlists',
	'continue-listening',
	'artists-shelf',
	'taxonomy-shelf',
	'term-hero',
	'playback-queue',
	'add-to-queue',
	'share-button',
	'shuffle-button',
	'request-form',
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

// Public playlists ship as a first-class Site Editor surface: page-playlists
// composes the section directly from blocks — the block itself renders the
// whole experience, so no wrapper template part or single-block pattern may
// duplicate it.
$page_playlists_template = (string) file_get_contents( $theme_directory . '/templates/page-playlists.html' );
mw_assert_same(
	true,
	false !== strpos( $page_playlists_template, 'wp:music-wave/public-playlists' ),
	'page-playlists.html must compose the public playlists section directly from the block.'
);
mw_assert_same(
	false,
	in_array( 'playlists', $registered_parts, true ) || is_file( $theme_directory . '/parts/playlists.html' ),
	'The public playlists experience must live in the block itself; a wrapper template part would duplicate it.'
);
mw_assert_same(
	false,
	in_array( 'musicwave/public-playlists-section', $registered_patterns, true ),
	'A pattern wrapping only the public playlists block would duplicate its inserter entry; keep composition in templates.'
);

foreach ( array( 'imageShape', 'showToggle', 'showPagination', 'sectionUrl', 'sectionLinkLabel' ) as $public_playlist_attribute ) {
	mw_assert_same(
		true,
		false !== strpos( $editor_script, $public_playlist_attribute ),
		'The editor must expose the public playlists ' . $public_playlist_attribute . ' control.'
	);
}

$public_playlists_css = (string) file_get_contents( $theme_directory . '/assets/css/components/playlists.css' );
foreach ( array( '.mw-public-playlists--minimal', '.mw-public-playlists__grid--scroll', '--mw-shelf-columns', '.mw-public-playlists__more', '.mw-public-playlists__art--circle' ) as $public_playlist_selector ) {
	mw_assert_same(
		true,
		false !== strpos( $public_playlists_css, $public_playlist_selector ),
		'The theme must style the public playlists variant ' . $public_playlist_selector . ' in coordination with the release shelf.'
	);
}

// The artists shelf and continue-listening rails reuse the shared shelf
// chrome, so the theme must ship their component styles and keep them
// registered as style modules.
$artists_shelf_css = (string) file_get_contents( $theme_directory . '/assets/css/components/artists-shelf.css' );
foreach ( array( '.mw-artists-shelf__avatar--circle', '.mw-artists-shelf__initial', '.mw-release-shelf--list .mw-artists-shelf__item', '.mw-artists-shelf__empty' ) as $artists_shelf_selector ) {
	mw_assert_same(
		true,
		false !== strpos( $artists_shelf_css, $artists_shelf_selector ),
		'The theme must style the artists shelf variant ' . $artists_shelf_selector . ' in coordination with the release shelf.'
	);
}
$listening_css = (string) file_get_contents( $theme_directory . '/assets/css/components/listening.css' );
foreach ( array( '.mw-continue-listening__panel', '.mw-continue-listening__header', '.mw-continue-listening__empty' ) as $listening_selector ) {
	mw_assert_same(
		true,
		false !== strpos( $listening_css, $listening_selector ),
		'The theme must style the continue-listening surface ' . $listening_selector . '.'
	);
}
mw_assert_same(
	true,
	false !== strpos( $functions_source, "'musicwave-artists-shelf'" ) && false !== strpos( $functions_source, "'musicwave-listening'" ),
	'functions.php must register the artists-shelf and listening style modules for frontend and editor parity.'
);
mw_assert_same(
	true,
	false !== strpos( $functions_source, "'musicwave-taxonomy-shelf'" ),
	'functions.php must register the taxonomy-shelf style module for frontend and editor parity.'
);
mw_assert_same(
	true,
	in_array( 'musicwave/popular-artists', $registered_patterns, true ),
	'The theme must ship the popular-artists pattern so editors can drop an artists shelf anywhere.'
);
$taxonomy_shelf_css = (string) file_get_contents( $theme_directory . '/assets/css/components/taxonomy-shelf.css' );
foreach ( array( '.mw-terms-shelf__tile--style-colorful.mw-terms-shelf__tile--hue-1', '.mw-terms-shelf__tile--style-plain', '.mw-release-shelf--list .mw-terms-shelf__tile', '.mw-terms-shelf__empty' ) as $taxonomy_shelf_selector ) {
	mw_assert_same(
		true,
		false !== strpos( $taxonomy_shelf_css, $taxonomy_shelf_selector ),
		'The theme must style the taxonomy shelf variant ' . $taxonomy_shelf_selector . ' in coordination with the release shelf.'
	);
}
mw_assert_same(
	true,
	in_array( 'musicwave/genre-mood-browse', $registered_patterns, true ),
	'The theme must ship the genre-mood-browse pattern so editors can drop browse tiles anywhere.'
);

// Archive pages must open with the shared term hero instead of hand-rolled
// page headings, so artists and genres get the same platform-grade landing.
$artist_archive_template = (string) file_get_contents( $theme_directory . '/templates/taxonomy-mw_artist.html' );
mw_assert_same(
	true,
	false !== strpos( $artist_archive_template, 'wp:music-wave/term-hero' ),
	'taxonomy-mw_artist.html must compose its header from the term hero block.'
);
mw_assert_same(
	false,
	false !== strpos( $artist_archive_template, 'music-wave/artist-profile' ) || false !== strpos( $artist_archive_template, 'wp:query-title' ),
	'the artist archive must not duplicate the hero with a profile card or generic archive title.'
);
$genre_archive_template = (string) file_get_contents( $theme_directory . '/templates/taxonomy-mw_genre.html' );
mw_assert_same(
	true,
	false !== strpos( $genre_archive_template, 'wp:music-wave/term-hero' ),
	'taxonomy-mw_genre.html must compose its header from the term hero block.'
);
$term_hero_css = (string) file_get_contents( $theme_directory . '/assets/css/components/term-hero.css' );
foreach ( array( '.mw-term-hero--banner', '.mw-term-hero__scrim', '.mw-term-hero--compact', '.mw-term-hero--hue-1 .mw-term-hero__media', '.mw-term-hero--size-tall' ) as $term_hero_selector ) {
	mw_assert_same(
		true,
		false !== strpos( $term_hero_css, $term_hero_selector ),
		'The theme must style the term hero variant ' . $term_hero_selector . '.'
	);
}
mw_assert_same(
	true,
	false !== strpos( $functions_source, "'musicwave-term-hero'" ),
	'functions.php must register the term-hero style module for frontend and editor parity.'
);
$queue_css = (string) file_get_contents( $theme_directory . '/assets/css/components/queue.css' );
foreach ( array( '.mw-playback-queue__item', '.mw-playback-queue__notice--error', '.mw-playback-queue__controls', '.mw-playback-queue__guest-text' ) as $queue_selector ) {
	mw_assert_same(
		true,
		false !== strpos( $queue_css, $queue_selector ),
		'The theme must style the playback queue surface ' . $queue_selector . '.'
	);
}
mw_assert_same(
	true,
	false !== strpos( $functions_source, "'musicwave-queue'" ),
	'functions.php must register the queue style module for frontend and editor parity.'
);
foreach ( array( '.mw-add-to-queue--guest', '.mw-add-to-queue__in' ) as $add_queue_selector ) {
	mw_assert_same(
		true,
		false !== strpos( $queue_css, $add_queue_selector ),
		'The theme must style the add-to-queue variant ' . $add_queue_selector . '.'
	);
}

$theme_editor_script = (string) file_get_contents( $theme_directory . '/assets/editor-blocks.js' );
foreach ( array( 'playlistOrderBy', 'playlistSearch', "'playlists'" ) as $shelf_playlist_control ) {
	mw_assert_same(
		true,
		false !== strpos( $theme_editor_script, $shelf_playlist_control ),
		'The release shelf editor must expose the public playlist source control (' . $shelf_playlist_control . ').'
	);
}

// The release shelf hero slider ("اسلایدر هیرو") is the reference-style
// crossfading layout: a dedicated stylesheet, a self-initializing fade
// engine, and the render-time script enqueue wired in functions.php.
$hero_slider_css = (string) file_get_contents( $theme_directory . '/assets/css/components/hero-slider.css' );
foreach ( array( '.mw-hero-slide', '.mw-hero-slide.is-active', '.mw-hero-slide__scrim', '.mw-hero-slider__dot.is-active', 'prefers-reduced-motion' ) as $hero_slider_selector ) {
	mw_assert_same(
		true,
		false !== strpos( $hero_slider_css, $hero_slider_selector ),
		'The theme must style the hero slider variant ' . $hero_slider_selector . ' in coordination with the release shelf.'
	);
}

$hero_slider_script = (string) file_get_contents( $theme_directory . '/assets/hero-slider.js' );
foreach ( array( 'data-mw-hero-slider', 'data-mw-hero-previous', 'data-mw-hero-next', '[data-mw-hero-dot]', 'prefers-reduced-motion', 'mw-page-rendered' ) as $hero_slider_hook ) {
	mw_assert_same(
		true,
		false !== strpos( $hero_slider_script, $hero_slider_hook ),
		'The hero slider script must bind the ' . $hero_slider_hook . ' hook.'
	);
}

mw_assert_same(
	true,
	false !== strpos( $functions_source, 'musicwave_render_hero_slider' )
		&& false !== strpos( $functions_source, "'musicwave-hero-slider'" )
		&& false !== strpos( $functions_source, "'slider'" ),
	'functions.php must render the hero slider layout and register its style module and render-time script.'
);

$release_shelf_meta  = json_decode( (string) file_get_contents( $theme_directory . '/blocks/release-shelf/block.json' ), true );
$release_shelf_attrs = is_array( $release_shelf_meta ) && isset( $release_shelf_meta['attributes'] ) ? (array) $release_shelf_meta['attributes'] : array();
foreach ( array( 'layout', 'autoplay', 'showArrows', 'showDots', 'interval' ) as $hero_attribute ) {
	mw_assert_same(
		true,
		isset( $release_shelf_attrs[ $hero_attribute ] ),
		'The release shelf block.json must declare the ' . $hero_attribute . ' attribute for the hero slider layout.'
	);
}

mw_assert_same(
	true,
	false !== strpos( $theme_editor_script, "'slider'" ),
	'The release shelf editor must expose the hero slider layout option.'
);

// ---- WAVE presentation layer (editorial reference parity) ----
// Every WAVE variant is a selectable option on its block: block.json must
// declare the attribute, the editor must expose it, the theme must style it,
// and the bundled pattern/template must be able to compose it.
foreach ( array( 'cardStyle', 'heroStyle', 'showBadge', 'showMeta' ) as $wave_attribute ) {
	mw_assert_same(
		true,
		isset( $release_shelf_attrs[ $wave_attribute ] ),
		'The release shelf block.json must declare the ' . $wave_attribute . ' attribute for the WAVE card/hero styles.'
	);
}
mw_assert_same(
	'classic',
	$release_shelf_attrs['cardStyle']['default'] ?? null,
	'The release shelf must default to the classic card style so existing sites do not change on upgrade.'
);
foreach ( array( "'chart'", "'wave'", "'editorial'", 'showBadge', 'showMeta' ) as $wave_editor_token ) {
	mw_assert_same(
		true,
		false !== strpos( $theme_editor_script, $wave_editor_token ),
		'The release shelf editor must expose the WAVE option ' . $wave_editor_token . '.'
	);
}
$shelf_css       = (string) file_get_contents( $theme_directory . '/assets/css/components/shelf.css' );
$hero_slider_css = (string) file_get_contents( $theme_directory . '/assets/css/components/hero-slider.css' );
foreach ( array( '.mw-release-shelf--cards-wave', '.mw-release-shelf__badge', '.mw-release-shelf__meta', '.mw-release-shelf__chart', '.mw-release-shelf__row[data-mw-playing]' ) as $wave_shelf_selector ) {
	mw_assert_same(
		true,
		false !== strpos( $shelf_css, $wave_shelf_selector ),
		'The theme must style the WAVE shelf surface ' . $wave_shelf_selector . '.'
	);
}
foreach ( array( '.mw-hero-slider--editorial', '.mw-hero-slide__stage', '.mw-hero-slide__facts' ) as $wave_hero_selector ) {
	mw_assert_same(
		true,
		false !== strpos( $hero_slider_css, $wave_hero_selector ),
		'The theme must style the editorial hero ' . $wave_hero_selector . '.'
	);
}
$utilities_css = (string) file_get_contents( $theme_directory . '/assets/css/utilities.css' );
foreach ( array( '.mw-badge--premium', '.mw-eyebrow', '.mw-ping', '.mw-vinyl' ) as $wave_utility ) {
	mw_assert_same(
		true,
		false !== strpos( $utilities_css, $wave_utility ),
		'utilities.css must ship the shared WAVE primitive ' . $wave_utility . ' so components do not re-implement it.'
	);
}
$tokens_css = (string) file_get_contents( $theme_directory . '/assets/css/tokens.css' );
foreach ( array( '--mw-color-premium', '--mw-color-on-premium', '--mw-color-accent-alt' ) as $wave_token ) {
	mw_assert_same(
		true,
		false !== strpos( $tokens_css, $wave_token . ':' ),
		'tokens.css must define the WAVE token ' . $wave_token . '.'
	);
}
$wave_variation = json_decode( (string) file_get_contents( $theme_directory . '/styles/wave.json' ), true );
mw_assert_same(
	true,
	is_array( $wave_variation ) && isset( $wave_variation['settings']['custom']['wave']['premium'], $wave_variation['settings']['custom']['scheme']['dark'] ),
	'styles/wave.json must be a valid style variation that carries the WAVE premium token and a dark/light scheme.'
);
mw_assert_same(
	true,
	in_array( 'musicwave/wave-home-layout', $registered_patterns, true ),
	'The theme must ship the wave-home-layout pattern so editors can compose the reference home page in one insert.'
);
$wave_home_pattern = (string) file_get_contents( $theme_directory . '/patterns/wave-home-layout.php' );
foreach ( array( '"heroStyle":"editorial"', '"layout":"chart"', '"cardStyle":"wave"', '"cardStyle":"mood"', 'wp:music-wave/continue-listening', 'wp:music-wave/artists-shelf', 'wp:music-wave/taxonomy-shelf' ) as $wave_pattern_token ) {
	mw_assert_same(
		true,
		false !== strpos( $wave_home_pattern, $wave_pattern_token ),
		'wave-home-layout must compose the reference sections through block options (' . $wave_pattern_token . ').'
	);
}
$single_release_source = (string) file_get_contents( $theme_directory . '/templates/single-mw_release.html' );
foreach ( array( 'is-style-mw-hero-vinyl', 'is-style-glow', 'is-style-wave', '"cardStyle":"wave"' ) as $wave_single_token ) {
	mw_assert_same(
		true,
		false !== strpos( $single_release_source, $wave_single_token ),
		'single-mw_release.html must opt into the WAVE album-page styles via block styles/attributes (' . $wave_single_token . ').'
	);
}
foreach ( array( 'mw-hero-card', 'mw-hero-vinyl' ) as $wave_group_style ) {
	mw_assert_same(
		true,
		false !== strpos( $functions_source, "'name'  => '" . $wave_group_style . "'" ),
		'functions.php must register the ' . $wave_group_style . ' group style so the WAVE hero is selectable in the Site Editor.'
	);
}
$block_styles_css = (string) file_get_contents( $theme_directory . '/assets/css/components/block-styles.css' );
foreach ( array( '.is-style-mw-hero-card', '.is-style-mw-hero-vinyl' ) as $wave_group_selector ) {
	mw_assert_same(
		true,
		false !== strpos( $block_styles_css, $wave_group_selector ),
		'block-styles.css must style the group variation ' . $wave_group_selector . '.'
	);
}

echo "Template integrity checks passed.\n";
