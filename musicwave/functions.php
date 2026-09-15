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
		'musicwave-rail'            => array(
			'file'         => 'assets/css/components/rail.css',
			'dependencies' => array( 'musicwave-navigation' ),
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
		'musicwave-comments'        => array(
			'file'         => 'assets/css/components/comments.css',
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
		'musicwave-hero-slider'     => array(
			'file'         => 'assets/css/components/hero-slider.css',
			'dependencies' => array( 'musicwave-shelf' ),
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

		/*
		 * Editorial × Vinyl layer. Both files are additive: they never redefine
		 * a SonicStream component, they only add the editorial vocabulary
		 * (kickers, section heads, chip rails, spec strips, liner notes) and
		 * the physical-media vocabulary (sleeve treatment, record displays,
		 * vinyl tracklist). They load after the components they decorate so a
		 * merchant editing global styles sees the final cascade in the Site
		 * Editor too.
		 */
		'musicwave-editorial'       => array(
			'file'         => 'assets/css/components/editorial.css',
			'dependencies' => array( 'musicwave-catalog', 'musicwave-shelf' ),
		),
		'musicwave-vinyl'           => array(
			'file'         => 'assets/css/components/vinyl.css',
			'dependencies' => array( 'musicwave-collections', 'musicwave-editorial', 'musicwave-shelf' ),
		),
		'musicwave-lyrics'          => array(
			'file'         => 'assets/css/components/lyrics.css',
			'dependencies' => array( 'musicwave-utilities' ),
		),
		'musicwave-release-page'    => array(
			'file'         => 'assets/css/components/release-page.css',
			'dependencies' => array( 'musicwave-catalog', 'musicwave-lyrics', 'musicwave-vinyl' ),
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

	/*
	 * SonicStream display pairing: Syne carries headlines and release titles,
	 * Plus Jakarta Sans carries body text. Both are variable-weight families
	 * so one request per family covers every weight used by the theme.
	 */
	wp_enqueue_style(
		'musicwave-fonts',
		'https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Syne:wght@600;700;800&display=swap',
		array(),
		$version
	);

	wp_enqueue_style(
		'musicwave',
		get_stylesheet_uri(),
		array( 'musicwave-fonts' ),
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
		wp_set_script_translations( 'musicwave-theme-preference', 'musicwave', get_template_directory() . '/languages' );
	}
	// Stream rail toggle (parts/header-stream.html). Tiny, dependency-free,
	// and a no-op on pages without the rail, so it ships with every route
	// rather than being tied to a block render.
	wp_enqueue_script(
		'musicwave-rail',
		get_template_directory_uri() . '/assets/rail.js',
		array(),
		$version,
		true
	);
	wp_localize_script(
		'musicwave-rail',
		'musicwaveRail',
		array(
			'labels' => array(
				'collapse' => __( 'جمع‌کردن منو', 'musicwave' ),
				'expand'   => __( 'بازکردن منو', 'musicwave' ),
			),
		)
	);
	// Registered only: the slider script enqueues at render time of the
	// music-wave/release-slider block, so routes without a slider ship no
	// slider bytes (PROJECT_PLAN.md Stage 4 deliverable 4).
	wp_register_script(
		'musicwave-slider',
		get_template_directory_uri() . '/assets/slider.js',
		array(),
		$version,
		true
	);
	if ( function_exists( 'wp_set_script_translations' ) ) {
		wp_set_script_translations( 'musicwave-slider', 'musicwave', get_template_directory() . '/languages' );
	}
	wp_register_script(
		'musicwave-release-hero',
		get_template_directory_uri() . '/assets/release-hero.js',
		array(),
		$version,
		true
	);
	if ( function_exists( 'wp_set_script_translations' ) ) {
		wp_set_script_translations( 'musicwave-release-hero', 'musicwave', get_template_directory() . '/languages' );
	}
	wp_register_script(
		'musicwave-lyrics',
		get_template_directory_uri() . '/assets/lyrics.js',
		array(),
		$version,
		true
	);
	if ( function_exists( 'wp_set_script_translations' ) ) {
		wp_set_script_translations( 'musicwave-lyrics', 'musicwave', get_template_directory() . '/languages' );
	}
	wp_localize_script(
		'musicwave-slider',
		'musicwaveSlider',
		array(
			/* translators: %d: slide group index. */
			'goToGroup' => __( 'رفتن به گروه اسلاید %d', 'musicwave' ),
			/* translators: 1: current slide group index, 2: total slide groups. */
			'status'    => __( 'گروه اسلاید %1$d از %2$d', 'musicwave' ),
		)
	);
	// Registered only: the hero slider script enqueues at render time of the
	// release shelf "slider" layout, so routes without a hero ship no bytes.
	wp_register_script(
		'musicwave-hero-slider',
		get_template_directory_uri() . '/assets/hero-slider.js',
		array(),
		$version,
		true
	);
	if ( function_exists( 'wp_set_script_translations' ) ) {
		wp_set_script_translations( 'musicwave-hero-slider', 'musicwave', get_template_directory() . '/languages' );
	}
	wp_localize_script(
		'musicwave-hero-slider',
		'musicwaveHeroSlider',
		array(
			'previous'  => __( 'اسلاید قبلی', 'musicwave' ),
			'next'      => __( 'اسلاید بعدی', 'musicwave' ),
			/* translators: %d: slide index. */
			'goToSlide' => __( 'رفتن به اسلاید %d', 'musicwave' ),
			/* translators: 1: current slide index, 2: total slides. */
			'status'    => __( 'اسلاید %1$d از %2$d', 'musicwave' ),
		)
	);
	wp_localize_script(
		'musicwave-theme-preference',
		'musicwaveThemePreference',
		array(
			'labels' => array(
				'system' => __( 'استفاده از پوسته سیستم', 'musicwave' ),
				'light'  => __( 'استفاده از پوسته روشن', 'musicwave' ),
				'dark'   => __( 'استفاده از پوسته تیره', 'musicwave' ),
			),
		)
	);
	if ( function_exists( 'is_singular' ) && is_singular( 'mw_release' ) ) {
		wp_enqueue_script( 'musicwave-release-hero' );
	}
}
add_action( 'wp_enqueue_scripts', 'musicwave_enqueue_assets' );

/**
 * Apply a saved display preference before the page paints.
 *
 * @return void
 */
function musicwave_preload_theme_preference(): void {
	echo "<script>(function(){try{var t=window.localStorage.getItem('musicwave-theme');if('light'===t||'dark'===t){document.documentElement.setAttribute('data-mw-theme',t);}var r=window.localStorage.getItem('musicwave-rail');if('collapsed'===r||'expanded'===r){document.documentElement.setAttribute('data-mw-rail',r);}}catch(e){}}());</script>\n";
}
add_action( 'wp_head', 'musicwave_preload_theme_preference', 0 );

/**
 * Resolve the native colour scheme of the active global styles.
 *
 * Style variations such as Cassette and Sunrise ship a light palette while the
 * default palette is dark. tokens.css needs to know which one is active so the
 * visitor's light/dark preference remaps presets only when it differs from the
 * palette the merchant is editing in the Site Editor.
 *
 * @return string Either "light" or "dark".
 */
function musicwave_native_color_scheme(): string {
	$canvas = '';
	if ( function_exists( 'wp_get_global_settings' ) ) {
		$settings = wp_get_global_settings( array( 'color', 'palette' ) );
		$palettes = is_array( $settings ) ? $settings : array();
		foreach ( array( 'custom', 'theme' ) as $origin ) {
			if ( empty( $palettes[ $origin ] ) || ! is_array( $palettes[ $origin ] ) ) {
				continue;
			}
			foreach ( $palettes[ $origin ] as $color ) {
				if ( isset( $color['slug'], $color['color'] ) && 'canvas' === $color['slug'] && is_string( $color['color'] ) ) {
					$canvas = $color['color'];
					break 2;
				}
			}
		}
	}

	$scheme = musicwave_is_light_color( $canvas ) ? 'light' : 'dark';

	/**
	 * Filter the native colour scheme reported to the stylesheet.
	 *
	 * @param string $scheme Either "light" or "dark".
	 * @param string $canvas Resolved canvas colour from the active palette.
	 */
	$filtered = apply_filters( 'musicwave_native_color_scheme', $scheme, $canvas );

	return 'light' === $filtered ? 'light' : 'dark';
}

/**
 * Decide whether a hex colour reads as light (WCAG relative luminance > 0.5).
 *
 * Non-hex values (gradients, CSS functions) are treated as dark because the
 * default palette is dark.
 *
 * @param string $hex Colour in #rgb, #rrggbb, or #rrggbbaa notation.
 * @return bool
 */
function musicwave_is_light_color( string $hex ): bool {
	$hex = ltrim( trim( $hex ), '#' );
	if ( 3 === strlen( $hex ) || 4 === strlen( $hex ) ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}
	// ctype is optional on some hosts; a pattern keeps the check dependency-free.
	if ( ! preg_match( '/^[0-9a-f]{6}/i', $hex ) ) {
		return false;
	}

	$channels = array();
	foreach ( array( 0, 2, 4 ) as $offset ) {
		$value      = hexdec( substr( $hex, $offset, 2 ) ) / 255;
		$channels[] = $value <= 0.03928 ? $value / 12.92 : pow( ( $value + 0.055 ) / 1.055, 2.4 );
	}

	$luminance = 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];

	return $luminance > 0.5;
}

/**
 * Expose the native colour scheme on <html> for tokens.css.
 *
 * @param string $output Language attributes markup.
 * @return string
 */
function musicwave_language_attributes( string $output ): string {
	return trim( $output . ' data-mw-scheme="' . esc_attr( musicwave_native_color_scheme() ) . '"' );
}
add_filter( 'language_attributes', 'musicwave_language_attributes' );

/**
 * Warm the connection to the font CDN before stylesheets resolve.
 *
 * @param array<int|string, mixed> $urls          Resource hint URLs.
 * @param string                   $relation_type Relation type.
 * @return array<int|string, mixed>
 */
function musicwave_resource_hints( array $urls, string $relation_type ): array {
	if ( 'preconnect' === $relation_type ) {
		$urls[] = array(
			'href'        => 'https://fonts.gstatic.com',
			'crossorigin' => 'anonymous',
		);
	}
	return $urls;
}
add_filter( 'wp_resource_hints', 'musicwave_resource_hints', 10, 2 );

/**
 * Defer non-critical scripts + preload critical tokens for LCP.
 *
 * @param string $tag    Script tag.
 * @param string $handle Script handle.
 * @return string
 */
function musicwave_filter_script_tag( string $tag, string $handle ): string {
	$defer = array( 'musicwave-theme-preference', 'musicwave-rail', 'musicwave-slider', 'music-wave-playlists', 'music-wave-preview-player' );
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
		'featured'          => __( 'منتخب', 'musicwave' ),
		'musicwave-hero'    => __( 'بخش‌های معرفی MusicWave', 'musicwave' ),
		'musicwave-shelves' => __( 'ویترین‌های MusicWave', 'musicwave' ),
		'musicwave-widgets' => __( 'ابزارک‌های MusicWave', 'musicwave' ),
		'musicwave-footers' => __( 'پابرگ‌های MusicWave', 'musicwave' ),
		'musicwave-headers' => __( 'سربرگ‌های MusicWave', 'musicwave' ),
		'musicwave-cards'   => __( 'کارت‌های MusicWave', 'musicwave' ),
		'musicwave-cta'     => __( 'فراخوان اقدام MusicWave', 'musicwave' ),
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
 * Register the shared inserter category when the companion plugin is absent.
 *
 * Theme presentation blocks use the same `music-wave` category as Core blocks.
 * The duplicate guard makes this safe when both Theme and Core add the
 * category during the same request, while keeping the Theme independently
 * usable for its presentation-only blocks.
 *
 * @param array<int, array<string, mixed>> $categories Existing categories.
 * @return array<int, array<string, mixed>>
 */
function musicwave_register_block_category( array $categories ): array {
	foreach ( $categories as $category ) {
		if ( is_array( $category ) && isset( $category['slug'] ) && 'music-wave' === $category['slug'] ) {
			return $categories;
		}
	}

	array_unshift(
		$categories,
		array(
			'slug'  => 'music-wave',
			'title' => __( 'MusicWave', 'musicwave' ),
			'icon'  => 'format-audio',
		)
	);

	return $categories;
}
add_filter( 'block_categories_all', 'musicwave_register_block_category' );

/**
 * Return the presentation style variations offered by the theme blocks.
 *
 * Single source of truth for the surfaces that must never drift:
 *
 *   1. the Site Editor "Styles" panel (`register_block_style`),
 *   2. the block inspector's "استایل و ظاهر" select, which writes the same
 *      `is-style-<slug>` class — the `styleVariant` attribute stays a
 *      pattern-level preset so a pattern can ship a look of its own,
 *   3. the renderer's modifier class (`mw-<component>--<variant>`), resolved
 *      from `array_keys()` of this registry.
 *
 * `tests/template-integrity.php` parses this registry and fails when a slug
 * ships without a stylesheet rule, a translatable label, or both editor
 * surfaces, so a new look can never reach an admin as a label without a style.
 *
 * @return array<string, array<string, array{label: string, hint: string}>>
 */
function musicwave_presentation_style_variations(): array {
	return array(
		'release-shelf'  => array(
			'editorial' => array(
				'label' => __( 'سرمقاله‌ای', 'musicwave' ),
				'hint'  => __( 'کارت مجله‌ای با خط تأکیدی، سطح شیشه‌ای و تیتر درشت.', 'musicwave' ),
			),
			'vinyl'     => array(
				'label' => __( 'وینیل', 'musicwave' ),
				'hint'  => __( 'غلاف آلبوم با صفحهٔ بیرون‌زننده که هنگام هاور از غلاف خارج می‌شود.', 'musicwave' ),
			),
			'bento'     => array(
				'label' => __( 'بنتو', 'musicwave' ),
				'hint'  => __( 'شبکهٔ موزاییکی؛ اولین انتشار به‌صورت کارت شاخص بزرگ نمایش داده می‌شود.', 'musicwave' ),
			),
			'minimal'   => array(
				'label' => __( 'حداقلی', 'musicwave' ),
				'hint'  => __( 'بدون سطح کارت؛ فقط خطوط و تایپوگرافی. مناسب چیدمان‌های فهرستی.', 'musicwave' ),
			),
		),
		'release-slider' => array(
			'cinema'    => array(
				'label' => __( 'سینمایی', 'musicwave' ),
				'hint'  => __( 'اسلاید تمام‌قاب با تیتر بزرگ و پردهٔ تیره‌تر.', 'musicwave' ),
			),
			'editorial' => array(
				'label' => __( 'سرمقاله‌ای', 'musicwave' ),
				'hint'  => __( 'ستون متن با خط تأکیدی کنار محتوا.', 'musicwave' ),
			),
		),
	);
}

/**
 * Resolve the active style variation for a theme presentation block.
 *
 * Precedence mirrors `ManaCore\MusicWave\Core\Blocks\BlockSupport::style_variation()`:
 * the Site Editor block style (`is-style-*`) wins over the inspector attribute,
 * which wins over the block default (an empty string means "no modifier").
 *
 * @param array<string, mixed> $attributes Block attributes.
 * @param array<int, string>   $allowed    Allowed variation slugs.
 */
function musicwave_style_variant( array $attributes, array $allowed ): string {
	$class_name = isset( $attributes['className'] ) && is_scalar( $attributes['className'] )
		? (string) $attributes['className']
		: '';
	if ( '' !== $class_name ) {
		foreach ( $allowed as $variation ) {
			if ( false !== strpos( $class_name, 'is-style-' . $variation ) ) {
				return $variation;
			}
		}
	}

	$selected = isset( $attributes['styleVariant'] ) && is_scalar( $attributes['styleVariant'] )
		? sanitize_key( (string) $attributes['styleVariant'] )
		: '';

	return in_array( $selected, $allowed, true ) ? $selected : '';
}

/**
 * Return the modifier class suffix for a component's allowed variations.
 *
 * @param array<string, mixed> $attributes Block attributes.
 * @param array<int, string>   $allowed    Allowed variation slugs.
 * @param string               $component  Root component class, e.g. mw-release-shelf.
 */
function musicwave_style_variant_class( array $attributes, array $allowed, string $component ): string {
	$variant = musicwave_style_variant( $attributes, $allowed );

	return '' !== $variant ? ' ' . $component . '--' . $variant : '';
}

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
			'label' => __( 'کپسولی (MusicWave)', 'musicwave' ),
		),
		array(
			'block' => 'core/button',
			'name'  => 'mw-outline-accent',
			'label' => __( 'خط تأکیدی خطی', 'musicwave' ),
		),
		array(
			'block' => 'core/group',
			'name'  => 'mw-surface',
			'label' => __( 'کارت سطحی', 'musicwave' ),
		),
		array(
			'block' => 'core/group',
			'name'  => 'mw-surface-raised',
			'label' => __( 'سطح برجسته', 'musicwave' ),
		),
		array(
			'block' => 'core/columns',
			'name'  => 'mw-tight-gap',
			'label' => __( 'فاصله کم', 'musicwave' ),
		),
		array(
			'block' => 'core/separator',
			'name'  => 'mw-accent',
			'label' => __( 'خط تأکیدی', 'musicwave' ),
		),
		array(
			'block' => 'core/image',
			'name'  => 'mw-rounded',
			'label' => __( 'گرد', 'musicwave' ),
		),
		array(
			'block' => 'core/image',
			'name'  => 'mw-circle',
			'label' => __( 'دایره', 'musicwave' ),
		),
		array(
			'block' => 'core/list',
			'name'  => 'mw-checklist',
			'label' => __( 'چک‌لیست', 'musicwave' ),
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

	// Presentation blocks: the canonical name plus the hidden legacy alias, so
	// a template saved before the namespace migration keeps its style picker
	// and both names render the same modifier class.
	if ( ! function_exists( 'register_block_style' ) ) {
		return;
	}

	// Static section head: the same editorial modifiers a PHP-rendered header
	// uses, exposed as block styles so the Styles panel can switch a stored
	// container between them without code.
	foreach ( musicwave_section_head_styles() as $style_name => $style_label ) {
		register_block_style(
			'music-wave/section-head',
			array(
				'name'  => $style_name,
				'label' => $style_label,
			)
		);
	}

	foreach ( musicwave_presentation_style_variations() as $slug => $variations ) {
		foreach ( array( 'music-wave/' . $slug, 'musicwave/' . $slug ) as $block_name ) {
			foreach ( $variations as $name => $variation ) {
				register_block_style(
					$block_name,
					array(
						'name'  => $name,
						'label' => $variation['label'],
					)
				);
			}
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
			'name'          => __( 'نوار کناری MusicWave', 'musicwave' ),
			'id'            => 'musicwave-sidebar',
			'description'   => __( 'در بخش قالب «نوار کناری» نمایش داده می‌شود (نمایش → ویرایشگر → بخش‌های قالب → نوار کناری). هر بلوک یا ابزارک قدیمی را اینجا اضافه کنید. همه‌چیز بدون نیاز به کدنویسی از رابط بصری قابل ویرایش است.', 'musicwave' ),
			'before_widget' => '<section id="%1$s" class="widget mw-widget %2$s">',
			'after_widget'  => '</section>',
			'before_title'  => '<h3 class="widget-title">',
			'after_title'   => '</h3>',
		),
		array(
			'name'          => __( 'ابزارک‌های پابرگ MusicWave', 'musicwave' ),
			'id'            => 'musicwave-footer-widgets',
			'description'   => __( 'در بخش قالب «ابزارک‌های پابرگ» نمایش داده می‌شود (نمایش → ویرایشگر → بخش‌های قالب → ابزارک‌های پابرگ). هر بلوک یا ابزارک قدیمی را بکشید و رها کنید؛ رنگ‌ها از سبک‌های کلی پیروی می‌کنند.', 'musicwave' ),
			'before_widget' => '<section id="%1$s" class="widget mw-widget %2$s">',
			'after_widget'  => '</section>',
			'before_title'  => '<h3 class="widget-title">',
			'after_title'   => '</h3>',
		),
		array(
			'name'          => __( 'نوار کناری فروشگاه MusicWave', 'musicwave' ),
			'id'            => 'musicwave-shop-sidebar',
			'description'   => __( 'در بخش قالب «نوار کناری فروشگاه» نمایش داده می‌شود (بخش‌های قالب → نوار کناری فروشگاه). از فیلترها و دسته‌بندی‌های ووکامرس یا هر بلوکی استفاده کنید.', 'musicwave' ),
			'before_widget' => '<section id="%1$s" class="widget mw-widget %2$s">',
			'after_widget'  => '</section>',
			'before_title'  => '<h3 class="widget-title">',
			'after_title'   => '</h3>',
		),
		array(
			'name'          => __( 'ابزارک‌های سربرگ MusicWave', 'musicwave' ),
			'id'            => 'musicwave-header-widgets',
			'description'   => __( 'اختیاری: ناحیه کوچک ابزارک برای سربرگ (مثلاً اعلان یا تغییر زبان). بلوک‌ها را از نمایش → ابزارک‌ها یا مستقیماً در بخش قالب سربرگ اضافه کنید.', 'musicwave' ),
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
 * @param string $block_dir Directory name inside the theme's blocks directory.
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
 * Build the browser registration entry for one bundled theme block.
 *
 * block.json is the single source of truth for the client registry: this reads
 * it once and returns the exact shape editor-blocks.js consumes, so the dynamic
 * (ServerSideRender) and static (InnerBlocks) lanes share one metadata path and
 * cannot drift from each other or from the PHP registration.
 *
 * @param string $block_dir Directory name inside the theme's blocks directory.
 * @return array<string, mixed>|null Null when the metadata file is unusable.
 */
function musicwave_block_metadata_entry( string $block_dir ): ?array {
	$meta = musicwave_load_block_json( $block_dir );
	if ( null === $meta || empty( $meta['name'] ) ) {
		return null;
	}

	return array(
		'name'        => (string) $meta['name'],
		'apiVersion'  => isset( $meta['apiVersion'] ) ? (int) $meta['apiVersion'] : 3,
		'title'       => isset( $meta['title'] ) ? (string) $meta['title'] : '',
		'description' => isset( $meta['description'] ) ? (string) $meta['description'] : '',
		'category'    => isset( $meta['category'] ) ? (string) $meta['category'] : 'music-wave',
		'icon'        => isset( $meta['icon'] ) ? (string) $meta['icon'] : 'format-audio',
		'keywords'    => isset( $meta['keywords'] ) && is_array( $meta['keywords'] ) ? array_values( $meta['keywords'] ) : array(),
		'textdomain'  => isset( $meta['textdomain'] ) ? (string) $meta['textdomain'] : 'musicwave',
		'attributes'  => isset( $meta['attributes'] ) && is_array( $meta['attributes'] ) ? $meta['attributes'] : array(),
		'supports'    => isset( $meta['supports'] ) && is_array( $meta['supports'] ) ? $meta['supports'] : array(),
		'example'     => isset( $meta['example'] ) && is_array( $meta['example'] ) ? $meta['example'] : array(),
		// Block style variations declared in block.json travel with the
		// client registration so the Styles panel never depends on the
		// REST hydration order.
		'styles'      => isset( $meta['styles'] ) && is_array( $meta['styles'] ) ? array_values( $meta['styles'] ) : array(),
	);
}

/**
 * Return the theme's static, child-bearing blocks.
 *
 * A block either renders itself in PHP (leaf block, ServerSideRender preview,
 * `render_callback`) or stores itself from JavaScript (`save()` + InnerBlocks) —
 * never both. Mixing the two is what produces duplicated chrome and stale
 * editor previews, so the two lanes are registered, localized and validated
 * separately. See docs/composability-architecture.md §5.
 *
 * Directories listed here are registered from block.json with no render
 * callback and get no legacy `musicwave/*` alias, because nothing was ever
 * saved under another name.
 *
 * @return array<int, string>
 */
function musicwave_static_block_dirs(): array {
	return array( 'section-head' );
}

/**
 * Register static container blocks from their bundled block.json metadata.
 *
 * With no `render_callback`, WordPress prints the markup the block's own
 * `save()` stored, so the frontend needs no PHP renderer and the Site Editor
 * needs no REST round-trip to preview it.
 *
 * @return void
 */
function musicwave_register_static_blocks(): void {
	if ( ! function_exists( 'register_block_type' ) ) {
		return;
	}

	foreach ( musicwave_static_block_dirs() as $block_dir ) {
		$path = get_template_directory() . '/blocks/' . $block_dir;
		// Missing bundled metadata must never fatal a request; the block simply
		// stays unavailable on an incomplete deployment.
		if ( ! is_readable( $path . '/block.json' ) ) {
			continue;
		}

		register_block_type( $path );
	}
}
add_action( 'init', 'musicwave_register_static_blocks' );

/**
 * Return the section-head looks offered by the Site Editor Styles panel.
 *
 * The slugs are the editorial modifiers editorial.css already ships
 * (`mw-section-head--center` / `--stack` / `--invert`), so a static container
 * and a PHP-rendered header speak one vocabulary. The Styles panel writes
 * `is-style-<slug>`; editorial.css maps both spellings onto one declaration set
 * rather than duplicating the rules.
 *
 * @return array<string, string> Slug => translated label.
 */
function musicwave_section_head_styles(): array {
	return array(
		'center' => __( 'وسط‌چین', 'musicwave' ),
		'stack'  => __( 'ستونی', 'musicwave' ),
		'invert' => __( 'معکوس (روی سطح رنگی)', 'musicwave' ),
	);
}

/**
 * Register a hidden legacy alias for a theme-owned block.
 *
 * Theme-owned blocks originally used the `musicwave/*` namespace. The
 * canonical namespace is now `music-wave/*`, but old Site Editor content and
 * post content must remain renderable. The alias is deliberately hidden from
 * the inserter so new content uses one consistent namespace.
 *
 * @param string               $dir      Block directory name.
 * @param string               $callback Server-side render callback.
 * @param array<string, mixed> $metadata Decoded block.json metadata.
 * @return void
 */
function musicwave_register_legacy_presentation_block( string $dir, string $callback, array $metadata ): void {
	$legacy_name = 'musicwave/' . $dir;
	$canonical   = isset( $metadata['name'] ) ? (string) $metadata['name'] : '';
	if ( '' === $canonical || $legacy_name === $canonical || ! function_exists( 'register_block_type' ) ) {
		return;
	}

	$textdomain                   = isset( $metadata['textdomain'] ) ? (string) $metadata['textdomain'] : 'musicwave';
	$keywords                     = isset( $metadata['keywords'] ) && is_array( $metadata['keywords'] ) ? array_values( $metadata['keywords'] ) : array();
	$keywords                     = array_map(
		static function ( $keyword ) use ( $textdomain ): string {
			return translate( (string) $keyword, $textdomain ); // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText,WordPress.WP.I18n.NonSingularStringLiteralDomain,WordPress.WP.I18n.LowLevelTranslationFunction
		},
		$keywords
	);
	$args                         = array(
		'api_version'     => isset( $metadata['apiVersion'] ) ? (int) $metadata['apiVersion'] : 3,
		'title'           => isset( $metadata['title'] ) ? translate( (string) $metadata['title'], $textdomain ) : $legacy_name, // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText,WordPress.WP.I18n.NonSingularStringLiteralDomain,WordPress.WP.I18n.LowLevelTranslationFunction
		'category'        => isset( $metadata['category'] ) ? (string) $metadata['category'] : 'music-wave',
		'description'     => isset( $metadata['description'] ) ? translate( (string) $metadata['description'], $textdomain ) : '', // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText,WordPress.WP.I18n.NonSingularStringLiteralDomain,WordPress.WP.I18n.LowLevelTranslationFunction
		'icon'            => isset( $metadata['icon'] ) ? (string) $metadata['icon'] : 'format-audio',
		'keywords'        => $keywords,
		'textdomain'      => $textdomain,
		'attributes'      => isset( $metadata['attributes'] ) && is_array( $metadata['attributes'] ) ? $metadata['attributes'] : array(),
		'supports'        => isset( $metadata['supports'] ) && is_array( $metadata['supports'] ) ? $metadata['supports'] : array(),
		'render_callback' => $callback,
	);
	$args['supports']['inserter'] = false;

	register_block_type( $legacy_name, $args );
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
		'synced-lyrics'  => array(
			'dir'      => 'synced-lyrics',
			'callback' => 'musicwave_render_synced_lyrics',
		),
	);

	foreach ( $blocks as $config ) {
		$dir      = (string) $config['dir'];
		$callback = (string) $config['callback'];
		$path     = get_template_directory() . '/blocks/' . $dir;
		$metadata = musicwave_load_block_json( $dir );
		if ( is_readable( $path . '/block.json' ) && is_array( $metadata ) ) {
			// block.json is authoritative for the canonical music-wave/* name.
			register_block_type( $path, array( 'render_callback' => $callback ) );
			musicwave_register_legacy_presentation_block( $dir, $callback, $metadata );
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
		wp_set_script_translations( 'musicwave-presentation-blocks', 'musicwave', get_template_directory() . '/languages' );
	}

	$dirs      = array( 'theme-text', 'theme-toggle', 'release-slider', 'release-shelf', 'synced-lyrics' );
	$localized = array();
	foreach ( $dirs as $dir ) {
		// block.json is the single source of truth for the browser registry.
		// Do not maintain a second title/description/attribute map here: that
		// was the source of Site Editor drift in earlier versions.
		$entry = musicwave_block_metadata_entry( $dir );
		if ( null === $entry ) {
			continue;
		}
		$localized[] = $entry;

		// Keep legacy theme blocks editable after the namespace migration.
		$legacy_entry                         = $entry;
		$legacy_entry['name']                 = 'musicwave/' . $dir;
		$legacy_entry['supports']             = isset( $legacy_entry['supports'] ) && is_array( $legacy_entry['supports'] ) ? $legacy_entry['supports'] : array();
		$legacy_entry['supports']['inserter'] = false;
		$localized[]                          = $legacy_entry;
	}

	/*
	 * Static child-bearing blocks travel in their own list: they register a real
	 * edit/save pair (InnerBlocks) instead of a ServerSideRender preview, and
	 * they get no legacy alias. Keeping the lanes separate means editor-blocks.js
	 * never has to branch on a block name to decide how to render it.
	 */
	$static_localized = array();
	foreach ( musicwave_static_block_dirs() as $static_dir ) {
		$static_entry = musicwave_block_metadata_entry( $static_dir );
		if ( null !== $static_entry ) {
			$static_localized[] = $static_entry;
		}
	}

	wp_localize_script(
		'musicwave-presentation-blocks',
		'musicwavePresentationBlocks',
		$localized
	);
	wp_localize_script(
		'musicwave-presentation-blocks',
		'musicwaveStaticBlocks',
		$static_localized
	);
	// Appearance selects read the same map the Styles panel is built from.
	wp_localize_script(
		'musicwave-presentation-blocks',
		'musicwavePresentationVariations',
		musicwave_presentation_style_variations()
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
		__( 'تعمیر قالب MusicWave', 'musicwave' ),
		__( 'تعمیر قالب MusicWave', 'musicwave' ),
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
			'page-requests',
			'page-request-song',
			'page-request-collab',
			'page-stream',
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
		'wp_template_part' => array( 'header', 'header-centered', 'header-minimal', 'header-stream', 'header-stream-end', 'footer', 'footer-widgets', 'footer-simple', 'sidebar', 'sidebar-shop', 'hero' ),
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
		'page-playlists'      => __( 'فهرست‌های پخش عمومی', 'musicwave' ),
		'page-requests'       => __( 'درخواست آهنگ و همکاری', 'musicwave' ),
		'page-request-song'   => __( 'سفارش آهنگ اختصاصی', 'musicwave' ),
		'page-request-collab' => __( 'همکاری', 'musicwave' ),
		'page-stream'         => __( 'صفحهٔ استریم (نوار کناری)', 'musicwave' ),
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
 * برچسب bundled templates in single-template lookups (Site Editor routes).
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
 * برچسب bundled templates in template lists (Site Editor navigation).
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
		<h1><?php esc_html_e( 'تعمیر قالب MusicWave', 'musicwave' ); ?></h1>
		<?php if ( $repaired > 0 ) : ?>
			<div class="notice notice-success is-dismissible"><p>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d: number of restored template overrides. */
						__( '%d مورد سفارشی‌سازی قالب MusicWave به زباله‌دان منتقل شد. فایل‌های معتبر قالب اکنون فعال هستند.', 'musicwave' ),
						$repaired
					)
				);
				?>
			</p></div>
		<?php endif; ?>
		<p><?php esc_html_e( 'سفارشی‌سازی‌های ویرایشگر سایت در پایگاه داده ذخیره می‌شوند و بر فایل‌های به‌روزشده قالب اولویت دارند. فقط زمانی از این ابزار استفاده کنید که قالب MusicWave بخش قالبی ناموجود یا محتوای بلوک نامعتبر گزارش کند.', 'musicwave' ); ?></p>
		<p><strong><?php esc_html_e( 'این کار انتشارها، صفحات، منوها، رسانه‌ها، تنظیمات افزونه یا سبک‌های کلی را حذف نمی‌کند.', 'musicwave' ); ?></strong></p>
		<p>
			<?php
			printf(
				/* translators: %d: number of customized template overrides. */
				esc_html__( 'تعداد سفارشی‌سازی‌های ذخیره‌شده قالب: %d', 'musicwave' ),
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
				<?php submit_button( __( 'بازیابی فایل‌های معتبر قالب', 'musicwave' ), 'primary' ); ?>
			</form>
		<?php else : ?>
			<p><?php esc_html_e( 'هیچ سفارشی‌سازی ذخیره‌شده‌ای پیدا نشد. وردپرس در حال حاضر از فایل‌های قالب بسته استفاده می‌کند.', 'musicwave' ); ?></p>
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
		wp_die( esc_html__( 'شما اجازه تعمیر قالب‌ها را ندارید.', 'musicwave' ) );
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
		'latest_releases'   => __( 'آخرین انتشارها', 'musicwave' ),
		'no_music_found'    => __( 'موسیقی‌ای پیدا نشد.', 'musicwave' ),
		'not_found_title'   => __( 'اینجا چیزی در حال پخش نیست.', 'musicwave' ),
		'not_found_message' => __( 'ممکن است صفحه جابه‌جا شده باشد. کاتالوگ را جست‌وجو کنید.', 'musicwave' ),
		'cart_title'        => __( 'سبد خرید شما', 'musicwave' ),
		'checkout_title'    => __( 'تسویه‌حساب', 'musicwave' ),
		'account_title'     => __( 'حساب کاربری من', 'musicwave' ),
		'powered_by'        => __( 'قدرت‌گرفته از MusicWave.', 'musicwave' ),
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
 * Return the presentation data shared by release cards and slider slides.
 *
 * Querying title, permalink, artist terms, and the fallback initial in one
 * place keeps the theme's shelf and slider renderers aligned. The actual
 * markup remains layout-specific, so the shared helper does not couple a
 * grid card to the slider or to Core's domain blocks.
 *
 * @param int $release_id Release post ID.
 * @return array<string, mixed>
 */
function musicwave_release_presentation_data( int $release_id ): array {
	// The card skeleton is shared with the plugin so every shelf, related
	// section and history rail renders identical markup. Release surfaces are
	// unreachable without MusicWave Core anyway: it owns the `mw_release` post
	// type, and every caller treats an empty array as "skip this card".
	if ( class_exists( '\ManaCore\MusicWave\Core\Blocks\ReleaseCard' ) ) {
		return \ManaCore\MusicWave\Core\Blocks\ReleaseCard::presentation_data( $release_id );
	}

	return array();
}

/**
 * Render one release card with the shared MusicWave card markup.
 *
 * Thin wrapper around `ReleaseCard::render()` so the theme names the plugin
 * class in exactly one place, and degrades to an empty string instead of a
 * fatal should MusicWave Core ever be missing. Callers already treat an empty
 * card as "skip".
 *
 * @param array<string, mixed> $item    Presentation data from musicwave_release_presentation_data().
 * @param array<string, mixed> $options Card options; see ReleaseCard::render().
 * @return string
 */
function musicwave_render_release_card( array $item, array $options ): string {
	if ( ! class_exists( '\ManaCore\MusicWave\Core\Blocks\ReleaseCard' ) ) {
		return '';
	}

	return \ManaCore\MusicWave\Core\Blocks\ReleaseCard::render( $item, $options );
}

/**
 * Render a shared shelf section header.
 *
 * @param string $eyebrow             Small label above the heading.
 * @param string $title               Section heading.
 * @param string $description         Optional supporting copy.
 * @param string $section_url         Optional section URL.
 * @param string $section_link_label  Link label.
 * @return string
 */
function musicwave_render_shelf_header( string $eyebrow, string $title, string $description = '', string $section_url = '', string $section_link_label = '' ): string {
	if ( '' === $eyebrow && '' === $title && '' === $description && '' === $section_url ) {
		return '';
	}

	$section_link_label = '' !== $section_link_label ? $section_link_label : __( 'مشاهده همه', 'musicwave' );

	if ( class_exists( '\ManaCore\MusicWave\Core\Blocks\SectionHeader' ) ) {
		return \ManaCore\MusicWave\Core\Blocks\SectionHeader::shelf_header( $eyebrow, $title, $description, $section_url, $section_link_label );
	}

	$link = '' !== $section_url
		? '<a class="mw-release-shelf__more" href="' . esc_url( $section_url ) . '">' . esc_html( $section_link_label ) . '<span aria-hidden="true">&rarr;</span></a>'
		: '';

	return '<header class="mw-release-shelf__header"><div>'
		. ( '' !== $eyebrow ? '<span>' . esc_html( $eyebrow ) . '</span>' : '' )
		. ( '' !== $title ? '<h2>' . esc_html( $title ) . '</h2>' : '' )
		. ( '' !== $description ? '<p>' . esc_html( $description ) . '</p>' : '' )
		. '</div>' . $link . '</header>';
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

	$instance   = wp_unique_id( 'mw-slider-' );
	$tabbed     = musicwave_apply_filter_tab( $attributes, $instance );
	$attributes = $tabbed['attributes'];

	$items = isset( $attributes['itemsToShow'] ) ? absint( $attributes['itemsToShow'] ) : 0;
	$items = $items >= 3 && $items <= 12 ? $items : musicwave_slider_number( 'slider_items', 6 );
	$items = min( 12, max( 3, $items ) );
	$ids   = get_posts( musicwave_release_query_args( $attributes, $items ) );
	if ( ! is_array( $ids ) || empty( $ids ) ) {
		return '';
	}

	$cards = array();
	foreach ( $ids as $release_id ) {
		$item = musicwave_release_presentation_data( absint( $release_id ) );
		if ( empty( $item ) ) {
			continue;
		}

		$release_id = (int) $item['id'];
		$link       = (string) $item['link'];
		$title      = (string) $item['title'];
		$artist     = '' !== (string) $item['artist'] ? (string) $item['artist'] : __( 'انتشار MusicWave', 'musicwave' );
		$shape      = isset( $attributes['imageShape'] ) ? sanitize_key( (string) $attributes['imageShape'] ) : 'square';
		$shape      = in_array( $shape, array( 'square', 'landscape', 'portrait', 'circle' ), true ) ? $shape : 'square';
		$size_key   = isset( $attributes['imageSize'] ) ? sanitize_key( (string) $attributes['imageSize'] ) : 'medium';
		$size_key   = in_array( $size_key, array( 'small', 'medium', 'large' ), true ) ? $size_key : 'medium';
		$thumb_size = 'large' === $size_key ? 'large' : ( 'small' === $size_key ? 'medium' : 'medium_large' );
		$image      = get_the_post_thumbnail(
			$release_id,
			$thumb_size,
			array(
				'class' => 'mw-release-slider__image',
				'alt'   => '',
			)
		);
		$initial    = (string) $item['initial'];
		$image      = '' !== $image
			? $image
			: '<span class="mw-release-slider__placeholder" aria-hidden="true">' . esc_html( $initial ) . '</span>';
		$excerpt    = '';
		if ( ! empty( $attributes['showExcerpt'] ) ) {
			$release_excerpt = get_the_excerpt( $release_id );
			$excerpt         = '' !== $release_excerpt ? '<p>' . esc_html( wp_trim_words( $release_excerpt, 16 ) ) . '</p>' : '';
		}
		$artist_markup = ( ! isset( $attributes['showArtist'] ) || false !== $attributes['showArtist'] ) ? '<span class="mw-release-slider__artist">' . esc_html( $artist ) . '</span>' : '';
		$date_markup   = ! empty( $attributes['showDate'] ) ? '<span class="mw-release-slider__date">' . esc_html( get_the_date( '', $release_id ) ) . '</span>' : '';
		$views_markup  = '';
		if ( ! empty( $attributes['showViews'] ) ) {
			$views        = absint( get_post_meta( $release_id, 'mw_views', true ) );
			$views_markup = $views > 0 ? '<span class="mw-release-slider__views">' . esc_html( number_format_i18n( $views ) ) . ' ' . esc_html__( 'بازدیدها', 'musicwave' ) . '</span>' : '';
		}

		$play_button = apply_filters( 'music_wave_card_play_button', '', $release_id, 'mw-release-slider__play' );
		$play_button = is_string( $play_button ) && '' !== $play_button ? $play_button : '<span class="mw-release-slider__play" aria-hidden="true">&#9654;</span>';

		/* translators: %s: music release title. */
		$slide_label = sprintf( __( 'باز کردن %s', 'musicwave' ), $title );
		$cards[]     = '<article class="mw-release-slider__slide" role="group" aria-label="' . esc_attr( $slide_label ) . '"><div class="mw-release-slider__artwrap"><a class="mw-release-slider__art mw-release-slider__art--' . esc_attr( $shape ) . '" href="' . esc_url( $link ) . '">' . $image . '</a>' . $play_button . '</div><div class="mw-release-slider__body"><h3><a href="' . esc_url( $link ) . '">' . esc_html( $title ) . '</a></h3>' . $artist_markup . $date_markup . $views_markup . $excerpt . '</div></article>';
	}
	if ( empty( $cards ) ) {
		return '';
	}

	$autoplay           = musicwave_slider_toggle( $attributes, 'autoplay', 'slider_autoplay' );
	$loop               = musicwave_slider_toggle( $attributes, 'loop', 'slider_loop' );
	$pause              = musicwave_slider_toggle( $attributes, 'pauseOnHover', 'slider_pause_on_hover' );
	$arrows             = musicwave_slider_toggle( $attributes, 'showArrows', 'slider_show_arrows' );
	$dots               = musicwave_slider_toggle( $attributes, 'showDots', 'slider_show_dots' );
	$interval           = isset( $attributes['interval'] ) ? absint( $attributes['interval'] ) : 0;
	$interval           = $interval >= 2000 && $interval <= 20000 ? $interval : musicwave_slider_number( 'slider_interval', 5000 );
	$interval           = min( 20000, max( 2000, $interval ) );
	$eyebrow            = isset( $attributes['eyebrow'] ) ? sanitize_text_field( (string) $attributes['eyebrow'] ) : '';
	$title              = isset( $attributes['title'] ) ? sanitize_text_field( (string) $attributes['title'] ) : '';
	$eyebrow            = '' !== $eyebrow ? $eyebrow : __( 'برای شما', 'musicwave' );
	$title              = '' !== $title ? $title : __( 'انتشارهای منتخب', 'musicwave' );
	$id                 = $instance;
	$controls           = $arrows ? '<div class="mw-release-slider__arrows"><button type="button" data-mw-slider-previous aria-controls="' . esc_attr( $id ) . '" aria-label="' . esc_attr__( 'انتشارهای قبلی', 'musicwave' ) . '">&#8592;</button><button type="button" data-mw-slider-next aria-controls="' . esc_attr( $id ) . '" aria-label="' . esc_attr__( 'انتشارهای بعدی', 'musicwave' ) . '">&#8594;</button></div>' : '';
	$dot_container      = $dots ? '<div class="mw-release-slider__dots" data-mw-slider-dots aria-label="' . esc_attr__( 'صفحه‌بندی اسلایدر', 'musicwave' ) . '"></div>' : '';
	$section_url        = isset( $attributes['sectionUrl'] ) ? esc_url( (string) $attributes['sectionUrl'] ) : '';
	$section_link_label = isset( $attributes['sectionLinkLabel'] ) ? sanitize_text_field( (string) $attributes['sectionLinkLabel'] ) : '';
	$section_link_label = '' !== $section_link_label ? $section_link_label : __( 'مشاهده همه', 'musicwave' );
	$more               = '' !== $section_url
		? '<a class="mw-release-slider__more" href="' . esc_url( $section_url ) . '">' . esc_html( $section_link_label ) . '<span aria-hidden="true">&rarr;</span></a>'
		: '';
	$tabs_markup        = musicwave_render_filter_tabs( $tabbed['tabs'], $tabbed['active'], $instance );
	$size_mod           = $size_key;

	$slider_variant = musicwave_style_variant_class(
		$attributes,
		array_keys( musicwave_presentation_style_variations()['release-slider'] ),
		'mw-release-slider'
	);

	return '<section ' . get_block_wrapper_attributes( array( 'class' => 'mw-release-slider mw-release-slider--size-' . $size_mod . $slider_variant ) ) . ' data-mw-slider data-autoplay="' . esc_attr( $autoplay ? '1' : '0' ) . '" data-loop="' . esc_attr( $loop ? '1' : '0' ) . '" data-pause-hover="' . esc_attr( $pause ? '1' : '0' ) . '" data-interval="' . esc_attr( (string) $interval ) . '"><header class="mw-release-slider__header"><div><span class="mw-release-slider__eyebrow">' . esc_html( $eyebrow ) . '</span><h2>' . esc_html( $title ) . '</h2></div><div class="mw-release-slider__tools">' . $tabs_markup . $more . $controls . '</div></header><div id="' . esc_attr( $id ) . '" class="mw-release-slider__viewport" data-mw-slider-viewport tabindex="0"><div class="mw-release-slider__track">' . implode( '', $cards ) . '</div></div>' . $dot_container . '<p class="screen-reader-text" aria-live="polite" data-mw-slider-status></p></section>';
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
 * Parse filter-tab lines from a block inspector field.
 *
 * Each line is `Label|orderBy:date` or `Label|taxonomy:mw_genre:slug`.
 *
 * @param string $raw Inspector value.
 * @return array<int, array{slug: string, label: string, orderBy: string, taxonomy: string, termSlug: string}>
 */
function musicwave_parse_filter_tabs( string $raw ): array {
	$tabs = array();
	foreach ( preg_split( '/\r\n|\r|\n/', $raw ) as $line ) {
		$line = trim( (string) $line );
		if ( '' === $line ) {
			continue;
		}
		$parts = array_map( 'trim', explode( '|', $line, 2 ) );
		$label = sanitize_text_field( $parts[0] );
		if ( '' === $label ) {
			continue;
		}
		$slug  = sanitize_title( $label );
		$order = '';
		$tax   = '';
		$term  = '';
		$spec  = isset( $parts[1] ) ? $parts[1] : '';
		if ( '' !== $spec ) {
			$bits = array_map( 'trim', explode( ':', $spec ) );
			$key  = isset( $bits[0] ) ? sanitize_key( $bits[0] ) : '';
			if ( 'orderby' === $key && isset( $bits[1] ) ) {
				$candidate = sanitize_key( $bits[1] );
				$order     = in_array( $candidate, array( 'date', 'title', 'rand', 'modified', 'views' ), true ) ? $candidate : '';
			} elseif ( 'taxonomy' === $key && isset( $bits[1], $bits[2] ) ) {
				$candidate = sanitize_key( $bits[1] );
				$tax       = in_array( $candidate, array( 'mw_artist', 'mw_genre', 'mw_mood', 'mw_release_type', 'mw_label' ), true ) ? $candidate : '';
				$term      = sanitize_title( $bits[2] );
			}
		}
		if ( '' === $slug ) {
			$slug = 'tab-' . (string) ( count( $tabs ) + 1 );
		}
		$tabs[] = array(
			'slug'     => $slug,
			'label'    => $label,
			'orderBy'  => $order,
			'taxonomy' => $tax,
			'termSlug' => $term,
		);
		if ( count( $tabs ) >= 6 ) {
			break;
		}
	}

	return $tabs;
}

/**
 * Apply the requested filter tab to a block's query attributes.
 *
 * @param array<string, mixed> $attributes Block attributes.
 * @param string               $instance   Unique instance id.
 * @return array{attributes: array<string, mixed>, tabs: array<int, array<string, string>>, active: string}
 */
function musicwave_apply_filter_tab( array $attributes, string $instance ): array {
	$raw  = isset( $attributes['filterTabs'] ) ? (string) $attributes['filterTabs'] : '';
	$tabs = musicwave_parse_filter_tabs( $raw );
	if ( array() === $tabs ) {
		return array(
			'attributes' => $attributes,
			'tabs'       => array(),
			'active'     => '',
		);
	}

	$key    = 'mw_tab_' . sanitize_html_class( $instance );
	$active = '';
	if ( isset( $_GET[ $key ] ) && is_scalar( $_GET[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$active = sanitize_title( wp_unslash( (string) $_GET[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}
	$match = null;
	foreach ( $tabs as $tab ) {
		if ( $tab['slug'] === $active ) {
			$match = $tab;
			break;
		}
	}
	if ( null === $match ) {
		$match  = $tabs[0];
		$active = $match['slug'];
	}
	if ( '' !== $match['orderBy'] ) {
		$attributes['orderBy'] = $match['orderBy'];
	}
	if ( '' !== $match['taxonomy'] && '' !== $match['termSlug'] ) {
		$attributes['taxonomy'] = $match['taxonomy'];
		$attributes['termSlug'] = $match['termSlug'];
	}

	return array(
		'attributes' => $attributes,
		'tabs'       => $tabs,
		'active'     => $active,
	);
}

/**
 * Render the Today / Week / genre pill row.
 *
 * @param array<int, array<string, string>> $tabs     Parsed tabs.
 * @param string                            $active   Active slug.
 * @param string                            $instance Block instance id.
 */
function musicwave_render_filter_tabs( array $tabs, string $active, string $instance ): string {
	if ( array() === $tabs ) {
		return '';
	}

	$key     = 'mw_tab_' . sanitize_html_class( $instance );
	$current = class_exists( '\\ManaCore\\MusicWave\\Core\\Blocks\\BlockSupport' )
		? \ManaCore\MusicWave\Core\Blocks\BlockSupport::current_url()
		: musicwave_current_request_url();
	$items   = '';
	foreach ( $tabs as $tab ) {
		$url    = add_query_arg( $key, $tab['slug'], remove_query_arg( $key, $current ) );
		$is_on  = $tab['slug'] === $active;
		$items .= '<a class="mw-filter-tabs__tab' . ( $is_on ? ' is-active' : '' ) . '" href="' . esc_url( $url ) . '"' . ( $is_on ? ' aria-current="true"' : '' ) . '>' . esc_html( $tab['label'] ) . '</a>';
	}

	return '<nav class="mw-filter-tabs" aria-label="' . esc_attr__( 'فیلتر بازه و سبک', 'musicwave' ) . '">' . $items . '</nav>';
}

/**
 * Current request URL when Core is not loaded.
 */
function musicwave_current_request_url(): string {
	if ( function_exists( 'is_singular' ) && is_singular() ) {
		$permalink = get_permalink();
		if ( is_string( $permalink ) && '' !== $permalink ) {
			return $permalink;
		}
	}
	$request = isset( $_SERVER['REQUEST_URI'] ) && is_scalar( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REQUEST_URI'] ) ) : '/';

	return home_url( $request );
}

/**
 * Register the LRC lyrics field on releases.
 *
 * @return void
 */
function musicwave_register_lyrics_meta(): void {
	if ( ! function_exists( 'register_post_meta' ) ) {
		return;
	}
	register_post_meta(
		'mw_release',
		'mw_lyrics_lrc',
		array(
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => true,
			'sanitize_callback' => 'sanitize_textarea_field',
			'auth_callback'     => static function (): bool {
				return current_user_can( 'edit_posts' );
			},
		)
	);
	register_post_meta(
		'mw_release',
		'mw_lyrics_offset',
		array(
			'type'              => 'integer',
			'single'            => true,
			'show_in_rest'      => true,
			'default'           => 0,
			'sanitize_callback' => static function ( $value ): int {
				return max( -10000, min( 10000, (int) $value ) );
			},
			'auth_callback'     => static function (): bool {
				return current_user_can( 'edit_posts' );
			},
		)
	);
}
add_action( 'init', 'musicwave_register_lyrics_meta' );

/**
 * Parse an LRC document into timed lines.
 *
 * @param string $raw LRC or plain lyrics.
 * @return array<int, array{time: float, text: string}>
 */
function musicwave_parse_lrc( string $raw ): array {
	$lines = array();
	foreach ( preg_split( '/\r\n|\r|\n/', $raw ) as $row ) {
		$row = trim( (string) $row );
		if ( '' === $row || 1 === preg_match( '/^\[[a-z]+:/i', $row ) ) {
			continue;
		}
		if ( preg_match_all( '/\[(\d{1,2}):(\d{2})(?:\.(\d{1,3}))?\]/', $row, $stamps, PREG_SET_ORDER ) ) {
			$text = trim( (string) preg_replace( '/\[\d{1,2}:\d{2}(?:\.\d{1,3})?\]/', '', $row ) );
			if ( '' === $text ) {
				continue;
			}
			$parts       = preg_split( '/\s*(?:\||\/\/)\s+/', $text, 2 );
			$primary     = isset( $parts[0] ) ? trim( (string) $parts[0] ) : $text;
			$translation = isset( $parts[1] ) ? trim( (string) $parts[1] ) : '';
			foreach ( $stamps as $stamp ) {
				$ms      = isset( $stamp[3] ) ? (float) ( '0.' . $stamp[3] ) : 0.0;
				$seconds = ( (int) $stamp[1] * 60 ) + (int) $stamp[2] + $ms;
				$lines[] = array(
					'time'        => $seconds,
					'text'        => $primary,
					'translation' => $translation,
				);
			}
			continue;
		}
		$lines[] = array(
			'time'        => -1.0,
			'text'        => $row,
			'translation' => '',
		);
	}
	usort(
		$lines,
		static function ( $a, $b ): int {
			return $a['time'] <=> $b['time'];
		}
	);

	return $lines;
}

/**
 * Render live-sync lyrics for the current release.
 *
 * @param array<string, mixed> $attributes Block attributes.
 */
function musicwave_render_synced_lyrics( array $attributes ): string {
	$release_id = isset( $attributes['releaseId'] ) ? absint( $attributes['releaseId'] ) : 0;
	if ( $release_id < 1 ) {
		$release_id = absint( get_the_ID() );
	}
	if ( $release_id < 1 || 'mw_release' !== (string) get_post_type( $release_id ) ) {
		return '';
	}

	$raw = (string) get_post_meta( $release_id, 'mw_lyrics_lrc', true );
	if ( '' === $raw ) {
		$raw = isset( $attributes['fallbackText'] ) ? (string) $attributes['fallbackText'] : '';
	}
	$raw = trim( $raw );
	if ( '' === $raw ) {
		return '';
	}

	$parsed = musicwave_parse_lrc( $raw );
	if ( array() === $parsed ) {
		return '';
	}

	if ( function_exists( 'wp_enqueue_script' ) ) {
		wp_enqueue_script( 'musicwave-lyrics' );
	}

	$heading = isset( $attributes['heading'] ) ? sanitize_text_field( (string) $attributes['heading'] ) : '';
	$heading = '' !== $heading ? $heading : __( 'متن هم‌زمان با آهنگ', 'musicwave' );
	$mode    = isset( $attributes['displayMode'] ) ? sanitize_key( (string) $attributes['displayMode'] ) : 'spotlight';
	$mode    = in_array( $mode, array( 'spotlight', 'plain', 'karaoke' ), true ) ? $mode : 'spotlight';
	$offset  = (int) get_post_meta( $release_id, 'mw_lyrics_offset', true );

	$rows = '';
	foreach ( $parsed as $index => $line ) {
		$timed     = $line['time'] >= 0;
		$time_attr = $timed ? ' data-mw-lyric-time="' . esc_attr( (string) $line['time'] ) . '"' : '';
		$tag       = $timed ? 'button' : 'p';
		$type      = $timed ? ' type="button"' : '';
		$stamp     = $timed ? '<span class="mw-lyrics__stamp">' . esc_html( musicwave_format_lyric_clock( $line['time'] ) ) . '</span>' : '';
		$sub       = ! empty( $line['translation'] ) ? '<span class="mw-lyrics__sub">' . esc_html( (string) $line['translation'] ) . '</span>' : '';
		$rows     .= '<' . $tag . $type . ' class="mw-lyrics__line" data-mw-lyric-index="' . esc_attr( (string) $index ) . '"' . $time_attr . '><span class="mw-lyrics__text">' . esc_html( $line['text'] ) . '</span>' . $sub . $stamp . '</' . $tag . '>';
	}

	$modes = '<div class="mw-lyrics__modes" role="group" aria-label="' . esc_attr__( 'حالت نمایش متن', 'musicwave' ) . '">'
		. '<button type="button" class="mw-lyrics__mode' . ( 'spotlight' === $mode ? ' is-active' : '' ) . '" data-mw-lyrics-mode="spotlight">' . esc_html__( 'Sync', 'musicwave' ) . '</button>'
		. '<button type="button" class="mw-lyrics__mode' . ( 'plain' === $mode ? ' is-active' : '' ) . '" data-mw-lyrics-mode="plain">' . esc_html__( 'Full Text', 'musicwave' ) . '</button>'
		. '<button type="button" class="mw-lyrics__mode' . ( 'karaoke' === $mode ? ' is-active' : '' ) . '" data-mw-lyrics-mode="karaoke">' . esc_html__( 'Karaoke', 'musicwave' ) . '</button>'
		. '</div>';

	$tools = '<div class="mw-lyrics__toolbar">'
		. '<button type="button" class="mw-lyrics__tool is-active" data-mw-lyrics-autoscroll aria-pressed="true">' . esc_html__( 'اسکرول خودکار', 'musicwave' ) . '</button>'
		. '<span class="mw-lyrics__toolbar-sep" aria-hidden="true">·</span>'
		. '<span class="mw-lyrics__hint">' . esc_html__( 'برای پرش، روی خط بزنید', 'musicwave' ) . '</span>'
		. '<span class="mw-lyrics__grow"></span>'
		. '<button type="button" class="mw-lyrics__scale" data-mw-lyrics-scale="-1" aria-label="' . esc_attr__( 'کوچک‌تر کردن متن', 'musicwave' ) . '">A−</button>'
		. '<button type="button" class="mw-lyrics__scale" data-mw-lyrics-scale="1" aria-label="' . esc_attr__( 'بزرگ‌تر کردن متن', 'musicwave' ) . '">A+</button>'
		. '</div>';

	$foot = '<footer class="mw-lyrics__foot"><span>' . esc_html__( 'متن از کاتالوگ همین انتشار · هم‌زمان با پخش‌کننده سراسری', 'musicwave' ) . '</span></footer>';

	return '<section ' . get_block_wrapper_attributes( array( 'class' => 'mw-lyrics mw-lyrics--' . $mode ) ) . ' data-mw-lyrics data-scroll="center" data-mw-lyric-offset="' . esc_attr( (string) $offset ) . '" data-release-id="' . esc_attr( (string) $release_id ) . '"><header class="mw-lyrics__header"><div class="mw-lyrics__heading"><span class="mw-lyrics__eyebrow">' . esc_html__( 'Live Sync Lyrics', 'musicwave' ) . '</span><h2>' . esc_html( $heading ) . '</h2></div>' . $modes . '</header>' . $tools . '<div class="mw-lyrics__stage" data-mw-lyrics-stage>' . $rows . '</div>' . $foot . '</section>';
}

/**
 * Format a lyric timestamp as mm:ss.
 */
function musicwave_format_lyric_clock( float $seconds ): string {
	if ( $seconds < 0 ) {
		return '';
	}

	$minutes = (int) floor( $seconds / 60 );
	$remain  = (int) floor( $seconds ) % 60;

	return sprintf( '%02d:%02d', $minutes, $remain );
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
	$section_link_label = '' !== $section_link_label ? $section_link_label : __( 'مشاهدهٔ همهٔ فهرست‌های پخش', 'musicwave' );
	if ( '' === $title && 'playlists' === ( isset( $attributes['source'] ) ? sanitize_key( (string) $attributes['source'] ) : '' ) ) {
		$title = __( 'فهرست‌های پخش اجتماعی', 'musicwave' );
	}
	if ( '' === $eyebrow ) {
		$eyebrow = __( 'منتخب شنوندگان', 'musicwave' );
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
		// Same cover-stack markup as Core's PlaylistBlocks::playlist_covers_markup()
		// so the fanned artwork styling in playlists.css covers both surfaces.
		if ( array() === $ids ) {
			$art_grid = '<span class="mw-playlists__art-grid mw-playlists__art-grid--empty" aria-hidden="true"><span class="mw-playlists__art-placeholder">♫</span></span>';
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
					$cells .= '<span class="mw-playlists__art-cell">' . $thumb . '</span>';
				} else {
					$init   = mb_substr( $ptitle, 0, 1 );
					$cells .= '<span class="mw-playlists__art-cell mw-playlists__art-cell--fallback" aria-hidden="true"><span>' . esc_html( $init ) . '</span></span>';
				}
			}
			// Pad to 4.
			for ( $p = count( $ids ); $p < 4; $p++ ) {
				$cells .= '<span class="mw-playlists__art-cell mw-playlists__art-cell--empty" aria-hidden="true"></span>';
			}
			$art_grid = '<span class="mw-playlists__art-grid">' . $cells . '</span>';
		}

		// Play button uses the same global queue as releases: data-mw-playlist-play.
		$play = '<button type="button" class="mw-release-shelf__play mw-card-play mw-release-shelf__play--playlist" data-mw-playlist-play data-playlist-id="' . esc_attr( (string) $pid ) . '" aria-label="' . esc_attr( sprintf( /* translators: %s: playlist title. */ __( 'پخش همه قطعه‌های %s', 'musicwave' ), $ptitle ) ) . '"' . ( 0 === $count ? ' disabled' : '' ) . '><span aria-hidden="true">▶</span></button>';

		/* translators: %s: playlist title. */
		$thumb_wrap = '<div class="mw-release-shelf__artwrap"><a class="mw-release-shelf__art mw-release-shelf__art--' . esc_attr( $shape ) . ' mw-release-shelf__art--playlist" href="' . esc_url( $link ) . '" aria-label="' . esc_attr( sprintf( __( 'باز کردن %s', 'musicwave' ), $ptitle ) ) . '">' . $art_grid . '</a>' . $play . '</div>';
		$artist_m   = '' !== $author ? '<span class="mw-release-shelf__artist">' . esc_html( $author ) . '</span>' : '';
		/* translators: %d: number of tracks in playlist. */
		$count_m = '<span class="mw-release-shelf__count">' . esc_html( sprintf( _n( '%d قطعه', '%d قطعه', $count, 'musicwave' ), $count ) ) . '</span>';
		$cards[] = '<article class="mw-release-shelf__item mw-release-shelf__item--playlist">' . $thumb_wrap . '<div class="mw-release-shelf__body"><h3><a href="' . esc_url( $link ) . '">' . esc_html( $ptitle ) . '</a></h3>' . $artist_m . $count_m . '</div></article>';
	}

	$header = musicwave_render_shelf_header( $eyebrow, $title, $description, $section_url, $section_link_label );
	$nav    = musicwave_shelf_nav_markup( $layout );

	$shelf_variant = musicwave_style_variant_class(
		$attributes,
		array_keys( musicwave_presentation_style_variations()['release-shelf'] ),
		'mw-release-shelf'
	);

	return '<section ' . get_block_wrapper_attributes( array( 'class' => 'mw-release-shelf mw-release-shelf--' . $layout . ' mw-release-shelf--playlists mw-release-shelf--columns-' . $columns . $shelf_variant ) ) . '>' . $header . $nav . '<div class="mw-release-shelf__items" data-mw-shelf-viewport>' . implode( '', $cards ) . '</div></section>';
}

/**
 * Floating previous/next arrows for horizontal (scroll) shelves.
 *
 * Returns an empty string for every other layout. The chevrons are inline
 * SVG (like the hero slider) so the stylesheet can mirror them in RTL, and
 * both buttons start disabled until the shared slider handler measures the
 * row, which avoids a flash of active arrows on rows that do not overflow.
 * The handler script is enqueued here so shelf-free routes ship no bytes.
 *
 * @param string $layout Resolved shelf layout key.
 */
function musicwave_shelf_nav_markup( string $layout ): string {
	if ( 'scroll' !== $layout ) {
		return '';
	}
	if ( function_exists( 'wp_enqueue_script' ) ) {
		wp_enqueue_script( 'musicwave-slider' );
	}

	$chevron_previous = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m15 18-6-6 6-6"/></svg>';
	$chevron_next     = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m9 18 6-6-6-6"/></svg>';

	return '<div class="mw-release-shelf__nav">'
		. '<button type="button" class="mw-release-shelf__nav-button" data-mw-shelf-previous aria-label="' . esc_attr__( 'قبلی', 'musicwave' ) . '" disabled>' . $chevron_previous . '</button>'
		. '<button type="button" class="mw-release-shelf__nav-button" data-mw-shelf-next aria-label="' . esc_attr__( 'بعدی', 'musicwave' ) . '" disabled>' . $chevron_next . '</button>'
		. '</div>';
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

	$shelf_instance = wp_unique_id( 'mw-shelf-' );
	$tabbed         = musicwave_apply_filter_tab( $attributes, $shelf_instance );
	$attributes     = $tabbed['attributes'];

	$items = isset( $attributes['itemsToShow'] ) ? absint( $attributes['itemsToShow'] ) : 8;
	$items = min( 24, max( 1, $items ) );
	$ids   = get_posts( musicwave_release_query_args( $attributes, $items ) );
	if ( ! is_array( $ids ) || empty( $ids ) ) {
		return '';
	}

	$layout  = isset( $attributes['layout'] ) ? sanitize_key( (string) $attributes['layout'] ) : 'grid';
	$layout  = in_array( $layout, array( 'grid', 'scroll', 'list', 'feature', 'slider' ), true ) ? $layout : 'grid';
	$shape   = isset( $attributes['imageShape'] ) ? sanitize_key( (string) $attributes['imageShape'] ) : 'square';
	$shape   = in_array( $shape, array( 'square', 'landscape', 'portrait', 'circle' ), true ) ? $shape : 'square';
	$columns = isset( $attributes['columns'] ) ? absint( $attributes['columns'] ) : 4;
	$columns = min( 6, max( 2, $columns ) );

	// Editorial × Vinyl look. Resolved once and handed to whichever layout
	// renders, so the Site Editor style picker, the block inspector select,
	// and the markup always agree on a single modifier class.
	$shelf_variant = musicwave_style_variant_class(
		$attributes,
		array_keys( musicwave_presentation_style_variations()['release-shelf'] ),
		'mw-release-shelf'
	);

	// Resolve section copy before rendering any cards. Feature layout has its
	// own renderer; returning here prevents the generic card loop from doing
	// duplicate title, taxonomy, and image work that it would immediately throw
	// away (the previous implementation paid for both render paths).
	$eyebrow            = isset( $attributes['eyebrow'] ) ? sanitize_text_field( (string) $attributes['eyebrow'] ) : '';
	$section_title      = isset( $attributes['title'] ) ? sanitize_text_field( (string) $attributes['title'] ) : '';
	$description        = isset( $attributes['description'] ) ? sanitize_text_field( (string) $attributes['description'] ) : '';
	$section_url        = isset( $attributes['sectionUrl'] ) ? esc_url( (string) $attributes['sectionUrl'] ) : '';
	$section_link_label = isset( $attributes['sectionLinkLabel'] ) ? sanitize_text_field( (string) $attributes['sectionLinkLabel'] ) : '';
	$section_link_label = '' !== $section_link_label ? $section_link_label : __( 'مشاهده همه', 'musicwave' );
	if ( 'feature' === $layout ) {
		return musicwave_render_feature_shelf( $attributes, $ids, $eyebrow, $section_title, $description, $section_url, $section_link_label, $shelf_variant );
	}
	if ( 'slider' === $layout ) {
		return musicwave_render_hero_slider( $attributes, $ids, $eyebrow, $section_title, $description, $section_url, $section_link_label, $shelf_variant );
	}

	$action_label = isset( $attributes['actionLabel'] ) ? sanitize_text_field( (string) $attributes['actionLabel'] ) : '';
	$action_label = '' !== $action_label ? $action_label : __( 'باز کردن انتشار', 'musicwave' );
	$show_artwork = ! isset( $attributes['showArtwork'] ) || false !== $attributes['showArtwork'];
	$show_play    = ! isset( $attributes['showPlayButton'] ) || false !== $attributes['showPlayButton'];
	$cards        = array();

	foreach ( $ids as $idx => $release_id ) {
		$item = musicwave_release_presentation_data( absint( $release_id ) );
		if ( empty( $item ) ) {
			continue;
		}

		$release_id = (int) $item['id'];
		$is_first   = $idx < 2;

		// The shelf always shows a play affordance when asked for one, so the
		// filtered button falls back to a decorative glyph.
		$play_button = '';
		if ( $show_play ) {
			$play_button = apply_filters( 'music_wave_card_play_button', '', $release_id, 'mw-release-shelf__play' );
			$play_button = is_string( $play_button ) && '' !== $play_button ? $play_button : '<span class="mw-release-shelf__play" aria-hidden="true">&#9654;</span>';
		}

		$date_markup = ! empty( $attributes['showDate'] )
			? '<time datetime="' . esc_attr( get_the_date( 'c', $release_id ) ) . '">' . esc_html( get_the_date( '', $release_id ) ) . '</time>'
			: '';

		/* translators: %s: music release title. */
		$open_label = sprintf( __( 'باز کردن %s', 'musicwave' ), (string) $item['title'] );

		$card = musicwave_render_release_card(
			$item,
			array(
				'shape'            => $shape,
				'image_attributes' => array(
					'loading'       => $is_first ? 'eager' : 'lazy',
					'fetchpriority' => $is_first ? 'high' : 'low',
					'decoding'      => 'async',
				),
				'open_label'       => $open_label,
				'overlay'          => $play_button,
				'show_artwork'     => $show_artwork,
				'show_artist'      => ! isset( $attributes['showArtist'] ) || false !== $attributes['showArtist'],
				'show_excerpt'     => ! empty( $attributes['showExcerpt'] ),
				'show_action'      => ! isset( $attributes['showAction'] ) || false !== $attributes['showAction'],
				'action_label'     => $action_label,
				'meta_html'        => $date_markup,
			)
		);

		if ( '' !== $card ) {
			$cards[] = $card;
		}
	}
	if ( empty( $cards ) ) {
		return '';
	}

	$header      = musicwave_render_shelf_header( $eyebrow, $section_title, $description, $section_url, $section_link_label );
	$tabs_markup = musicwave_render_filter_tabs( $tabbed['tabs'], $tabbed['active'], $shelf_instance );

	$nav = musicwave_shelf_nav_markup( $layout );

	return '<section ' . get_block_wrapper_attributes( array( 'class' => 'mw-release-shelf mw-release-shelf--' . $layout . ' mw-release-shelf--columns-' . $columns . $shelf_variant ) ) . '>' . $header . $tabs_markup . $nav . '<div class="mw-release-shelf__items" data-mw-shelf-viewport>' . implode( '', $cards ) . '</div></section>';
}

/**
 * Render the editorial "feature" layout: a large featured release cover
 * with a gradient overlay alongside a compact list of supporting releases.
 *
 * @param array<string, mixed> $attributes    Block attributes.
 * @param array<int, int>      $ids           Queried release IDs.
 * @param string               $eyebrow       برچسب بالایی text.
 * @param string               $title         Section title.
 * @param string               $description   Section description.
 * @param string               $section_url   Optional "مشاهده همه" URL.
 * @param string               $section_link_label برچسب for the "مشاهده همه" link.
 * @param string               $variant_class Optional style-variation modifier class.
 */
function musicwave_render_feature_shelf( array $attributes, array $ids, string $eyebrow, string $title, string $description, string $section_url, string $section_link_label, string $variant_class = '' ): string {
	$featured_id = isset( $attributes['featuredReleaseId'] ) ? absint( $attributes['featuredReleaseId'] ) : 0;
	if ( $featured_id < 1 || ! in_array( $featured_id, $ids, true ) ) {
		$featured_id = absint( $ids[0] );
	}
	$side_ids = array_values( array_diff( $ids, array( $featured_id ) ) );
	$side_ids = array_slice( $side_ids, 0, 4 );

	$featured_item = musicwave_release_presentation_data( $featured_id );
	if ( empty( $featured_item ) ) {
		return '';
	}
	$featured_id     = (int) $featured_item['id'];
	$featured_link   = (string) $featured_item['link'];
	$featured_title  = (string) $featured_item['title'];
	$featured_artist = (string) $featured_item['artist'];
	$featured_art    = get_the_post_thumbnail(
		$featured_id,
		'large',
		array(
			'class' => 'mw-feature__art-img',
			'alt'   => '',
		)
	);

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
		: __( 'باز کردن انتشار', 'musicwave' );
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
			$featured_views = '<span class="mw-feature__views">' . esc_html( number_format_i18n( $views ) ) . ' ' . esc_html__( 'بازدیدها', 'musicwave' ) . '</span>';
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
			$item = musicwave_release_presentation_data( absint( $rid ) );
			if ( empty( $item ) ) {
				continue;
			}
			$rid          = (int) $item['id'];
			$rlink        = (string) $item['link'];
			$rtitle       = (string) $item['title'];
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
			$items[] = '<li class="mw-feature__side-item">' . $rank_markup . ( '' !== $rthumb ? '<a href="' . esc_url( $rlink ) . '" class="mw-feature__side-art">' . $rthumb . '</a>' : '' ) . '<div class="mw-feature__side-body"><a href="' . esc_url( $rlink ) . '" class="mw-feature__side-title">' . esc_html( $rtitle ) . '</a>' . $views_markup . '</div></li>';
		}
		if ( ! empty( $items ) ) {
			$list_title = '' !== $title ? $title : __( 'انتشارهای بیشتر', 'musicwave' );
			$list       = '<aside class="mw-feature__aside" ' . $aside_style . '><h3 class="mw-feature__aside-title">' . esc_html( $list_title ) . '</h3><ul class="mw-feature__side-list">' . implode( '', $items ) . '</ul>';
			if ( '' !== $section_url ) {
				$list .= '<a class="mw-feature__more" href="' . esc_url( $section_url ) . '">' . esc_html( $section_link_label ) . '<span aria-hidden="true">&rarr;</span></a>';
			}
			$list .= '</aside>';
		}
	}

	$style_attr = 'style="--mw-feature-overlay:' . esc_attr( (string) $overlay ) . '"';

	return '<section ' . get_block_wrapper_attributes( array( 'class' => 'mw-release-shelf mw-release-shelf--feature' . $variant_class ) ) . ' ' . $style_attr . '><div class="mw-feature">' . $hero . $list . '</div></section>';
}

/**
 * Render the hero slider layout: full-bleed crossfading slides in the
 * SonicStream hero style — one release per slide with a softened backdrop,
 * an accent-to-canvas gradient scrim, a display title, play/open actions,
 * and a crisp side cover. Arrows, dots, autoplay, and the fade engine live
 * in assets/hero-slider.js, which enqueues here at render time so routes
 * without a hero slider ship no slider bytes.
 *
 * @param array<string, mixed> $attributes Block attributes.
 * @param array<int, int>      $ids        Queried release IDs.
 * @param string               $eyebrow    Section eyebrow label.
 * @param string               $title      Section title.
 * @param string               $description Section description.
 * @param string               $section_url Optional "see all" URL.
 * @param string               $section_link_label Label for the "see all" link.
 * @param string               $variant_class Optional style-variation modifier class.
 */
function musicwave_render_hero_slider( array $attributes, array $ids, string $eyebrow, string $title, string $description, string $section_url, string $section_link_label, string $variant_class = '' ): string {
	if ( function_exists( 'wp_enqueue_script' ) ) {
		wp_enqueue_script( 'musicwave-hero-slider' );
	}

	// Hero slides read as editorial statements; beyond eight they dilute.
	$ids = array_slice( $ids, 0, 8 );

	$autoplay     = ! isset( $attributes['autoplay'] ) || false !== $attributes['autoplay'];
	$show_arrows  = ! isset( $attributes['showArrows'] ) || false !== $attributes['showArrows'];
	$show_dots    = ! isset( $attributes['showDots'] ) || false !== $attributes['showDots'];
	$interval     = isset( $attributes['interval'] ) ? absint( $attributes['interval'] ) : 0;
	$interval     = $interval >= 2000 && $interval <= 20000 ? $interval : 5000;
	$action_label = isset( $attributes['actionLabel'] ) ? sanitize_text_field( (string) $attributes['actionLabel'] ) : '';
	$action_label = '' !== $action_label ? $action_label : __( 'باز کردن انتشار', 'musicwave' );
	$show_play    = ! isset( $attributes['showPlayButton'] ) || false !== $attributes['showPlayButton'];

	$data = array();
	foreach ( $ids as $idx => $release_id ) {
		$item = musicwave_release_presentation_data( absint( $release_id ) );
		if ( empty( $item ) ) {
			continue;
		}

		$release_id = (int) $item['id'];
		$is_first   = 0 === $idx;

		/* translators: %s: music release title. */
		$open_label = sprintf( __( 'باز کردن %s', 'musicwave' ), (string) $item['title'] );

		// Softened full-bleed backdrop; the first slide is the LCP image.
		$backdrop = get_the_post_thumbnail(
			$release_id,
			'large',
			array(
				'class'         => 'mw-hero-slide__backdrop',
				'alt'           => '',
				'loading'       => $is_first ? 'eager' : 'lazy',
				'fetchpriority' => $is_first ? 'high' : 'low',
				'decoding'      => 'async',
			)
		);
		$backdrop = '' !== $backdrop
			? $backdrop
			: '<div class="mw-hero-slide__backdrop mw-hero-slide__backdrop--gradient" role="img" aria-label="' . esc_attr( $open_label ) . '"></div>';

		$art = get_the_post_thumbnail(
			$release_id,
			'medium_large',
			array(
				'class'    => 'mw-hero-slide__art-img',
				'alt'      => '',
				'loading'  => $is_first ? 'eager' : 'lazy',
				'decoding' => 'async',
			)
		);
		$art = '' !== $art
			? $art
			: '<span class="mw-hero-slide__art-fallback" aria-hidden="true">' . esc_html( (string) $item['initial'] ) . '</span>';

		// Factual per-slide badge (album / single / EP) so every slide keeps
		// its own identity, like the reference hero's kicker per slide.
		$types  = wp_get_post_terms( $release_id, 'mw_release_type', array( 'fields' => 'names' ) );
		$kicker = is_array( $types ) && ! empty( $types[0] ) ? (string) $types[0] : '';

		// Meta line: year · genre, joined with the reference dot separators.
		$meta_parts = array();
		$year       = (string) get_the_date( 'Y', $release_id );
		if ( '' !== $year ) {
			$meta_parts[] = $year;
		}
		$genres = wp_get_post_terms( $release_id, 'mw_genre', array( 'fields' => 'names' ) );
		$genre  = is_array( $genres ) && ! empty( $genres[0] ) ? (string) $genres[0] : '';
		if ( '' !== $genre ) {
			$meta_parts[] = $genre;
		}

		$play_button = '';
		if ( $show_play ) {
			$play_button = apply_filters( 'music_wave_card_play_button', '', $release_id, 'mw-hero-slide__play' );
			$play_button = is_string( $play_button ) && '' !== $play_button
				? $play_button
				: '<span class="mw-hero-slide__play mw-hero-slide__play--static" aria-hidden="true">&#9654;</span>';
		}

		$data[] = array(
			'link'       => (string) $item['link'],
			'title'      => (string) $item['title'],
			'artist'     => (string) $item['artist'],
			'open_label' => $open_label,
			'backdrop'   => $backdrop,
			'art'        => $art,
			'kicker'     => $kicker,
			'meta'       => implode( ' \u{00B7} ', $meta_parts ),
			'play'       => $play_button,
		);
	}
	if ( empty( $data ) ) {
		return '';
	}

	$slides = array();
	foreach ( $data as $i => $slide ) {
		$is_active = 0 === $i;
		/* translators: 1: slide index, 2: music release title. */
		$slide_label = sprintf( __( 'اسلاید %1$d: %2$s', 'musicwave' ), $i + 1, $slide['title'] );

		$slides[] = '<article class="mw-hero-slide' . ( $is_active ? ' is-active' : '' ) . '" role="group" aria-label="' . esc_attr( $slide_label ) . '"' . ( $is_active ? '' : ' aria-hidden="true"' ) . '>'
			. $slide['backdrop']
			. '<div class="mw-hero-slide__scrim" aria-hidden="true"></div>'
			. '<div class="mw-hero-slide__content">'
			. ( '' !== $slide['kicker'] ? '<span class="mw-hero-slide__kicker">' . esc_html( $slide['kicker'] ) . '</span>' : '' )
			. '<h3 class="mw-hero-slide__title"><a href="' . esc_url( $slide['link'] ) . '">' . esc_html( $slide['title'] ) . '</a></h3>'
			. ( '' !== $slide['artist'] ? '<span class="mw-hero-slide__artist">' . esc_html( $slide['artist'] ) . '</span>' : '' )
			. ( '' !== $slide['meta'] ? '<p class="mw-hero-slide__meta">' . esc_html( $slide['meta'] ) . '</p>' : '' )
			. '<div class="mw-hero-slide__actions">' . $slide['play'] . '<a class="mw-hero-slide__open" href="' . esc_url( $slide['link'] ) . '">' . esc_html( $action_label ) . '</a></div>'
			. '</div>'
			. '<a class="mw-hero-slide__art" href="' . esc_url( $slide['link'] ) . '" aria-label="' . esc_attr( $slide['open_label'] ) . '">' . $slide['art'] . '</a>'
			. '</article>';
	}

	$id = wp_unique_id( 'mw-hero-slider-' );

	$arrows = $show_arrows
		? '<div class="mw-hero-slider__arrows">'
			. '<button type="button" class="mw-hero-slider__arrow" data-mw-hero-previous aria-controls="' . esc_attr( $id ) . '" aria-label="' . esc_attr__( 'اسلاید قبلی', 'musicwave' ) . '"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg></button>'
			. '<button type="button" class="mw-hero-slider__arrow" data-mw-hero-next aria-controls="' . esc_attr( $id ) . '" aria-label="' . esc_attr__( 'اسلاید بعدی', 'musicwave' ) . '"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg></button>'
			. '</div>'
		: '';

	$dot_buttons = '';
	foreach ( $data as $i => $slide ) {
		$dot_buttons .= '<button type="button" class="mw-hero-slider__dot' . ( 0 === $i ? ' is-active' : '' ) . '" data-mw-hero-dot="' . (string) $i . '" aria-label="' . esc_attr( sprintf( /* translators: %d: slide index. */ __( 'رفتن به اسلاید %d', 'musicwave' ), $i + 1 ) ) . '"' . ( 0 === $i ? ' aria-current="true"' : '' ) . '"></button>';
	}
	$dot_container = $show_dots
		? '<div class="mw-hero-slider__dots" data-mw-hero-dots role="group" aria-label="' . esc_attr__( 'صفحهبندی اسلایدر', 'musicwave' ) . '">' . $dot_buttons . '</div>'
		: '';

	$region_label = '' !== $title ? $title : __( 'اسلایدر انتشارها', 'musicwave' );
	$header       = musicwave_render_shelf_header( $eyebrow, $title, $description, $section_url, $section_link_label );

	return '<section ' . get_block_wrapper_attributes( array( 'class' => 'mw-release-shelf mw-release-shelf--slider' . $variant_class ) ) . '>'
		. $header
		. '<div id="' . esc_attr( $id ) . '" class="mw-hero-slider" data-mw-hero-slider data-autoplay="' . esc_attr( $autoplay ? '1' : '0' ) . '" data-pause-hover="1" data-interval="' . esc_attr( (string) $interval ) . '">'
		. '<div class="mw-hero-slider__viewport" tabindex="0" role="region" aria-roledescription="' . esc_attr__( 'اسلایدر', 'musicwave' ) . '" aria-label="' . esc_attr( $region_label ) . '">'
		. implode( '', $slides )
		. '</div>'
		. $arrows
		. $dot_container
		. '<p class="screen-reader-text" aria-live="polite" data-mw-hero-status></p>'
		. '</div>'
		. '</section>';
}

/**
 * Track front-end بازدیدها for mw_release posts.
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
	$label = __( 'استفاده از پوسته سیستم', 'musicwave' );

	// One SVG per preference; theme-toggle.css reveals the icon that matches
	// data-mw-theme-value so theme-preference.js only swaps an attribute.
	$icons = '<svg class="mw-theme-toggle__icon mw-theme-toggle__icon--system" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm0 2v16a8 8 0 0 1 0-16Z"/></svg>'
		. '<svg class="mw-theme-toggle__icon mw-theme-toggle__icon--light" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 7a5 5 0 1 0 0 10 5 5 0 0 0 0-10Zm0-6a1 1 0 0 1 1 1v2a1 1 0 1 1-2 0V2a1 1 0 0 1 1-1Zm0 19a1 1 0 0 1 1 1v2a1 1 0 1 1-2 0v-2a1 1 0 0 1 1-1ZM1 12a1 1 0 0 1 1-1h2a1 1 0 1 1 0 2H2a1 1 0 0 1-1-1Zm19 0a1 1 0 0 1 1-1h2a1 1 0 1 1 0 2h-2a1 1 0 0 1-1-1ZM4.22 4.22a1 1 0 0 1 1.42 0l1.41 1.41a1 1 0 0 1-1.41 1.42L4.22 5.64a1 1 0 0 1 0-1.42Zm12.73 12.73a1 1 0 0 1 1.41 0l1.42 1.41a1 1 0 0 1-1.42 1.42l-1.41-1.42a1 1 0 0 1 0-1.41Zm2.83-12.73a1 1 0 0 1 0 1.42l-1.42 1.41a1 1 0 1 1-1.41-1.41l1.41-1.42a1 1 0 0 1 1.42 0ZM7.05 16.95a1 1 0 0 1 0 1.41l-1.41 1.42a1 1 0 0 1-1.42-1.42l1.42-1.41a1 1 0 0 1 1.41 0Z"/></svg>'
		. '<svg class="mw-theme-toggle__icon mw-theme-toggle__icon--dark" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path fill="currentColor" d="M21.64 13.4A9 9 0 0 1 10.6 2.36a1 1 0 0 0-1.2-1.3A11 11 0 1 0 22.94 14.6a1 1 0 0 0-1.3-1.2ZM12 21a9 9 0 0 1-3.87-17.13A11 11 0 0 0 20.13 15.87 9 9 0 0 1 12 21Z"/></svg>';

	return '<button class="mw-theme-toggle" type="button" data-mw-theme-value="system" aria-label="' . esc_attr( $label ) . '" title="' . esc_attr( $label ) . '">' . $icons . '</button>';
}

/**
 * The Sidebar template part is Appearance → Widgets (musicwave-sidebar).
 * Empty widget areas render nothing so the rail does not occupy a column.
 */
function musicwave_inject_sidebar_widgets( string $content, array $block ): string {
	$slug = isset( $block['attrs']['slug'] ) ? (string) $block['attrs']['slug'] : '';
	if ( 'sidebar' !== $slug ) {
		return $content;
	}

	$widgets = '';
	if ( function_exists( 'is_active_sidebar' ) && is_active_sidebar( 'musicwave-sidebar' ) ) {
		ob_start();
		dynamic_sidebar( 'musicwave-sidebar' );
		$widgets = trim( (string) ob_get_clean() );
	}

	if ( '' === $widgets ) {
		return '';
	}

	return '<aside class="mw-sidebar"><div class="mw-sidebar__widgets">' . $widgets . '</div></aside>';
}
add_filter( 'render_block_core/template-part', 'musicwave_inject_sidebar_widgets', 10, 2 );

require_once get_template_directory() . '/inc/site-header.php';
require get_template_directory() . '/inc/nav-icons.php';
