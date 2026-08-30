<?php
/**
 * MusicWave theme bootstrap.
 *
 * @package MusicWave
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register theme features and editor parity.
 *
 * Widgets and template parts are intentionally widget-compatible so a buyer
 * who expects Appearance → Widgets finds editable widget areas inside the
 * Site Editor without ever touching code.
 *
 * @return void
 */
function musicwave_setup(): void {
	load_theme_textdomain( 'musicwave', get_template_directory() . '/languages' );
	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'title-tag' );
	add_theme_support( 'editor-styles' );
	add_editor_style( array_merge( array( 'style.css' ), musicwave_editor_style_files() ) );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'woocommerce' );
	add_theme_support( 'widgets' );
	add_theme_support( 'widgets-block-editor' );
	add_theme_support( 'block-template-parts' );
}
add_action( 'after_setup_theme', 'musicwave_setup' );

/**
 * Return ordered modular styles used in both the frontend and editor.
 *
 * @return array<string, array<string, array<int, string>|string>>
 */
function musicwave_style_modules(): array {
	return array(
		'musicwave-tokens'          => array(
			'file'         => 'assets/css/tokens.css',
			'dependencies' => array(),
		),
		'musicwave-base'            => array(
			'file'         => 'assets/css/base.css',
			'dependencies' => array( 'musicwave-tokens' ),
		),
		'musicwave-layout'          => array(
			'file'         => 'assets/css/layout.css',
			'dependencies' => array( 'musicwave-base' ),
		),
		'musicwave-navigation'      => array(
			'file'         => 'assets/css/components/navigation.css',
			'dependencies' => array( 'musicwave-layout' ),
		),
		'musicwave-utilities'       => array(
			'file'         => 'assets/css/utilities.css',
			'dependencies' => array( 'musicwave-base' ),
		),
		'musicwave-theme-toggle'    => array(
			'file'         => 'assets/css/components/theme-toggle.css',
			'dependencies' => array( 'musicwave-navigation' ),
		),
		'musicwave-catalog'         => array(
			'file'         => 'assets/css/components/catalog.css',
			'dependencies' => array( 'musicwave-utilities' ),
		),
		'musicwave-catalog-filters' => array(
			'file'         => 'assets/css/components/catalog-filters.css',
			'dependencies' => array( 'musicwave-catalog' ),
		),
		'musicwave-artist'          => array(
			'file'         => 'assets/css/components/artist.css',
			'dependencies' => array( 'musicwave-utilities' ),
		),
		'musicwave-artists-shelf'   => array(
			'file'         => 'assets/css/components/artists-shelf.css',
			'dependencies' => array( 'musicwave-utilities', 'musicwave-shelf' ),
		),
		'musicwave-taxonomy-shelf'  => array(
			'file'         => 'assets/css/components/taxonomy-shelf.css',
			'dependencies' => array( 'musicwave-utilities', 'musicwave-shelf' ),
		),
		'musicwave-term-hero'       => array(
			'file'         => 'assets/css/components/term-hero.css',
			'dependencies' => array( 'musicwave-utilities' ),
		),
		'musicwave-queue'           => array(
			'file'         => 'assets/css/components/queue.css',
			'dependencies' => array( 'musicwave-utilities' ),
		),
		'musicwave-access'          => array(
			'file'         => 'assets/css/components/access.css',
			'dependencies' => array( 'musicwave-utilities' ),
		),
		'musicwave-downloads'       => array(
			'file'         => 'assets/css/components/downloads.css',
			'dependencies' => array( 'musicwave-access' ),
		),
		'musicwave-player'          => array(
			'file'         => 'assets/css/components/player.css',
			'dependencies' => array( 'musicwave-utilities' ),
		),
		'musicwave-global-player'   => array(
			'file'         => 'assets/css/components/global-player.css',
			'dependencies' => array( 'musicwave-utilities' ),
		),
		'musicwave-collections'     => array(
			'file'         => 'assets/css/components/collections.css',
			'dependencies' => array( 'musicwave-utilities' ),
		),
		'musicwave-related'         => array(
			'file'         => 'assets/css/components/related.css',
			'dependencies' => array( 'musicwave-catalog', 'musicwave-player' ),
		),
		'musicwave-slider'          => array(
			'file'         => 'assets/css/components/slider.css',
			'dependencies' => array( 'musicwave-catalog' ),
		),
		'musicwave-shelf'           => array(
			'file'         => 'assets/css/components/shelf.css',
			'dependencies' => array( 'musicwave-catalog' ),
		),
		'musicwave-commerce'        => array(
			'file'         => 'assets/css/components/commerce.css',
			'dependencies' => array( 'musicwave-utilities' ),
		),
		'musicwave-account-library' => array(
			'file'         => 'assets/css/components/account-library.css',
			'dependencies' => array( 'musicwave-utilities' ),
		),
		'musicwave-playlists'       => array(
			'file'         => 'assets/css/components/playlists.css',
			'dependencies' => array( 'musicwave-utilities' ),
		),
		'musicwave-listening'       => array(
			'file'         => 'assets/css/components/listening.css',
			'dependencies' => array( 'musicwave-utilities', 'musicwave-shelf' ),
		),
		'musicwave-notifications'   => array(
			'file'         => 'assets/css/components/notifications.css',
			'dependencies' => array( 'musicwave-utilities' ),
		),
		'musicwave-block-styles'    => array(
			'file'         => 'assets/css/components/block-styles.css',
			'dependencies' => array( 'musicwave-utilities' ),
		),
		'musicwave-accessibility'   => array(
			'file'         => 'assets/css/accessibility.css',
			'dependencies' => array( 'musicwave-base' ),
		),
	);
}

/**
 * Return stylesheet paths for WordPress editor parity.
 *
 * @return array<int, string>
 */
function musicwave_editor_style_files(): array {
	$files = array();
	foreach ( musicwave_style_modules() as $style ) {
		$files[] = $style['file'];
	}
	$files[] = 'assets/css/editor.css';

	return $files;
}

/**
 * Load public modular styles and theme preference behavior.
 *
 * @return void
 */
function musicwave_enqueue_assets(): void {
	$theme   = wp_get_theme();
	$version = (string) $theme->get( 'Version' );

	wp_enqueue_style(
		'musicwave',
		get_stylesheet_uri(),
		array(),
		$version
	);
	foreach ( musicwave_style_modules() as $handle => $style ) {
		wp_enqueue_style(
			$handle,
			get_template_directory_uri() . '/' . $style['file'],
			$style['dependencies'],
			$version
		);
	}

	wp_enqueue_script(
		'musicwave-theme-preference',
		get_template_directory_uri() . '/assets/theme-preference.js',
		array(),
		$version,
		true
	);
	if ( function_exists( 'wp_set_script_translations' ) ) {
		wp_set_script_translations( 'musicwave-theme-preference', 'musicwave' );
	}
	// Registered only: the slider script enqueues at render time of the
	// musicwave/release-slider block, so routes without a slider ship no
	// slider bytes (PROJECT_PLAN.md Stage 4 deliverable 4).
	wp_register_script(
		'musicwave-slider',
		get_template_directory_uri() . '/assets/slider.js',
		array(),
		$version,
		true
	);
	if ( function_exists( 'wp_set_script_translations' ) ) {
		wp_set_script_translations( 'musicwave-slider', 'musicwave' );
	}
	wp_localize_script(
		'musicwave-slider',
		'musicwaveSlider',
		array(
			/* translators: %d: slide group index. */
			'goToGroup' => __( 'Go to slide group %d', 'musicwave' ),
			/* translators: 1: current slide group index, 2: total slide groups. */
			'status'    => __( 'Slide group %1$d of %2$d', 'musicwave' ),
		)
	);
	wp_localize_script(
		'musicwave-theme-preference',
		'musicwaveThemePreference',
		array(
			'labels' => array(
				'system' => __( 'Use system theme', 'musicwave' ),
				'light'  => __( 'Use light theme', 'musicwave' ),
				'dark'   => __( 'Use dark theme', 'musicwave' ),
			),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'musicwave_enqueue_assets' );

/**
 * Apply a saved display preference before the page paints.
 *
 * @return void
 */
function musicwave_preload_theme_preference(): void {
	echo "<script>(function(){try{var t=window.localStorage.getItem('musicwave-theme');if('light'===t||'dark'===t){document.documentElement.setAttribute('data-mw-theme',t);}}catch(e){}}());</script>\n";
}
add_action( 'wp_head', 'musicwave_preload_theme_preference', 0 );

/**
 * Defer non-critical scripts + preload critical tokens for LCP.
 *
 * @param string $tag    Script tag.
 * @param string $handle Script handle.
 * @return string
 */
function musicwave_filter_script_tag( string $tag, string $handle ): string {
	$defer = array( 'musicwave-theme-preference', 'musicwave-slider', 'music-wave-playlists', 'music-wave-preview-player' );
	// WordPress 6.3+ may already add `defer` via the script strategy API (data-wp-strategy="defer").
	// Avoid double-defer and respect an existing strategy attribute.
	if ( in_array( $handle, $defer, true ) && false === strpos( $tag, ' defer' ) && false === strpos( $tag, 'data-wp-strategy' ) ) {
		$tag = str_replace( ' src', ' defer src', $tag );
	}
	return $tag;
}
add_filter( 'script_loader_tag', 'musicwave_filter_script_tag', 10, 2 );

/**
 * Register pattern categories used by the Site Editor.
 *
 * Non-technical buyers browse patterns by category; keeping `musicwave` as the
 * primary label plus `featured` and `widget` groups prevents the editor from
 * showing ungrouped or miscategorized patterns.
 *
 * @return void
 */
function musicwave_register_pattern_categories(): void {
	$categories = array(
		'musicwave'         => __( 'MusicWave', 'musicwave' ),
		'featured'          => __( 'Featured', 'musicwave' ),
		'musicwave-hero'    => __( 'MusicWave Hero', 'musicwave' ),
		'musicwave-shelves' => __( 'MusicWave Shelves', 'musicwave' ),
		'musicwave-widgets' => __( 'MusicWave Widgets', 'musicwave' ),
		'musicwave-footers' => __( 'MusicWave Footers', 'musicwave' ),
		'musicwave-headers' => __( 'MusicWave Headers', 'musicwave' ),
		'musicwave-cards'   => __( 'MusicWave Cards', 'musicwave' ),
		'musicwave-cta'     => __( 'MusicWave Call to Action', 'musicwave' ),
	);
	foreach ( $categories as $slug => $label ) {
		if ( ! WP_Block_Pattern_Categories_Registry::get_instance()->is_registered( $slug ) ) {
			register_block_pattern_category(
				$slug,
				array( 'label' => $label )
			);
		}
	}
}
add_action( 'init', 'musicwave_register_pattern_categories' );

/**
 * Register curated block styles for one-click visual variations.
 *
 * All styles are pure CSS class hooks consumed by the modular CSS;
 * the buyer never edits code — they pick a style in the Site Editor's
 * Styles panel or block sidebar.
 *
 * @return void
 */
function musicwave_register_block_styles(): void {
	$styles = array(
		array(
			'block' => 'core/button',
			'name'  => 'mw-pill',
			'label' => __( 'Pill (MusicWave)', 'musicwave' ),
		),
		array(
			'block' => 'core/button',
			'name'  => 'mw-outline-accent',
			'label' => __( 'Outline accent', 'musicwave' ),
		),
		array(
			'block' => 'core/group',
			'name'  => 'mw-surface',
			'label' => __( 'Surface card', 'musicwave' ),
		),
		array(
			'block' => 'core/group',
			'name'  => 'mw-surface-raised',
			'label' => __( 'Raised surface', 'musicwave' ),
		),
		array(
			'block' => 'core/columns',
			'name'  => 'mw-tight-gap',
			'label' => __( 'Tight gap', 'musicwave' ),
		),
		array(
			'block' => 'core/separator',
			'name'  => 'mw-accent',
			'label' => __( 'Accent line', 'musicwave' ),
		),
		array(
			'block' => 'core/image',
			'name'  => 'mw-rounded',
			'label' => __( 'Rounded', 'musicwave' ),
		),
		array(
			'block' => 'core/image',
			'name'  => 'mw-circle',
			'label' => __( 'Circle', 'musicwave' ),
		),
		array(
			'block' => 'core/list',
			'name'  => 'mw-checklist',
			'label' => __( 'Checklist', 'musicwave' ),
		),
	);
	foreach ( $styles as $style ) {
		if ( function_exists( 'register_block_style' ) ) {
			register_block_style(
				$style['block'],
				array(
					'name'  => $style['name'],
					'label' => $style['label'],
				)
			);
		}
	}
}
add_action( 'init', 'musicwave_register_block_styles' );

/**
 * Register legacy widget areas for hybrid / classic-widget compatibility.
 *
 * Block themes do not require sidebars, but many buyers still search for
 * Appearance → Widgets. These sidebars back the editable template parts
 * (sidebar + footer-widgets) so legacy widgets and block widgets both work
 * without ever exposing raw PHP to the buyer.
 *
 * @return void
 */
function musicwave_widgets_init(): void {
	$sidebars = array(
		array(
			'name'          => __( 'MusicWave sidebar', 'musicwave' ),
			'id'            => 'musicwave-sidebar',
			'description'   => __( 'Appears in the Sidebar template part (Appearance → Editor → Template Parts → Sidebar). Add any block or legacy widget here. Fully editable visually — no code required.', 'musicwave' ),
			'before_widget' => '<section id="%1$s" class="widget mw-widget %2$s">',
			'after_widget'  => '</section>',
			'before_title'  => '<h3 class="widget-title">',
			'after_title'   => '</h3>',
		),
		array(
			'name'          => __( 'MusicWave footer widgets', 'musicwave' ),
			'id'            => 'musicwave-footer-widgets',
			'description'   => __( 'Appears in the Footer widgets template part (Appearance → Editor → Template Parts → Footer widgets). Drag any block or legacy widget — colors follow global Styles.', 'musicwave' ),
			'before_widget' => '<section id="%1$s" class="widget mw-widget %2$s">',
			'after_widget'  => '</section>',
			'before_title'  => '<h3 class="widget-title">',
			'after_title'   => '</h3>',
		),
		array(
			'name'          => __( 'MusicWave shop sidebar', 'musicwave' ),
			'id'            => 'musicwave-shop-sidebar',
			'description'   => __( 'Appears in the Shop sidebar template part (Template Parts → Shop sidebar). Use WooCommerce filters, categories, or any block.', 'musicwave' ),
			'before_widget' => '<section id="%1$s" class="widget mw-widget %2$s">',
			'after_widget'  => '</section>',
			'before_title'  => '<h3 class="widget-title">',
			'after_title'   => '</h3>',
		),
		array(
			'name'          => __( 'MusicWave header widgets', 'musicwave' ),
			'id'            => 'musicwave-header-widgets',
			'description'   => __( 'Optional: small widget area for the header (e.g., announcement, language switcher). Add blocks under Appearance → Widgets or directly in the Header template part.', 'musicwave' ),
			'before_widget' => '<section id="%1$s" class="widget mw-widget %2$s">',
			'after_widget'  => '</section>',
			'before_title'  => '<h3 class="widget-title">',
			'after_title'   => '</h3>',
		),
	);
	foreach ( $sidebars as $sidebar ) {
		register_sidebar( $sidebar );
	}
}
add_action( 'widgets_init', 'musicwave_widgets_init' );

/**
 * Load and decode a theme block's block.json without requiring the block to be registered.
 *
 * @param string $block_dir Directory name inside musicwave/blocks.
 * @return array<string, mixed>|null
 */
function musicwave_load_block_json( string $block_dir ): ?array {
	$file = get_template_directory() . '/blocks/' . $block_dir . '/block.json';
	if ( ! is_readable( $file ) ) {
		return null;
	}
	// Local bundled metadata file, never a remote URL.
	$raw = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	if ( ! is_string( $raw ) || '' === $raw ) {
		return null;
	}
	$data = json_decode( $raw, true );
	return is_array( $data ) ? $data : null;
}

/**
 * Register presentation-only blocks for translated default template text.
 *
 * Metadata lives in musicwave/blocks/<block>/block.json as the single source
 * of truth for attributes and supports; PHP only injects the render callback.
 * This eliminates the previous triple duplication between PHP registration,
 * block.json, and the JS localization that caused Site Editor validation
 * errors when the maps drifted (PROJECT_PLAN.md P1).
 *
 * @return void
 */
function musicwave_register_presentation_blocks(): void {
	$blocks = array(
		'theme-text'     => array(
			'dir'      => 'theme-text',
			'callback' => 'musicwave_render_theme_text',
		),
		'theme-toggle'   => array(
			'dir'      => 'theme-toggle',
			'callback' => 'musicwave_render_theme_toggle',
		),
		'release-slider' => array(
			'dir'      => 'release-slider',
			'callback' => 'musicwave_render_release_slider',
		),
		'release-shelf'  => array(
			'dir'      => 'release-shelf',
			'callback' => 'musicwave_render_release_shelf',
		),
	);

	foreach ( $blocks as $config ) {
		$dir      = (string) $config['dir'];
		$callback = (string) $config['callback'];
		$path     = get_template_directory() . '/blocks/' . $dir;
		if ( is_readable( $path . '/block.json' ) ) {
			// block.json is authoritative; only the render callback is added in PHP.
			register_block_type( $path, array( 'render_callback' => $callback ) );
			continue;
		}
		// Fallback: if the bundled json is missing (e.g. incomplete deployment), do not fatal.
	}
}
add_action( 'init', 'musicwave_register_presentation_blocks' );

/**
 * Register JavaScript editor counterparts for template presentation blocks.
 *
 * The block.json files are the single source of truth; this loader reads them
 * at runtime so JS and PHP never diverge. Non-technical buyers get a clear,
 * translated inspector without ever seeing raw JSON or PHP.
 *
 * @return void
 */
function musicwave_enqueue_presentation_editor_blocks(): void {
	$theme = wp_get_theme();
	wp_enqueue_script(
		'musicwave-presentation-blocks',
		get_template_directory_uri() . '/assets/editor-blocks.js',
		array( 'wp-block-editor', 'wp-blocks', 'wp-components', 'wp-element', 'wp-i18n', 'wp-server-side-render' ),
		(string) $theme->get( 'Version' ),
		true
	);
	if ( function_exists( 'wp_set_script_translations' ) ) {
		wp_set_script_translations( 'musicwave-presentation-blocks', 'musicwave' );
	}

	$dirs      = array( 'theme-text', 'theme-toggle', 'release-slider', 'release-shelf' );
	$localized = array();
	foreach ( $dirs as $dir ) {
		$meta = musicwave_load_block_json( $dir );
		if ( null === $meta || empty( $meta['name'] ) ) {
			continue;
		}
		$entry = array(
			'name' => (string) $meta['name'],
		);
		// Title / description / icon are already translated in block.json's textdomain,
		// but re-translate via __() for currency and for the classic localization path.
		if ( isset( $meta['title'] ) ) {
			// Title comes from block.json; keep the PHP translation wrapper for consistency.
			$title_map      = array(
				'musicwave/theme-text'     => __( 'MusicWave template text', 'musicwave' ),
				'musicwave/theme-toggle'   => __( 'MusicWave display preference', 'musicwave' ),
				'musicwave/release-slider' => __( 'MusicWave release slider', 'musicwave' ),
				'musicwave/release-shelf'  => __( 'MusicWave release shelf', 'musicwave' ),
			);
			$entry['title'] = $title_map[ $meta['name'] ] ?? (string) $meta['title'];
		}
		if ( isset( $meta['description'] ) ) {
			$desc_map = array(
				'musicwave/release-slider' => __( 'A responsive, accessible slider for featured or recent releases.', 'musicwave' ),
				'musicwave/release-shelf'  => __( 'A configurable grid, horizontal shelf, or compact list of releases.', 'musicwave' ),
			);
			if ( isset( $desc_map[ $meta['name'] ] ) ) {
				$entry['description'] = $desc_map[ $meta['name'] ];
			} else {
				$entry['description'] = (string) $meta['description'];
			}
		}
		if ( isset( $meta['icon'] ) ) {
			$entry['icon'] = (string) $meta['icon'];
		}
		$entry['attributes'] = isset( $meta['attributes'] ) && is_array( $meta['attributes'] ) ? $meta['attributes'] : array();
		$entry['supports']   = isset( $meta['supports'] ) && is_array( $meta['supports'] ) ? $meta['supports'] : array();
		$localized[]         = $entry;
	}

	wp_localize_script(
		'musicwave-presentation-blocks',
		'musicwavePresentationBlocks',
		$localized
	);
}
add_action( 'enqueue_block_editor_assets', 'musicwave_enqueue_presentation_editor_blocks' );

/**
 * Register a recovery screen for database-saved template overrides.
 *
 * WordPress stores Site Editor customizations in the database and those
 * records override repaired files shipped by the theme. The recovery screen
 * lets an administrator explicitly restore only MusicWave's built-in
 * templates while leaving posts, releases, menus, and global styles intact.
 *
 * @return void
 */
function musicwave_register_template_repair_page(): void {
	add_theme_page(
		__( 'MusicWave template repair', 'musicwave' ),
		__( 'MusicWave template repair', 'musicwave' ),
		'edit_theme_options',
		'musicwave-template-repair',
		'musicwave_render_template_repair_page'
	);
}
add_action( 'admin_menu', 'musicwave_register_template_repair_page' );

/**
 * Return the theme templates that may safely fall back to their bundled files.
 *
 * The footer-widgets and sidebar parts are the buyer-editable widget areas;
 * they must be repairable so a broken Site Editor customization never requires
 * PHP knowledge to recover.
 *
 * @return array<string, array<int, string>>
 */
function musicwave_repairable_template_slugs(): array {
	return array(
		'wp_template'      => array(
			'404',
			'archive',
			'archive-mw_release',
			'home',
			'index',
			'page',
			'page-account',
			'page-browse',
			'page-playlists',
			'page-cart',
			'page-checkout',
			'page-music-home',
			'page-no-sidebar',
			'page-wide',
			'page-landing',
			'page-shop',
			'page-with-sidebar',
			'search',
			'single',
			'single-mw_release',
			'single-product',
			'taxonomy-mw_artist',
			'taxonomy-mw_genre',
			'archive-product',
		),
		'wp_template_part' => array( 'header', 'header-centered', 'header-minimal', 'footer', 'footer-widgets', 'footer-simple', 'sidebar', 'sidebar-shop', 'hero' ),
	);
}

/**
 * Return the canonical account template slug.
 */
function musicwave_account_template_slug(): string {
	return 'page-account';
}

/**
 * Return template slugs that resolve to the unified Music account template.
 *
 * Covers the retired account templates and the common page slugs sites use
 * for their library, dashboard, and My Account pages.
 *
 * @return array<int, string>
 */
function musicwave_account_template_aliases(): array {
	return array(
		'page-library',
		'page-dashboard',
		'page-my-account',
		'page-music-library',
		'page-music-user-dashboard',
		'page-user-dashboard',
	);
}

/**
 * Resolve every legacy account template to the single Music account template.
 *
 * The library, dashboard, and My Account pages share one bundled template so
 * administrators customize a single layout in the Site Editor. Previously
 * assigned templates and slug-matched pages keep working through aliases.
 *
 * @param mixed  $template      Resolved template object, or null.
 * @param string $id            Requested theme//slug template identifier.
 * @param string $template_type Template type (wp_template).
 * @return mixed
 */
function musicwave_alias_account_template( $template, string $id, string $template_type ) {
	if ( null !== $template || 'wp_template' !== $template_type ) {
		return $template;
	}

	$parts = explode( '//', $id );
	$slug  = (string) end( $parts );
	if ( ! in_array( $slug, musicwave_account_template_aliases(), true ) ) {
		return $template;
	}

	return get_block_template( get_stylesheet() . '//' . musicwave_account_template_slug(), $template_type );
}
add_filter( 'get_block_template', 'musicwave_alias_account_template', 10, 3 );

/**
 * Make the account aliases effective for front-end template resolution.
 *
 * resolve_block_template() looks candidates up through the plural
 * get_block_templates() query, which the singular alias filter above never
 * sees; inject the canonical account template whenever a hierarchy asks for
 * one of the alias slugs so /my-account/ and the retired slugs all render
 * the unified Music account page.
 *
 * @param array<int, mixed> $templates     Found templates.
 * @param array<string, mixed> $query      Query (contains slug__in).
 * @param string            $template_type Template type.
 * @return array<int, mixed>
 */
function musicwave_alias_account_templates_in_query( array $templates, array $query, string $template_type ) {
	if ( 'wp_template' !== $template_type || empty( $query['slug__in'] ) || ! is_array( $query['slug__in'] ) ) {
		return $templates;
	}
	$aliases = array_values( array_intersect( $query['slug__in'], musicwave_account_template_aliases() ) );
	if ( empty( $aliases ) ) {
		return $templates;
	}
	foreach ( $templates as $existing ) {
		if ( is_object( $existing ) && in_array( $existing->slug, $aliases, true ) ) {
			return $templates;
		}
	}

	$canonical = get_block_template( get_stylesheet() . '//' . musicwave_account_template_slug(), 'wp_template' );
	if ( $canonical ) {
		// Re-slug the clone to the queried alias so the hierarchy sorter
		// keeps it at the alias position instead of dropping it behind the
		// generic page/index fallbacks.
		$alias        = clone $canonical;
		$alias->slug  = $aliases[0];
		$alias->id    = get_stylesheet() . '//' . $aliases[0];
		$alias->title = $canonical->title . ' (alias)';
		$templates[]  = $alias;
	}

	return $templates;
}
add_filter( 'get_block_templates', 'musicwave_alias_account_templates_in_query', 10, 3 );

/**
 * Human titles for bundled templates whose slugs read like internals.
 *
 * Only file-based (source: theme) templates are relabeled; a template the
 * administrator already customized in the Site Editor keeps its own title.
 *
 * @return array<string, string>
 */
function musicwave_template_titles(): array {
	return array(
		'page-playlists' => __( 'Public playlists', 'musicwave' ),
	);
}

/**
 * Apply the human title map to one resolved template object.
 *
 * @param mixed $template Resolved block template, or null.
 * @return mixed
 */
function musicwave_apply_template_title( $template ) {
	if ( ! is_object( $template ) || 'theme' !== ( isset( $template->source ) ? (string) $template->source : '' ) ) {
		return $template;
	}

	$titles = musicwave_template_titles();
	$slug   = is_object( $template ) && isset( $template->slug ) ? (string) $template->slug : '';
	if ( '' !== $slug && isset( $titles[ $slug ] ) ) {
		$template->title = $titles[ $slug ];
	}

	return $template;
}

/**
 * Label bundled templates in single-template lookups (Site Editor routes).
 *
 * @param mixed  $template      Resolved template, or null.
 * @param string $id            Requested theme//slug identifier.
 * @param string $template_type Template type.
 * @return mixed
 */
function musicwave_filter_block_template_title( $template, string $id, string $template_type ) {
	unset( $id, $template_type );

	return musicwave_apply_template_title( $template );
}
add_filter( 'get_block_template', 'musicwave_filter_block_template_title', 20, 3 );

/**
 * Label bundled templates in template lists (Site Editor navigation).
 *
 * @param array<int, mixed>    $templates    Found templates.
 * @param array<string, mixed> $query        Query arguments.
 * @param string               $template_type Template type.
 * @return array<int, mixed>
 */
function musicwave_filter_block_templates_titles( array $templates, array $query, string $template_type ): array {
	unset( $query );
	if ( 'wp_template' !== $template_type ) {
		return $templates;
	}

	return array_map( 'musicwave_apply_template_title', $templates );
}
add_filter( 'get_block_templates', 'musicwave_filter_block_templates_titles', 20, 3 );

/**
 * Find customized MusicWave template records that override theme files.
 *
 * @return array<int, WP_Post>
 */
function musicwave_customized_template_overrides(): array {
	$repairable = musicwave_repairable_template_slugs();
	$posts      = get_posts(
		array(
			'post_type'      => array_keys( $repairable ),
			'post_status'    => array( 'publish', 'draft' ),
			'posts_per_page' => -1,
			'no_found_rows'  => true,
			'tax_query'      => array(
				array(
					'taxonomy' => 'wp_theme',
					'field'    => 'name',
					'terms'    => get_stylesheet(),
				),
			),
		)
	);

	if ( ! is_array( $posts ) ) {
		return array();
	}

	return array_values(
		array_filter(
			$posts,
			static function ( $post ) use ( $repairable ): bool {
				return $post instanceof WP_Post
					&& isset( $repairable[ $post->post_type ] )
					&& in_array( $post->post_name, $repairable[ $post->post_type ], true );
			}
		)
	);
}

/**
 * Render the opt-in template recovery screen.
 *
 * @return void
 */
function musicwave_render_template_repair_page(): void {
	if ( ! current_user_can( 'edit_theme_options' ) ) {
		return;
	}

	$overrides = musicwave_customized_template_overrides();
	// Read-only redirect feedback counter, cast to int with absint().
	$repaired = isset( $_GET['musicwave-repaired'] ) && is_scalar( $_GET['musicwave-repaired'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		? absint( wp_unslash( (string) $_GET['musicwave-repaired'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		: 0;
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'MusicWave template repair', 'musicwave' ); ?></h1>
		<?php if ( $repaired > 0 ) : ?>
			<div class="notice notice-success is-dismissible"><p>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d: number of restored template overrides. */
						__( '%d customized MusicWave template records were moved to the Trash. The validated theme files are active again.', 'musicwave' ),
						$repaired
					)
				);
				?>
			</p></div>
		<?php endif; ?>
		<p><?php esc_html_e( 'Site Editor customizations are stored in the database and override updated theme files. Use this tool only when a MusicWave template reports an unavailable template part or invalid block content.', 'musicwave' ); ?></p>
		<p><strong><?php esc_html_e( 'This does not delete releases, pages, menus, media, plugin settings, or global styles.', 'musicwave' ); ?></strong></p>
		<p>
			<?php
			printf(
				/* translators: %d: number of customized template overrides. */
				esc_html__( 'Customized built-in template overrides found: %d', 'musicwave' ),
				count( $overrides )
			);
			?>
		</p>
		<?php if ( ! empty( $overrides ) ) : ?>
			<ul>
				<?php foreach ( $overrides as $override ) : ?>
					<li><code><?php echo esc_html( $override->post_type . ': ' . $override->post_name ); ?></code></li>
				<?php endforeach; ?>
			</ul>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="musicwave_repair_templates">
				<?php wp_nonce_field( 'musicwave_repair_templates' ); ?>
				<?php submit_button( __( 'Restore validated theme templates', 'musicwave' ), 'primary' ); ?>
			</form>
		<?php else : ?>
			<p><?php esc_html_e( 'No database overrides were found. WordPress is already using the bundled template files.', 'musicwave' ); ?></p>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Move customized built-in template overrides to Trash after confirmation.
 *
 * @return void
 */
function musicwave_handle_template_repair(): void {
	if ( ! current_user_can( 'edit_theme_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to repair theme templates.', 'musicwave' ) );
	}

	check_admin_referer( 'musicwave_repair_templates' );
	$repaired = 0;
	foreach ( musicwave_customized_template_overrides() as $override ) {
		if ( wp_trash_post( $override->ID ) ) {
			++$repaired;
		}
	}

	wp_safe_redirect(
		add_query_arg(
			array(
				'page'               => 'musicwave-template-repair',
				'musicwave-repaired' => $repaired,
			),
			admin_url( 'themes.php' )
		)
	);
	exit;
}
add_action( 'admin_post_musicwave_repair_templates', 'musicwave_handle_template_repair' );

/**
 * Return default template copy indexed by stable presentation keys.
 *
 * @return array<string, string>
 */
function musicwave_template_texts(): array {
	return array(
		'latest_releases'   => __( 'Latest releases', 'musicwave' ),
		'no_music_found'    => __( 'No music was found.', 'musicwave' ),
		'not_found_title'   => __( 'Nothing playing here.', 'musicwave' ),
		'not_found_message' => __( 'The page may have moved. Try searching the catalog.', 'musicwave' ),
		'cart_title'        => __( 'Your cart', 'musicwave' ),
		'checkout_title'    => __( 'Checkout', 'musicwave' ),
		'account_title'     => __( 'My account', 'musicwave' ),
		'powered_by'        => __( 'Powered by MusicWave.', 'musicwave' ),
	);
}

/**
 * Render a known translated default template string.
 *
 * @param array<string, mixed> $attributes Block attributes.
 * @return string
 */
function musicwave_render_theme_text( array $attributes ): string {
	$key   = isset( $attributes['key'] ) ? sanitize_key( (string) $attributes['key'] ) : '';
	$texts = musicwave_template_texts();
	if ( ! isset( $texts[ $key ] ) ) {
		return '';
	}

	$tag_name = isset( $attributes['tagName'] ) ? strtolower( (string) $attributes['tagName'] ) : 'p';
	$tags     = array( 'p', 'span', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' );
	if ( ! in_array( $tag_name, $tags, true ) ) {
		$tag_name = 'p';
	}

	$class_name = isset( $attributes['className'] ) ? (string) $attributes['className'] : '';
	$class_name = trim( (string) preg_replace( '/[^A-Za-z0-9_\-\s]/', '', $class_name ) );
	$class      = '' === $class_name ? '' : ' class="' . esc_attr( $class_name ) . '"';

	return '<' . $tag_name . $class . '>' . esc_html( $texts[ $key ] ) . '</' . $tag_name . '>';
}

/**
 * Resolve a slider toggle against the global MusicWave setting.
 *
 * @param array<string, mixed> $attributes Block attributes.
 */
function musicwave_slider_toggle( array $attributes, string $attribute, string $setting ): bool {
	$value = isset( $attributes[ $attribute ] ) && is_scalar( $attributes[ $attribute ] ) ? sanitize_key( (string) $attributes[ $attribute ] ) : 'inherit';
	if ( 'enabled' === $value || 'disabled' === $value ) {
		return 'enabled' === $value;
	}

	if ( class_exists( '\ManaCore\MusicWave\Core\Support\Settings' ) ) {
		return 'enabled' === \ManaCore\MusicWave\Core\Support\Settings::get( $setting );
	}

	return true;
}

/**
 * Return one global slider number when MusicWave Core is available.
 */
function musicwave_slider_number( string $setting, int $fallback ): int {
	if ( class_exists( '\ManaCore\MusicWave\Core\Support\Settings' ) ) {
		return absint( \ManaCore\MusicWave\Core\Support\Settings::get( $setting ) );
	}

	return $fallback;
}

/**
 * Render a responsive and accessible release slider.
 *
 * @param array<string, mixed> $attributes Block attributes.
 */
function musicwave_render_release_slider( array $attributes ): string {
	if ( ! post_type_exists( 'mw_release' ) || ! musicwave_slider_toggle( $attributes, 'enabled', 'slider_enabled' ) ) {
		return '';
	}

	if ( function_exists( 'wp_enqueue_script' ) ) {
		wp_enqueue_script( 'musicwave-slider' );
	}

	$items = isset( $attributes['itemsToShow'] ) ? absint( $attributes['itemsToShow'] ) : 0;
	$items = $items >= 3 && $items <= 12 ? $items : musicwave_slider_number( 'slider_items', 6 );
	$items = min( 12, max( 3, $items ) );
	$ids   = get_posts( musicwave_release_query_args( $attributes, $items ) );
	if ( ! is_array( $ids ) || empty( $ids ) ) {
		return '';
	}

	$cards = array();
	foreach ( $ids as $release_id ) {
		$release_id = absint( $release_id );
		$link       = get_permalink( $release_id );
		if ( $release_id < 1 || ! is_string( $link ) || '' === $link ) {
			continue;
		}

		$title   = get_the_title( $release_id );
		$image   = get_the_post_thumbnail(
			$release_id,
			'medium_large',
			array(
				'class' => 'mw-release-slider__image',
				'alt'   => '',
			)
		);
		$artists = wp_get_post_terms( $release_id, 'mw_artist', array( 'fields' => 'names' ) );
		$artist  = is_array( $artists ) && ! empty( $artists ) ? implode( ', ', $artists ) : __( 'MusicWave release', 'musicwave' );
		$excerpt = '';
		if ( ! empty( $attributes['showExcerpt'] ) ) {
			$release_excerpt = get_the_excerpt( $release_id );
			$excerpt         = '' !== $release_excerpt ? '<p>' . esc_html( wp_trim_words( $release_excerpt, 16 ) ) . '</p>' : '';
		}
		$artist_markup = ( ! isset( $attributes['showArtist'] ) || false !== $attributes['showArtist'] ) ? '<span class="mw-release-slider__artist">' . esc_html( $artist ) . '</span>' : '';
		$date_markup   = ! empty( $attributes['showDate'] ) ? '<span class="mw-release-slider__date">' . esc_html( get_the_date( '', $release_id ) ) . '</span>' : '';
		$views_markup  = '';
		if ( ! empty( $attributes['showViews'] ) ) {
			$views        = absint( get_post_meta( $release_id, 'mw_views', true ) );
			$views_markup = $views > 0 ? '<span class="mw-release-slider__views">' . esc_html( number_format_i18n( $views ) ) . ' ' . esc_html__( 'views', 'musicwave' ) . '</span>' : '';
		}

		$play_button = apply_filters( 'music_wave_card_play_button', '', $release_id, 'mw-release-slider__play' );
		$play_button = is_string( $play_button ) && '' !== $play_button ? $play_button : '<span class="mw-release-slider__play" aria-hidden="true">&#9654;</span>';

		$cards[] = '<article class="mw-release-slider__slide" role="group"><div class="mw-release-slider__artwrap"><a class="mw-release-slider__art" href="' . esc_url( $link ) . '">' . $image . '</a>' . $play_button . '</div><div class="mw-release-slider__body"><h3><a href="' . esc_url( $link ) . '">' . esc_html( $title ) . '</a></h3>' . $artist_markup . $date_markup . $views_markup . $excerpt . '</div></article>';
	}
	if ( empty( $cards ) ) {
		return '';
	}

	$autoplay      = musicwave_slider_toggle( $attributes, 'autoplay', 'slider_autoplay' );
	$loop          = musicwave_slider_toggle( $attributes, 'loop', 'slider_loop' );
	$pause         = musicwave_slider_toggle( $attributes, 'pauseOnHover', 'slider_pause_on_hover' );
	$arrows        = musicwave_slider_toggle( $attributes, 'showArrows', 'slider_show_arrows' );
	$dots          = musicwave_slider_toggle( $attributes, 'showDots', 'slider_show_dots' );
	$interval      = isset( $attributes['interval'] ) ? absint( $attributes['interval'] ) : 0;
	$interval      = $interval >= 2000 && $interval <= 20000 ? $interval : musicwave_slider_number( 'slider_interval', 5000 );
	$interval      = min( 20000, max( 2000, $interval ) );
	$eyebrow       = isset( $attributes['eyebrow'] ) ? sanitize_text_field( (string) $attributes['eyebrow'] ) : '';
	$title         = isset( $attributes['title'] ) ? sanitize_text_field( (string) $attributes['title'] ) : '';
	$eyebrow       = '' !== $eyebrow ? $eyebrow : __( 'Made for you', 'musicwave' );
	$title         = '' !== $title ? $title : __( 'Featured releases', 'musicwave' );
	$id            = wp_unique_id( 'mw-release-slider-' );
	$controls      = $arrows ? '<div class="mw-release-slider__arrows"><button type="button" data-mw-slider-previous aria-controls="' . esc_attr( $id ) . '" aria-label="' . esc_attr__( 'Previous releases', 'musicwave' ) . '">&#8592;</button><button type="button" data-mw-slider-next aria-controls="' . esc_attr( $id ) . '" aria-label="' . esc_attr__( 'Next releases', 'musicwave' ) . '">&#8594;</button></div>' : '';
	$dot_container = $dots ? '<div class="mw-release-slider__dots" data-mw-slider-dots aria-label="' . esc_attr__( 'Slider pagination', 'musicwave' ) . '"></div>' : '';

	return '<section ' . get_block_wrapper_attributes( array( 'class' => 'mw-release-slider' ) ) . ' data-mw-slider data-autoplay="' . esc_attr( $autoplay ? '1' : '0' ) . '" data-loop="' . esc_attr( $loop ? '1' : '0' ) . '" data-pause-hover="' . esc_attr( $pause ? '1' : '0' ) . '" data-interval="' . esc_attr( (string) $interval ) . '"><header class="mw-release-slider__header"><div><span class="mw-release-slider__eyebrow">' . esc_html( $eyebrow ) . '</span><h2>' . esc_html( $title ) . '</h2></div>' . $controls . '</header><div id="' . esc_attr( $id ) . '" class="mw-release-slider__viewport" data-mw-slider-viewport tabindex="0"><div class="mw-release-slider__track">' . implode( '', $cards ) . '</div></div>' . $dot_container . '<p class="screen-reader-text" aria-live="polite" data-mw-slider-status></p></section>';
}

/**
 * Build release query arguments from the shared query and filtering attributes.
 *
 * The release slider and release shelf blocks accept the same ordering,
 * taxonomy, release type, and curated ID attributes, so one builder keeps
 * their query behavior identical and easy to maintain.
 *
 * @param array<string, mixed> $attributes Block attributes.
 * @param int                  $items      Number of releases to fetch.
 * @return array<string, mixed>
 */
function musicwave_release_query_args( array $attributes, int $items ): array {
	$order_by = isset( $attributes['orderBy'] ) ? sanitize_key( (string) $attributes['orderBy'] ) : 'date';
	$order_by = in_array( $order_by, array( 'date', 'title', 'rand', 'modified', 'views' ), true ) ? $order_by : 'date';
	$order    = isset( $attributes['order'] ) && 'ASC' === strtoupper( (string) $attributes['order'] ) ? 'ASC' : 'DESC';
	$query    = array(
		'post_type'              => 'mw_release',
		'post_status'            => 'publish',
		'posts_per_page'         => $items,
		'fields'                 => 'ids',
		'orderby'                => 'views' === $order_by ? 'meta_value_num' : $order_by,
		'order'                  => $order,
		'no_found_rows'          => true,
		'update_post_meta_cache' => false,
		'update_post_term_cache' => true,
	);

	if ( 'views' === $order_by ) {
		$query['meta_key'] = 'mw_views'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
	}

	$release_ids = isset( $attributes['releaseIds'] ) ? (string) $attributes['releaseIds'] : '';
	$release_ids = preg_split( '/[\s,]+/', $release_ids );
	if ( ! is_array( $release_ids ) ) {
		$release_ids = array();
	}
	$release_ids = array_values(
		array_unique(
			array_filter(
				array_map( 'absint', $release_ids )
			)
		)
	);
	$release_ids = array_slice( $release_ids, 0, 24 );
	if ( ! empty( $release_ids ) ) {
		$query['post__in'] = $release_ids;
		$query['orderby']  = 'post__in';
	}

	$taxonomy     = isset( $attributes['taxonomy'] ) ? sanitize_key( (string) $attributes['taxonomy'] ) : '';
	$term         = isset( $attributes['termSlug'] ) ? sanitize_title( (string) $attributes['termSlug'] ) : '';
	$content_type = isset( $attributes['contentType'] ) ? sanitize_key( (string) $attributes['contentType'] ) : '';
	$tax_query    = array();

	if ( in_array( $taxonomy, array( 'mw_artist', 'mw_genre', 'mw_mood', 'mw_release_type', 'mw_label' ), true ) && '' !== $term ) {
		$tax_query[] = array(
			'taxonomy' => $taxonomy,
			'field'    => 'slug',
			'terms'    => array( $term ),
		);
	}

	if ( '' !== $content_type && taxonomy_exists( 'mw_release_type' ) ) {
		$tax_query[] = array(
			'taxonomy' => 'mw_release_type',
			'field'    => 'slug',
			'terms'    => array( $content_type ),
		);
	}

	if ( count( $tax_query ) > 1 ) {
		$tax_query['relation'] = 'AND';
	}

	if ( ! empty( $tax_query ) ) {
		$query['tax_query'] = $tax_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
	}

	return $query;
}

/**
 * Render a community playlist shelf: same grid/scroll/list chrome as the
 * release shelf, but sourced from public playlists. Reuses
 * PlaylistRepository::public_playlists() so only `public` playlists ever
 * appear; viewer filtering still hides unpublished release art.
 *
 * @param array<string, mixed> $attributes Block attributes.
 */
function musicwave_render_playlist_shelf( array $attributes ): string {
	if ( ! class_exists( '\ManaCore\MusicWave\Core\Playlists\PlaylistRepository' ) ) {
		return '';
	}

	$items   = isset( $attributes['itemsToShow'] ) ? absint( $attributes['itemsToShow'] ) : 8;
	$items   = min( 24, max( 1, $items ) );
	$columns = isset( $attributes['columns'] ) ? absint( $attributes['columns'] ) : 4;
	$columns = min( 6, max( 2, $columns ) );
	$layout  = isset( $attributes['layout'] ) ? sanitize_key( (string) $attributes['layout'] ) : 'grid';
	$layout  = in_array( $layout, array( 'grid', 'scroll', 'list' ), true ) ? $layout : 'grid';
	$shape   = isset( $attributes['imageShape'] ) ? sanitize_key( (string) $attributes['imageShape'] ) : 'square';
	$shape   = in_array( $shape, array( 'square', 'landscape', 'portrait', 'circle' ), true ) ? $shape : 'square';

	$orderby = isset( $attributes['playlistOrderBy'] ) ? sanitize_key( (string) $attributes['playlistOrderBy'] ) : '';
	if ( '' === $orderby ) {
		$orderby = isset( $attributes['orderBy'] ) ? sanitize_key( (string) $attributes['orderBy'] ) : 'updated_at';
	}
	$orderby = in_array( $orderby, array( 'updated_at', 'created_at', 'title', 'date' ), true ) ? $orderby : 'updated_at';
	if ( 'date' === $orderby ) {
		$orderby = 'updated_at';
	}
	$search = isset( $attributes['playlistSearch'] ) ? sanitize_text_field( (string) $attributes['playlistSearch'] ) : '';
	$search = trim( $search );
	if ( '' === $search && isset( $_GET['s'] ) && is_scalar( $_GET['s'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		// Allow catalog ?s= reuse for playlist shelves when placed on search archive.
		$search = sanitize_text_field( wp_unslash( (string) $_GET['s'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	$repo      = new \ManaCore\MusicWave\Core\Playlists\PlaylistRepository();
	$viewer_id = get_current_user_id();
	$playlists = $repo->public_playlists( $items, 0, $search, $orderby, $viewer_id );
	if ( array() === $playlists ) {
		return '';
	}

	$eyebrow            = isset( $attributes['eyebrow'] ) ? sanitize_text_field( (string) $attributes['eyebrow'] ) : '';
	$title              = isset( $attributes['title'] ) ? sanitize_text_field( (string) $attributes['title'] ) : '';
	$description        = isset( $attributes['description'] ) ? sanitize_text_field( (string) $attributes['description'] ) : '';
	$section_url        = isset( $attributes['sectionUrl'] ) ? esc_url( (string) $attributes['sectionUrl'] ) : '';
	$section_link_label = isset( $attributes['sectionLinkLabel'] ) ? sanitize_text_field( (string) $attributes['sectionLinkLabel'] ) : '';
	$section_link_label = '' !== $section_link_label ? $section_link_label : __( 'See all playlists', 'musicwave' );
	if ( '' === $title && 'playlists' === ( isset( $attributes['source'] ) ? sanitize_key( (string) $attributes['source'] ) : '' ) ) {
		$title = __( 'Community playlists', 'musicwave' );
	}
	if ( '' === $eyebrow ) {
		$eyebrow = __( 'Curated by listeners', 'musicwave' );
	}

	// Default to the dedicated playlists page if no URL given.
	if ( '' === $section_url ) {
		$page = get_page_by_path( 'playlists' );
		if ( $page instanceof WP_Post ) {
			$perm = get_permalink( $page );
			if ( is_string( $perm ) && '' !== $perm ) {
				$section_url = $perm;
			}
		}
		if ( '' === $section_url ) {
			$section_url = home_url( '/playlists/' );
		}
	}

	$cards = array();
	foreach ( $playlists as $p_idx => $playlist ) {
		$pid    = (int) $playlist['id'];
		$ptitle = (string) $playlist['title'];
		$author = isset( $playlist['author_name'] ) ? (string) $playlist['author_name'] : '';
		$count  = isset( $playlist['count'] ) ? (int) $playlist['count'] : 0;
		$link   = add_query_arg( array( 'mw-playlist' => (string) $pid ), $section_url );

		// Build 2x2 art grid from viewer's visible items (published only).
		$art_grid   = '';
		$items_view = $repo->items_for_viewer( $pid, $viewer_id );
		$ids        = array_slice(
			array_map(
				static function ( $i ): int {
					return isset( $i['release_id'] ) ? absint( $i['release_id'] ) : 0;
				},
				$items_view
			),
			0,
			4
		);
		if ( array() === $ids ) {
			$art_grid = '<span class="mw-release-shelf__placeholder" aria-hidden="true">♫</span>';
		} else {
			$cells = '';
			foreach ( $ids as $cell_idx => $rid ) {
				$is_first = 0 === $p_idx && 0 === $cell_idx;
				$thumb    = get_the_post_thumbnail(
					$rid,
					'medium',
					array(
						'class'         => 'mw-release-shelf__image',
						'alt'           => '',
						'loading'       => $is_first ? 'eager' : 'lazy',
						'fetchpriority' => $is_first ? 'high' : 'low',
						'decoding'      => 'async',
					)
				);
				if ( '' !== $thumb ) {
					$cells .= '<span class="mw-release-shelf__art-cell">' . $thumb . '</span>';
				} else {
					$init   = mb_substr( $ptitle, 0, 1 );
					$cells .= '<span class="mw-release-shelf__art-cell mw-release-shelf__art-cell--fallback" aria-hidden="true">' . esc_html( $init ) . '</span>';
				}
			}
			// Pad to 4.
			for ( $p = count( $ids ); $p < 4; $p++ ) {
				$cells .= '<span class="mw-release-shelf__art-cell mw-release-shelf__art-cell--empty" aria-hidden="true"></span>';
			}
			$art_grid = '<span class="mw-release-shelf__playlist-grid">' . $cells . '</span>';
		}

		// Play button uses the same global queue as releases: data-mw-playlist-play.
		$play = '<button type="button" class="mw-release-shelf__play mw-card-play mw-release-shelf__play--playlist" data-mw-playlist-play data-playlist-id="' . esc_attr( (string) $pid ) . '" aria-label="' . esc_attr( sprintf( /* translators: %s: playlist title. */ __( 'Play all tracks in %s', 'musicwave' ), $ptitle ) ) . '"' . ( 0 === $count ? ' disabled' : '' ) . '><span aria-hidden="true">▶</span></button>';

		/* translators: %s: playlist title. */
		$thumb_wrap = '<div class="mw-release-shelf__artwrap"><a class="mw-release-shelf__art mw-release-shelf__art--' . esc_attr( $shape ) . ' mw-release-shelf__art--playlist" href="' . esc_url( $link ) . '" aria-label="' . esc_attr( sprintf( __( 'Open %s', 'musicwave' ), $ptitle ) ) . '">' . $art_grid . '</a>' . $play . '</div>';
		$artist_m   = '' !== $author ? '<span class="mw-release-shelf__artist">' . esc_html( $author ) . '</span>' : '';
		/* translators: %d: number of tracks in playlist. */
		$count_m = '<span class="mw-release-shelf__count">' . esc_html( sprintf( _n( '%d track', '%d tracks', $count, 'musicwave' ), $count ) ) . '</span>';
		$cards[] = '<article class="mw-release-shelf__item mw-release-shelf__item--playlist">' . $thumb_wrap . '<div class="mw-release-shelf__body"><h3><a href="' . esc_url( $link ) . '">' . esc_html( $ptitle ) . '</a></h3>' . $artist_m . $count_m . '</div></article>';
	}

	$header = '';
	if ( '' !== $eyebrow || '' !== $title || '' !== $description || '' !== $section_url ) {
		$header = '<header class="mw-release-shelf__header"><div>' . ( '' !== $eyebrow ? '<span>' . esc_html( $eyebrow ) . '</span>' : '' ) . ( '' !== $title ? '<h2>' . esc_html( $title ) . '</h2>' : '' ) . ( '' !== $description ? '<p>' . esc_html( $description ) . '</p>' : '' ) . '</div>' . ( '' !== $section_url ? '<a class="mw-release-shelf__more" href="' . esc_url( $section_url ) . '">' . esc_html( $section_link_label ) . '<span aria-hidden="true">&rarr;</span></a>' : '' ) . '</header>';
	}

	return '<section ' . get_block_wrapper_attributes( array( 'class' => 'mw-release-shelf mw-release-shelf--' . $layout . ' mw-release-shelf--playlists mw-release-shelf--columns-' . $columns ) ) . '>' . $header . '<div class="mw-release-shelf__items">' . implode( '', $cards ) . '</div></section>';
}

/**
 * Render a configurable release shelf.
 *
 * @param array<string, mixed> $attributes Block attributes.
 */
function musicwave_render_release_shelf( array $attributes ): string {
	$source = isset( $attributes['source'] ) ? sanitize_key( (string) $attributes['source'] ) : 'releases';
	if ( 'playlists' === $source || 'public_playlists' === $source ) {
		return musicwave_render_playlist_shelf( $attributes );
	}
	if ( ! post_type_exists( 'mw_release' ) ) {
		return '';
	}

	$items = isset( $attributes['itemsToShow'] ) ? absint( $attributes['itemsToShow'] ) : 8;
	$items = min( 24, max( 1, $items ) );
	$ids   = get_posts( musicwave_release_query_args( $attributes, $items ) );
	if ( ! is_array( $ids ) || empty( $ids ) ) {
		return '';
	}

	$layout       = isset( $attributes['layout'] ) ? sanitize_key( (string) $attributes['layout'] ) : 'grid';
	$layout       = in_array( $layout, array( 'grid', 'scroll', 'list', 'feature' ), true ) ? $layout : 'grid';
	$shape        = isset( $attributes['imageShape'] ) ? sanitize_key( (string) $attributes['imageShape'] ) : 'square';
	$shape        = in_array( $shape, array( 'square', 'landscape', 'portrait', 'circle' ), true ) ? $shape : 'square';
	$columns      = isset( $attributes['columns'] ) ? absint( $attributes['columns'] ) : 4;
	$columns      = min( 6, max( 2, $columns ) );
	$action_label = isset( $attributes['actionLabel'] ) ? sanitize_text_field( (string) $attributes['actionLabel'] ) : '';
	$action_label = '' !== $action_label ? $action_label : __( 'Open release', 'musicwave' );
	$show_artwork = ! isset( $attributes['showArtwork'] ) || false !== $attributes['showArtwork'];
	$show_play    = ! isset( $attributes['showPlayButton'] ) || false !== $attributes['showPlayButton'];
	$cards        = array();

	foreach ( $ids as $idx => $release_id ) {
		$release_id = absint( $release_id );
		$link       = get_permalink( $release_id );
		if ( $release_id < 1 || ! is_string( $link ) || '' === $link ) {
			continue;
		}

		$title     = get_the_title( $release_id );
		$artists   = wp_get_post_terms( $release_id, 'mw_artist', array( 'fields' => 'names' ) );
		$artist    = is_array( $artists ) && ! empty( $artists ) ? implode( ', ', $artists ) : '';
		$is_first  = $idx < 2;
		$thumbnail = get_the_post_thumbnail(
			$release_id,
			'medium_large',
			array(
				'class'         => 'mw-release-shelf__image',
				'alt'           => '',
				'loading'       => $is_first ? 'eager' : 'lazy',
				'fetchpriority' => $is_first ? 'high' : 'low',
				'decoding'      => 'async',
			)
		);
		$initial   = function_exists( 'mb_substr' ) ? mb_substr( $title, 0, 1 ) : substr( $title, 0, 1 );
		/* translators: %s: music release title. */
		$open_label  = sprintf( __( 'Open %s', 'musicwave' ), $title );
		$play_button = '';
		if ( $show_play ) {
			$play_button = apply_filters( 'music_wave_card_play_button', '', $release_id, 'mw-release-shelf__play' );
			$play_button = is_string( $play_button ) && '' !== $play_button ? $play_button : '<span class="mw-release-shelf__play" aria-hidden="true">&#9654;</span>';
		}
		$art            = $show_artwork
			? '<div class="mw-release-shelf__artwrap"><a class="mw-release-shelf__art mw-release-shelf__art--' . esc_attr( $shape ) . '" href="' . esc_url( $link ) . '" aria-label="' . esc_attr( $open_label ) . '">' . ( '' !== $thumbnail ? $thumbnail : '<span class="mw-release-shelf__placeholder" aria-hidden="true">' . esc_html( $initial ) . '</span>' ) . '</a>' . $play_button . '</div>'
			: '';
		$artist_markup  = ( ! isset( $attributes['showArtist'] ) || false !== $attributes['showArtist'] ) && '' !== $artist ? '<span class="mw-release-shelf__artist">' . esc_html( $artist ) . '</span>' : '';
		$date_markup    = ! empty( $attributes['showDate'] ) ? '<time datetime="' . esc_attr( get_the_date( 'c', $release_id ) ) . '">' . esc_html( get_the_date( '', $release_id ) ) . '</time>' : '';
		$excerpt_markup = '';
		if ( ! empty( $attributes['showExcerpt'] ) ) {
			$excerpt        = get_the_excerpt( $release_id );
			$excerpt_markup = '' !== $excerpt ? '<p>' . esc_html( wp_trim_words( $excerpt, 18 ) ) . '</p>' : '';
		}
		$action  = ! isset( $attributes['showAction'] ) || false !== $attributes['showAction'] ? '<a class="mw-release-shelf__action" href="' . esc_url( $link ) . '">' . esc_html( $action_label ) . '</a>' : '';
		$cards[] = '<article class="mw-release-shelf__item">' . $art . '<div class="mw-release-shelf__body"><h3><a href="' . esc_url( $link ) . '">' . esc_html( $title ) . '</a></h3>' . $artist_markup . $date_markup . $excerpt_markup . $action . '</div></article>';
	}
	if ( empty( $cards ) ) {
		return '';
	}

	$eyebrow            = isset( $attributes['eyebrow'] ) ? sanitize_text_field( (string) $attributes['eyebrow'] ) : '';
	$title              = isset( $attributes['title'] ) ? sanitize_text_field( (string) $attributes['title'] ) : '';
	$description        = isset( $attributes['description'] ) ? sanitize_text_field( (string) $attributes['description'] ) : '';
	$section_url        = isset( $attributes['sectionUrl'] ) ? esc_url( (string) $attributes['sectionUrl'] ) : '';
	$section_link_label = isset( $attributes['sectionLinkLabel'] ) ? sanitize_text_field( (string) $attributes['sectionLinkLabel'] ) : '';
	$section_link_label = '' !== $section_link_label ? $section_link_label : __( 'See all', 'musicwave' );
	if ( 'feature' === $layout ) {
		return musicwave_render_feature_shelf( $attributes, $ids, $eyebrow, $title, $description, $section_url, $section_link_label );
	}

	$header = '';
	if ( '' !== $eyebrow || '' !== $title || '' !== $description || '' !== $section_url ) {
		$header = '<header class="mw-release-shelf__header"><div>' . ( '' !== $eyebrow ? '<span>' . esc_html( $eyebrow ) . '</span>' : '' ) . ( '' !== $title ? '<h2>' . esc_html( $title ) . '</h2>' : '' ) . ( '' !== $description ? '<p>' . esc_html( $description ) . '</p>' : '' ) . '</div>' . ( '' !== $section_url ? '<a class="mw-release-shelf__more" href="' . esc_url( $section_url ) . '">' . esc_html( $section_link_label ) . '<span aria-hidden="true">&rarr;</span></a>' : '' ) . '</header>';
	}

	return '<section ' . get_block_wrapper_attributes( array( 'class' => 'mw-release-shelf mw-release-shelf--' . $layout . ' mw-release-shelf--columns-' . $columns ) ) . '>' . $header . '<div class="mw-release-shelf__items">' . implode( '', $cards ) . '</div></section>';
}

/**
 * Render the editorial "feature" layout: a large featured release cover
 * with a gradient overlay alongside a compact list of supporting releases.
 *
 * @param array<string, mixed> $attributes    Block attributes.
 * @param array<int, int>      $ids           Queried release IDs.
 * @param string               $eyebrow       Eyebrow text.
 * @param string               $title         Section title.
 * @param string               $description   Section description.
 * @param string               $section_url   Optional "See all" URL.
 * @param string               $section_link_label Label for the "See all" link.
 */
function musicwave_render_feature_shelf( array $attributes, array $ids, string $eyebrow, string $title, string $description, string $section_url, string $section_link_label ): string {
	$featured_id = isset( $attributes['featuredReleaseId'] ) ? absint( $attributes['featuredReleaseId'] ) : 0;
	if ( $featured_id < 1 || ! in_array( $featured_id, $ids, true ) ) {
		$featured_id = absint( $ids[0] );
	}
	$side_ids = array_values( array_diff( $ids, array( $featured_id ) ) );
	$side_ids = array_slice( $side_ids, 0, 4 );

	$featured_link   = get_permalink( $featured_id );
	$featured_title  = get_the_title( $featured_id );
	$featured_art    = get_the_post_thumbnail(
		$featured_id,
		'large',
		array(
			'class' => 'mw-feature__art-img',
			'alt'   => '',
		)
	);
	$artists         = wp_get_post_terms( $featured_id, 'mw_artist', array( 'fields' => 'names' ) );
	$featured_artist = is_array( $artists ) && ! empty( $artists ) ? implode( ', ', $artists ) : '';

	$featured_source = isset( $attributes['featuredSource'] ) ? sanitize_key( (string) $attributes['featuredSource'] ) : 'excerpt';
	$featured_body   = '';
	if ( 'excerpt' === $featured_source ) {
		$excerpt = get_the_excerpt( $featured_id );
		if ( '' !== $excerpt ) {
			$featured_body = '<p class="mw-feature__excerpt">' . esc_html( wp_trim_words( $excerpt, 30 ) ) . '</p>';
		}
	} elseif ( 'custom' === $featured_source && '' !== $description ) {
		$featured_body = '<p class="mw-feature__excerpt">' . esc_html( $description ) . '</p>';
	}

	$overlay = isset( $attributes['overlay'] ) ? absint( $attributes['overlay'] ) : 50;
	$overlay = min( 100, max( 0, $overlay ) );
	$overlay = $overlay / 100;

	// CTA button options.
	$cta_label   = isset( $attributes['ctaLabel'] ) && '' !== (string) $attributes['ctaLabel']
		? sanitize_text_field( (string) $attributes['ctaLabel'] )
		: __( 'Open release', 'musicwave' );
	$cta_variant = isset( $attributes['ctaStyle'] ) ? sanitize_key( (string) $attributes['ctaStyle'] ) : 'solid';
	$cta_variant = in_array( $cta_variant, array( 'solid', 'outline', 'ghost' ), true ) ? $cta_variant : 'solid';
	$cta_bg_raw  = sanitize_hex_color( (string) ( $attributes['ctaBgColor'] ?? '' ) );
	$cta_bg      = null !== $cta_bg_raw ? $cta_bg_raw : '';
	$cta_txt_raw = sanitize_hex_color( (string) ( $attributes['ctaTextColor'] ?? '' ) );
	$cta_txt     = null !== $cta_txt_raw ? $cta_txt_raw : '';
	$cta_radius  = isset( $attributes['ctaRadius'] ) ? max( 0, min( 999, absint( $attributes['ctaRadius'] ) ) ) : 999;
	$cta_style   = ( $cta_bg ? '--mw-cta-bg:' . esc_attr( $cta_bg ) . ';' : '' )
		. ( $cta_txt ? '--mw-cta-color:' . esc_attr( $cta_txt ) . ';' : '' )
		. 'border-radius:' . esc_attr( (string) $cta_radius ) . 'px;';

	// Hero typography.
	$hero_title_color_raw = sanitize_hex_color( (string) ( $attributes['heroTitleColor'] ?? '' ) );
	$hero_title_color     = null !== $hero_title_color_raw ? $hero_title_color_raw : '';
	$hero_title_size      = isset( $attributes['heroTitleSize'] ) ? absint( $attributes['heroTitleSize'] ) : 0;
	$hero_text_color_raw  = sanitize_hex_color( (string) ( $attributes['heroTextColor'] ?? '' ) );
	$hero_text_color      = null !== $hero_text_color_raw ? $hero_text_color_raw : '';
	$title_css            = ( $hero_title_color ? 'color:' . esc_attr( $hero_title_color ) . ';' : '' )
		. ( $hero_title_size > 0 ? 'font-size:' . esc_attr( (string) $hero_title_size ) . 'px;' : '' );
	$text_css             = $hero_text_color ? ' style="color:' . esc_attr( $hero_text_color ) . '"' : '';

	// Views badge for featured hero.
	$featured_views = '';
	if ( ! empty( $attributes['showViews'] ) ) {
		$views = absint( get_post_meta( $featured_id, 'mw_views', true ) );
		if ( $views > 0 ) {
			$featured_views = '<span class="mw-feature__views">' . esc_html( number_format_i18n( $views ) ) . ' ' . esc_html__( 'views', 'musicwave' ) . '</span>';
		}
	}

	$side_col_width = isset( $attributes['sideColumnWidth'] ) ? absint( $attributes['sideColumnWidth'] ) : 340;
	$side_col_width = min( 560, max( 200, $side_col_width ) );
	$aside_style    = 'style="flex-basis:' . esc_attr( (string) $side_col_width ) . 'px"';

	$hero = '<div class="mw-feature__hero">';
	if ( '' !== $featured_art ) {
		$hero .= '<div class="mw-feature__art">' . $featured_art . '</div>';
	}
	$hero .= '<div class="mw-feature__scrim" style="opacity:' . esc_attr( (string) $overlay ) . '" aria-hidden="true"></div>';
	$hero .= '<div class="mw-feature__content"' . $text_css . '>';
	if ( '' !== $eyebrow ) {
		$hero .= '<span class="mw-feature__eyebrow">' . esc_html( $eyebrow ) . '</span>';
	}
	$hero .= '<h2 class="mw-feature__title"' . ( $title_css ? ' style="' . esc_attr( $title_css ) . '"' : '' ) . '><a href="' . esc_url( (string) $featured_link ) . '">' . esc_html( $featured_title ) . '</a></h2>';
	if ( '' !== $featured_artist ) {
		$hero .= '<span class="mw-feature__artist">' . esc_html( $featured_artist ) . '</span>';
	}
	$hero .= $featured_body;
	$hero .= $featured_views;
	$hero .= '<a class="mw-feature__cta mw-feature__cta--' . esc_attr( $cta_variant ) . '" href="' . esc_url( (string) $featured_link ) . '" style="' . esc_attr( $cta_style ) . '">' . esc_html( $cta_label ) . '<span aria-hidden="true">&rarr;</span></a>';
	$hero .= '</div></div>';

	$list = '';
	if ( ! empty( $side_ids ) ) {
		$items = array();
		foreach ( $side_ids as $rank => $rid ) {
			$rid   = absint( $rid );
			$rlink = get_permalink( $rid );
			if ( $rid < 1 || ! is_string( $rlink ) ) {
				continue;
			}
			$rank_markup  = ! empty( $attributes['showRank'] ) ? '<span class="mw-feature__rank">' . ( $rank + 2 ) . '</span>' : '';
			$views_markup = '';
			if ( ! empty( $attributes['showViews'] ) ) {
				$sv = absint( get_post_meta( $rid, 'mw_views', true ) );
				if ( $sv > 0 ) {
					$views_markup = '<span class="mw-feature__side-views">' . esc_html( number_format_i18n( $sv ) ) . '</span>';
				}
			}
			$rthumb  = get_the_post_thumbnail(
				$rid,
				'thumbnail',
				array(
					'class' => 'mw-feature__side-img',
					'alt'   => '',
				)
			);
			$items[] = '<li class="mw-feature__side-item">' . $rank_markup . ( '' !== $rthumb ? '<a href="' . esc_url( $rlink ) . '" class="mw-feature__side-art">' . $rthumb . '</a>' : '' ) . '<div class="mw-feature__side-body"><a href="' . esc_url( $rlink ) . '" class="mw-feature__side-title">' . esc_html( get_the_title( $rid ) ) . '</a>' . $views_markup . '</div></li>';
		}
		if ( ! empty( $items ) ) {
			$list_title = '' !== $title ? $title : __( 'More releases', 'musicwave' );
			$list       = '<aside class="mw-feature__aside" ' . $aside_style . '><h3 class="mw-feature__aside-title">' . esc_html( $list_title ) . '</h3><ul class="mw-feature__side-list">' . implode( '', $items ) . '</ul>';
			if ( '' !== $section_url ) {
				$list .= '<a class="mw-feature__more" href="' . esc_url( $section_url ) . '">' . esc_html( $section_link_label ) . '<span aria-hidden="true">&rarr;</span></a>';
			}
			$list .= '</aside>';
		}
	}

	$style_attr = 'style="--mw-feature-overlay:' . esc_attr( (string) $overlay ) . '"';

	return '<section ' . get_block_wrapper_attributes( array( 'class' => 'mw-release-shelf mw-release-shelf--feature' ) ) . ' ' . $style_attr . '><div class="mw-feature">' . $hero . $list . '</div></section>';
}

/**
 * Track front-end views for mw_release posts.
 *
 * Increments the `mw_views` post meta each time a published release is
 * viewed in the front end (skipping admin users and preview requests).
 *
 * @return void
 */
function musicwave_track_release_view(): void {
	if ( is_admin() || is_preview() || ! is_singular( 'mw_release' ) ) {
		return;
	}
	if ( current_user_can( 'edit_theme_options' ) ) {
		return;
	}

	$post_id = (int) get_the_ID();
	if ( $post_id < 1 ) {
		return;
	}

	$views = absint( get_post_meta( $post_id, 'mw_views', true ) );
	update_post_meta( $post_id, 'mw_views', $views + 1 );
}
add_action( 'template_redirect', 'musicwave_track_release_view' );

/**
 * Render the preference toggle with a translated JavaScript-free label.
 *
 * @return string
 */
function musicwave_render_theme_toggle(): string {
	$label = __( 'Use system theme', 'musicwave' );

	return '<button class="mw-theme-toggle" type="button" aria-label="' . esc_attr( $label ) . '" title="' . esc_attr( $label ) . '">◐</button>';
}
