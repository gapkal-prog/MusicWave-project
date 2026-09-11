<?php
/**
 * Frontend release rendering module.
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Modules;

use ManaCore\MusicWave\Core\Blocks\ReleaseBlocks;
use ManaCore\MusicWave\Core\Blocks\ArtistProfileBlock;
use ManaCore\MusicWave\Core\Blocks\ArtistShelfBlock;
use ManaCore\MusicWave\Core\Blocks\BlockMetadata;
use ManaCore\MusicWave\Core\Blocks\BlockSupport;
use ManaCore\MusicWave\Core\Blocks\PreviewPlayer;
use ManaCore\MusicWave\Core\Blocks\TaxonomyShelfBlock;
use ManaCore\MusicWave\Core\Blocks\TermHeroBlock;
use ManaCore\MusicWave\Core\Blocks\ShareButtonBlock;
use ManaCore\MusicWave\Core\Blocks\ShuffleButtonBlock;
use ManaCore\MusicWave\Core\Contracts\Module;
use ManaCore\MusicWave\Core\Infrastructure\ReleaseRestVisibilityPolicy;
use ManaCore\MusicWave\Core\Playback\PlaybackQueueRoutes;

final class Rendering implements Module {
	/** @var ReleaseBlocks */
	private $blocks;

	/** @var ArtistProfileBlock|null */
	private $artist_profile;

	/** @var ArtistShelfBlock|null */
	private $artist_shelf;

	/** @var TaxonomyShelfBlock|null */
	private $taxonomy_shelf;

	/** @var TermHeroBlock|null */
	private $term_hero;

	/** @var PreviewPlayer|null */
	private $preview_player;

	/** @var ShareButtonBlock|null */
	private $share_button;

	/** @var ShuffleButtonBlock|null */
	private $shuffle_button;

	/** @var PlaybackQueueRoutes|null */
	private $playback_queue;

	/** @var ReleaseRestVisibilityPolicy|null */
	private $rest_visibility;

	public function __construct( ReleaseBlocks $blocks, ?ArtistProfileBlock $artist_profile = null, ?PreviewPlayer $preview_player = null, ?PlaybackQueueRoutes $playback_queue = null, ?ReleaseRestVisibilityPolicy $rest_visibility = null, ?ArtistShelfBlock $artist_shelf = null, ?TaxonomyShelfBlock $taxonomy_shelf = null, ?TermHeroBlock $term_hero = null, ?ShareButtonBlock $share_button = null, ?ShuffleButtonBlock $shuffle_button = null ) {
		$this->blocks          = $blocks;
		$this->artist_profile  = $artist_profile;
		$this->artist_shelf    = $artist_shelf;
		$this->taxonomy_shelf  = $taxonomy_shelf;
		$this->term_hero       = $term_hero;
		$this->share_button    = $share_button;
		$this->shuffle_button  = $shuffle_button;
		$this->preview_player  = $preview_player;
		$this->playback_queue  = $playback_queue;
		$this->rest_visibility = $rest_visibility;
	}

	public function register(): void {
		add_action( 'init', array( $this->blocks, 'register' ), 20 );
		if ( null !== $this->artist_profile ) {
			add_action( 'init', array( $this->artist_profile, 'register' ), 21 );
		}
		if ( null !== $this->artist_shelf ) {
			add_action( 'init', array( $this->artist_shelf, 'register' ), 21 );
		}
		if ( null !== $this->taxonomy_shelf ) {
			add_action( 'init', array( $this->taxonomy_shelf, 'register' ), 21 );
		}
		if ( null !== $this->term_hero ) {
			add_action( 'init', array( $this->term_hero, 'register' ), 21 );
		}
		if ( null !== $this->share_button ) {
			add_action( 'init', array( $this->share_button, 'register' ), 22 );
		}
		if ( null !== $this->shuffle_button ) {
			add_action( 'init', array( $this->shuffle_button, 'register' ), 22 );
		}
		if ( null !== $this->preview_player ) {
			add_action( 'init', array( $this->preview_player, 'register' ), 22 );
		}
		if ( null !== $this->playback_queue ) {
			$this->playback_queue->register();
		}
		if ( null !== $this->rest_visibility ) {
			$this->rest_visibility->register();
		}
		add_action( 'init', array( $this, 'register_block_styles' ), 30 );
		add_filter( 'block_categories_all', array( $this, 'register_block_category' ) );
		add_filter( 'the_content', array( $this->blocks, 'filter_content' ), 20 );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_blocks' ) );
		add_action( 'enqueue_block_assets', array( $this, 'enqueue_editor_canvas_styles' ) );
	}

	/**
	 * Add the dedicated MusicWave category to the block inserter.
	 *
	 * @param mixed $categories Existing block categories.
	 * @return array<int, array<string, mixed>>
	 */
	public function register_block_category( $categories ): array {
		$categories = is_array( $categories ) ? $categories : array();
		foreach ( $categories as $category ) {
			if ( is_array( $category ) && isset( $category['slug'] ) && 'music-wave' === $category['slug'] ) {
				return $categories;
			}
		}

		array_unshift(
			$categories,
			array(
				'slug'  => 'music-wave',
				'title' => __( 'MusicWave', 'music-wave-core' ),
				'icon'  => 'format-audio',
			)
		);

		return $categories;
	}

	/**
	 * Expose existing layout modifiers as editor style variations.
	 *
	 * The renderers translate the chosen is-style-* class into the same
	 * modifier classes produced by the inspector toggles, so both surfaces
	 * stay visually identical.
	 *
	 * @return void
	 */
	public function register_block_styles(): void {
		if ( ! function_exists( 'register_block_style' ) ) {
			return;
		}

		foreach ( self::style_variations() as $block_name => $variations ) {
			foreach ( $variations as $variation ) {
				register_block_style( $block_name, $variation );
			}
		}
	}

	/**
	 * Style variations offered by the dynamic blocks.
	 *
	 * Single source of truth for two surfaces that must never drift: the
	 * Site Editor "Styles" panel (`register_block_style`) and the appearance
	 * select inside each block's MusicWave settings panel (`assets/blocks.js`).
	 * Both write the same `is-style-<name>` class, so whichever surface the
	 * admin uses, the other follows.
	 *
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	public static function style_variations(): array {
		$styles = array(
			'music-wave/preview-player'    => array(
				array(
					'name'  => 'outline',
					'label' => __( 'طرح کلی', 'music-wave-core' ),
				),
				array(
					'name'  => 'ghost',
					'label' => __( 'بی‌زمینه', 'music-wave-core' ),
				),
			),
			'music-wave/account-dashboard' => array(
				array(
					'name'       => 'tabs',
					'label'      => __( 'برگه‌ها (یک بخش در یک زمان)', 'music-wave-core' ),
					'is_default' => true,
				),
				array(
					'name'  => 'stacked',
					'label' => __( 'روی‌هم‌چیده (همهٔ بخش‌ها نمایش داده می‌شوند)', 'music-wave-core' ),
				),
			),
			'music-wave/preview-button'    => array(
				array(
					'name'  => 'outline',
					'label' => __( 'طرح کلی', 'music-wave-core' ),
				),
				array(
					'name'  => 'ghost',
					'label' => __( 'بی‌زمینه', 'music-wave-core' ),
				),
				array(
					'name'  => 'vinyl',
					'label' => __( 'صفحهٔ وینیل (چرخان)', 'music-wave-core' ),
				),
			),
			'music-wave/release-meta'      => array(
				array(
					'name'  => 'inline',
					'label' => __( 'درون‌خطی', 'music-wave-core' ),
				),
				array(
					'name'  => 'stack',
					'label' => __( 'ردیف‌های انباشته', 'music-wave-core' ),
				),
				array(
					'name'  => 'stamp',
					'label' => __( 'مهر سرمقاله‌ای', 'music-wave-core' ),
				),
			),
			'music-wave/catalog-filters'   => array(
				array(
					'name'  => 'stacked',
					'label' => __( 'انباشته شده', 'music-wave-core' ),
				),
				array(
					'name'  => 'chips',
					'label' => __( 'چیپ‌های استریم', 'music-wave-core' ),
				),
			),
			'music-wave/collection-list'   => array(
				array(
					'name'  => 'tracklist',
					'label' => __( 'فهرست وینیل (شماره‌دار)', 'music-wave-core' ),
				),
			),
			'music-wave/related-releases'  => array(
				array(
					'name'  => 'editorial',
					'label' => __( 'سرمقاله‌ای', 'music-wave-core' ),
				),
				array(
					'name'  => 'vinyl',
					'label' => __( 'وینیل', 'music-wave-core' ),
				),
			),
			'music-wave/catalog-results'   => array(
				array(
					'name'  => 'editorial',
					'label' => __( 'سرمقاله‌ای', 'music-wave-core' ),
				),
			),
			'music-wave/artist-profile'    => array(
				array(
					'name'  => 'spotlight',
					'label' => __( 'نورافکن سرمقاله‌ای', 'music-wave-core' ),
				),
			),
			'music-wave/public-playlists'  => array(
				array(
					'name'       => 'cards',
					'label'      => __( 'کارت (سطحی)', 'music-wave-core' ),
					'is_default' => true,
				),
				array(
					'name'  => 'minimal',
					'label' => __( 'حداقل (بدون سطح)', 'music-wave-core' ),
				),
				array(
					'name'  => 'vinyl',
					'label' => __( 'ویترین صفحه (وینیل)', 'music-wave-core' ),
				),
			),
		);

		/*
		 * `is_default` marks the look a block already renders, so the editor can
		 * label it as the default instead of offering it as an extra style.
		 */
		return $styles;
	}

	/**
	 * Register JavaScript editor counterparts for PHP-rendered blocks.
	 *
	 * WordPress 6.6 does not automatically make PHP-only blocks available to
	 * the browser block registry. These counterparts preserve dynamic rendering
	 * while allowing templates to open in the Site Editor without an unsupported
	 * block warning.
	 *
	 * @return void
	 */
	public function enqueue_editor_blocks(): void {
		wp_enqueue_script(
			'music-wave-dynamic-blocks',
			MUSIC_WAVE_CORE_URL . 'assets/blocks.js',
			array( 'wp-block-editor', 'wp-blocks', 'wp-components', 'wp-element', 'wp-i18n', 'wp-data', 'wp-server-side-render' ),
			MUSIC_WAVE_CORE_VERSION,
			true
		);
		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'music-wave-dynamic-blocks', 'music-wave-core', MUSIC_WAVE_CORE_PATH . 'languages' );
		}
		wp_localize_script(
			'music-wave-dynamic-blocks',
			'musicWaveDynamicBlocks',
			$this->editor_blocks()
		);
		// The appearance select reads the same map `register_block_style()`
		// builds, so a variation can never surface in one panel but not the
		// other.
		wp_localize_script(
			'music-wave-dynamic-blocks',
			'musicWaveBlockStyles',
			self::style_variations()
		);
	}

	/**
	 * Inserter icon for the request form: a studio microphone with a plus.
	 *
	 * Sent as path data; blocks.js turns it into an SVG element, so the icon
	 * follows the editor's current text colour like core icons do.
	 *
	 * @return array{viewBox: string, paths: array<int, array<string, string>>}
	 */
	private static function request_form_icon(): array {
		return array(
			'viewBox' => '0 0 24 24',
			'paths'   => array(
				array(
					'd'    => 'M12 2.5a3.25 3.25 0 0 1 3.25 3.25v5.5a3.25 3.25 0 0 1-6.5 0v-5.5A3.25 3.25 0 0 1 12 2.5Z',
					'fill' => 'currentColor',
				),
				array(
					'd'           => 'M6.25 10.75a5.75 5.75 0 0 0 11.5 0M12 16.5v3.75M8.75 20.25h6.5',
					'fill'        => 'none',
					'stroke'      => 'currentColor',
					'strokeWidth' => '1.7',
				),
				array(
					'd'           => 'M19.25 2.75v4M17.25 4.75h4',
					'fill'        => 'none',
					'stroke'      => 'currentColor',
					'strokeWidth' => '1.7',
				),
			),
		);
	}

	/**
	 * Load the preview stylesheet inside the iframed editor canvas.
	 *
	 * Since WordPress 6.3 the Site Editor (and the post editor for API v3
	 * blocks) renders the content in an iframe that only receives assets
	 * enqueued on `enqueue_block_assets`; `enqueue_block_editor_assets` styles
	 * stay in the parent document. Per-block stylesheets travel through
	 * block.json `style`/`editorStyle` handles; this shared sheet covers the
	 * preview shell around them. The `is_admin()` guard keeps it off the
	 * front end, where the same action also fires.
	 *
	 * @return void
	 */
	public function enqueue_editor_canvas_styles(): void {
		if ( ! is_admin() ) {
			return;
		}
		wp_enqueue_style(
			'music-wave-editor',
			MUSIC_WAVE_CORE_URL . 'assets/editor.css',
			array(),
			MUSIC_WAVE_CORE_VERSION
		);
	}

	/**
	 * Return the client registration metadata for every Core dynamic block.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function editor_blocks(): array {
		$release_attributes         = array(
			'releaseId' => array(
				'type'    => 'integer',
				'default' => 0,
			),
			'compact'   => array(
				'type'    => 'boolean',
				'default' => false,
			),
		);
		$metadata_attributes        = array_merge(
			$release_attributes,
			array(
				'showCatalogNumber' => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'showReleaseDate'   => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'showDuration'      => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'showBpm'           => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'showKey'           => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'showArtist'        => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'showGenre'         => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'showLibraryButton' => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'showTaxonomyChips' => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'showActions'       => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'layout'            => array(
					'type'    => 'string',
					'default' => 'grid',
				),
				'showLabels'        => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'linkTerms'         => array(
					'type'    => 'boolean',
					'default' => false,
				),
				'showMood'          => array(
					'type'    => 'boolean',
					'default' => false,
				),
				'showLabel'         => array(
					'type'    => 'boolean',
					'default' => false,
				),
				'showReleaseType'   => array(
					'type'    => 'boolean',
					'default' => false,
				),
			)
		);
		$release_id_attributes      = array(
			'releaseId' => array(
				'type'    => 'integer',
				'default' => 0,
			),
		);
		$access_panel_attributes    = array_merge(
			$release_id_attributes,
			array(
				'layout'             => array(
					'type'    => 'string',
					'default' => 'banner',
				),
				'showWhenGranted'    => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'grantedMessage'     => array(
					'type'    => 'string',
					'default' => '',
				),
				'restrictedMessage'  => array(
					'type'    => 'string',
					'default' => '',
				),
				'purchaseMessage'    => array(
					'type'    => 'string',
					'default' => '',
				),
				'purchaseCtaLabel'   => array(
					'type'    => 'string',
					'default' => '',
				),
				'membershipMessage'  => array(
					'type'    => 'string',
					'default' => '',
				),
				'membershipCtaLabel' => array(
					'type'    => 'string',
					'default' => '',
				),
				'membershipCtaUrl'   => array(
					'type'    => 'string',
					'default' => '',
				),
			)
		);
		$credits_attributes         = array_merge(
			$release_id_attributes,
			array(
				'heading'     => array(
					'type'    => 'string',
					'default' => '',
				),
				'showHeading' => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'showRole'    => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'groupByRole' => array(
					'type'    => 'boolean',
					'default' => false,
				),
				'layout'      => array(
					'type'    => 'string',
					'default' => 'list',
				),
			)
		);
		$collection_attributes      = array_merge(
			$release_id_attributes,
			array(
				'heading'           => array(
					'type'    => 'string',
					'default' => '',
				),
				'showHeading'       => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'showPosition'      => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'showArtwork'       => array(
					'type'    => 'boolean',
					'default' => false,
				),
				'showDuration'      => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'showTotalDuration' => array(
					'type'    => 'boolean',
					'default' => false,
				),
				'showPreview'       => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'showDownload'      => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'groupByDisc'       => array(
					'type'    => 'boolean',
					'default' => false,
				),
			)
		);
		$catalog_filter_attributes  = array(
			'layout'            => array(
				'type'    => 'string',
				'default' => 'inline',
			),
			'showSearch'        => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showArtistFilter'  => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showGenreFilter'   => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showMoodFilter'    => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showTypeFilter'    => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showSort'          => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showReset'         => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'maxTerms'          => array(
				'type'    => 'integer',
				'default' => 50,
			),
			'searchPlaceholder' => array(
				'type'    => 'string',
				'default' => '',
			),
			'submitLabel'       => array(
				'type'    => 'string',
				'default' => '',
			),
			'resetLabel'        => array(
				'type'    => 'string',
				'default' => '',
			),
		);
		$catalog_results_attributes = array(
			'showCount' => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showChips' => array(
				'type'    => 'boolean',
				'default' => true,
			),
		);
		$preview_attributes         = array_merge(
			$release_id_attributes,
			array(
				'label'    => array(
					'type'    => 'string',
					'default' => '',
				),
				'style'    => array(
					'type'    => 'string',
					'default' => 'solid',
				),
				'showIcon' => array(
					'type'    => 'boolean',
					'default' => true,
				),
			)
		);
		$download_attributes        = array_merge(
			$release_attributes,
			array(
				'heading'         => array(
					'type'    => 'string',
					'default' => '',
				),
				'description'     => array(
					'type'    => 'string',
					'default' => '',
				),
				'showHeading'     => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'showDescription' => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'showQuality'     => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'showStream'      => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'downloadLabel'   => array(
					'type'    => 'string',
					'default' => '',
				),
				'playLabel'       => array(
					'type'    => 'string',
					'default' => '',
				),
				'loginLabel'      => array(
					'type'    => 'string',
					'default' => '',
				),
			)
		);
		$related_attributes         = array_merge(
			$release_id_attributes,
			array(
				'itemsToShow'       => array(
					'type'    => 'integer',
					'default' => 0,
				),
				'orderBy'           => array(
					'type'    => 'string',
					'default' => 'date',
				),
				'order'             => array(
					'type'    => 'string',
					'default' => 'DESC',
				),
				'sameArtistSection' => array(
					'type'    => 'string',
					'default' => 'inherit',
				),
				'similarSection'    => array(
					'type'    => 'string',
					'default' => 'inherit',
				),
				'matchGenre'        => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'matchMood'         => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'matchType'         => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'sameArtistHeading' => array(
					'type'    => 'string',
					'default' => '',
				),
				'similarHeading'    => array(
					'type'    => 'string',
					'default' => '',
				),
				'showSectionLink'   => array(
					'type'    => 'boolean',
					'default' => false,
				),
				'sectionLinkLabel'  => array(
					'type'    => 'string',
					'default' => '',
				),
				'layout'            => array(
					'type'    => 'string',
					'default' => 'grid',
				),
				'columns'           => array(
					'type'    => 'integer',
					'default' => 4,
				),
				'imageShape'        => array(
					'type'    => 'string',
					'default' => 'square',
				),
				'showArtwork'       => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'showArtist'        => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'showDate'          => array(
					'type'    => 'boolean',
					'default' => false,
				),
				'showExcerpt'       => array(
					'type'    => 'boolean',
					'default' => false,
				),
				'showPreview'       => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'showAction'        => array(
					'type'    => 'boolean',
					'default' => false,
				),
				'actionLabel'       => array(
					'type'    => 'string',
					'default' => '',
				),
			)
		);
		$preview_button_attributes  = array_merge(
			$preview_attributes,
			array(
				'compact'   => array(
					'type'    => 'boolean',
					'default' => false,
				),
				'fullWidth' => array(
					'type'    => 'boolean',
					'default' => false,
				),
			)
		);
		// Share and shuffle share the same two-attribute schema in block.json.
		$share_button_attributes   = array(
			'releaseId' => array(
				'type'    => 'integer',
				'default' => 0,
			),
			'label'     => array(
				'type'    => 'string',
				'default' => '',
			),
		);
		$artist_profile_attributes = array(
			'termId'            => array(
				'type'    => 'integer',
				'default' => 0,
			),
			'layout'            => array(
				'type'    => 'string',
				'default' => 'card',
			),
			'showImage'         => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showTitle'         => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showBio'           => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showLink'          => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showLibraryButton' => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'imageSize'         => array(
				'type'    => 'string',
				'default' => 'medium',
			),
			'imageShape'        => array(
				'type'    => 'string',
				'default' => 'rounded',
			),
			'ctaLabel'          => array(
				'type'    => 'string',
				'default' => '',
			),
			'bioLength'         => array(
				'type'    => 'integer',
				'default' => 0,
			),
			'showReleaseCount'  => array(
				'type'    => 'boolean',
				'default' => false,
			),
			'accentColor'       => array(
				'type'    => 'string',
				'default' => '',
			),
		);
		$supports                  = BlockSupport::appearance_tools();
		$artist_shelf_attributes   = array(
			'eyebrow'          => array(
				'type'    => 'string',
				'default' => '',
			),
			'heading'          => array(
				'type'    => 'string',
				'default' => '',
			),
			'description'      => array(
				'type'    => 'string',
				'default' => '',
			),
			'showHeading'      => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'source'           => array(
				'type'    => 'string',
				'default' => 'all',
			),
			'termSlug'         => array(
				'type'    => 'string',
				'default' => '',
			),
			'artistIds'        => array(
				'type'    => 'string',
				'default' => '',
			),
			'orderBy'          => array(
				'type'    => 'string',
				'default' => 'count',
			),
			'order'            => array(
				'type'    => 'string',
				'default' => 'DESC',
			),
			'itemsToShow'      => array(
				'type'    => 'integer',
				'default' => 8,
			),
			'columns'          => array(
				'type'    => 'integer',
				'default' => 4,
			),
			'layout'           => array(
				'type'    => 'string',
				'default' => 'grid',
			),
			'imageShape'       => array(
				'type'    => 'string',
				'default' => 'circle',
			),
			'imageSize'        => array(
				'type'    => 'string',
				'default' => 'medium',
			),
			'showImage'        => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showName'         => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showBio'          => array(
				'type'    => 'boolean',
				'default' => false,
			),
			'showReleaseCount' => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showFollowButton' => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'bioLength'        => array(
				'type'    => 'integer',
				'default' => 20,
			),
			'sectionUrl'       => array(
				'type'    => 'string',
				'default' => '',
			),
			'sectionLinkLabel' => array(
				'type'    => 'string',
				'default' => '',
			),
			'emptyMessage'     => array(
				'type'    => 'string',
				'default' => '',
			),
		);
		$taxonomy_shelf_attributes = array(
			'taxonomy'         => array(
				'type'    => 'string',
				'default' => 'mw_genre',
			),
			'source'           => array(
				'type'    => 'string',
				'default' => 'all',
			),
			'termIds'          => array(
				'type'    => 'string',
				'default' => '',
			),
			'orderBy'          => array(
				'type'    => 'string',
				'default' => 'count',
			),
			'itemsToShow'      => array(
				'type'    => 'integer',
				'default' => 8,
			),
			'columns'          => array(
				'type'    => 'integer',
				'default' => 4,
			),
			'layout'           => array(
				'type'    => 'string',
				'default' => 'grid',
			),
			'cardStyle'        => array(
				'type'    => 'string',
				'default' => 'colorful',
			),
			'showCount'        => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'eyebrow'          => array(
				'type'    => 'string',
				'default' => '',
			),
			'heading'          => array(
				'type'    => 'string',
				'default' => '',
			),
			'description'      => array(
				'type'    => 'string',
				'default' => '',
			),
			'showHeading'      => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'sectionUrl'       => array(
				'type'    => 'string',
				'default' => '',
			),
			'sectionLinkLabel' => array(
				'type'    => 'string',
				'default' => '',
			),
			'emptyMessage'     => array(
				'type'    => 'string',
				'default' => '',
			),
		);
		$term_hero_attributes      = array(
			'taxonomy'          => array(
				'type'    => 'string',
				'default' => '',
			),
			'termId'            => array(
				'type'    => 'integer',
				'default' => 0,
			),
			'layout'            => array(
				'type'    => 'string',
				'default' => 'banner',
			),
			'size'              => array(
				'type'    => 'string',
				'default' => 'medium',
			),
			'imageShape'        => array(
				'type'    => 'string',
				'default' => 'rounded',
			),
			'showImage'         => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showEyebrow'       => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showName'          => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showDescription'   => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showCount'         => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showFollowButton'  => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'descriptionLength' => array(
				'type'    => 'integer',
				'default' => 40,
			),
			'accentColor'       => array(
				'type'    => 'string',
				'default' => '',
			),
		);
		$queue_block_attributes    = array(
			'heading'      => array(
				'type'    => 'string',
				'default' => '',
			),
			'showArtwork'  => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showPosition' => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showArtist'   => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showControls' => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showClear'    => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'emptyMessage' => array(
				'type'    => 'string',
				'default' => '',
			),
		);
		$queue_add_attributes      = array(
			'releaseId' => array(
				'type'    => 'integer',
				'default' => 0,
			),
			'position'  => array(
				'type'    => 'string',
				'default' => 'next',
			),
			'label'     => array(
				'type'    => 'string',
				'default' => '',
			),
		);
		$music_library_attributes  = array(
			'heading'      => array(
				'type'    => 'string',
				'default' => '',
			),
			'showHeading'  => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'intro'        => array(
				'type'    => 'string',
				'default' => '',
			),
			'showFilters'  => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showCounts'   => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'layout'       => array(
				'type'    => 'string',
				'default' => 'list',
			),
			'columns'      => array(
				'type'    => 'integer',
				'default' => 4,
			),
			'itemsToShow'  => array(
				'type'    => 'integer',
				'default' => 24,
			),
			'showArtist'   => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showType'     => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showYear'     => array(
				'type'    => 'boolean',
				'default' => false,
			),
			'showRemove'   => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'emptyMessage' => array(
				'type'    => 'string',
				'default' => '',
			),
		);
		$library_button_attributes = array(
			'releaseId'  => array(
				'type'    => 'integer',
				'default' => 0,
			),
			'itemType'   => array(
				'type'    => 'string',
				'default' => 'release',
			),
			'termId'     => array(
				'type'    => 'integer',
				'default' => 0,
			),
			'label'      => array(
				'type'    => 'string',
				'default' => '',
			),
			'addedLabel' => array(
				'type'    => 'string',
				'default' => '',
			),
			'style'      => array(
				'type'    => 'string',
				'default' => 'solid',
			),
			'compact'    => array(
				'type'    => 'boolean',
				'default' => false,
			),
		);

		$editor_blocks = array(
			array(
				'name'        => 'music-wave/release-meta',
				'title'       => __( 'فرادادهٔ انتشار', 'music-wave-core' ),
				'description' => __( 'فرادادهٔ انتشار را از هسته MusicWave نمایش می‌دهد.', 'music-wave-core' ),
				'icon'        => 'list-view',
				'keywords'    => array( __( 'شماره کاتالوگ', 'music-wave-core' ), __( 'bpm', 'music-wave-core' ), __( 'جزئیات', 'music-wave-core' ) ),
				'attributes'  => $metadata_attributes,
				'usesContext' => array( 'postId', 'postType' ),
				'supports'    => $supports,
			),
			array(
				'name'        => 'music-wave/access-panel',
				'title'       => __( 'پنل دسترسی آزاد', 'music-wave-core' ),
				'description' => __( 'وضعیت دسترسی انتشار فعلی و اقدامات را نشان می‌دهد.', 'music-wave-core' ),
				'icon'        => 'lock',
				'keywords'    => array( __( 'خرید', 'music-wave-core' ), __( 'عضویت', 'music-wave-core' ), __( 'محدود', 'music-wave-core' ) ),
				'attributes'  => $access_panel_attributes,
				'usesContext' => array( 'postId', 'postType' ),
				'supports'    => $supports,
			),
			array(
				'name'        => 'music-wave/release-credits',
				'title'       => __( 'عوامل انتشار', 'music-wave-core' ),
				'description' => __( 'عوامل و نقش‌های انتشار را نشان می‌دهد.', 'music-wave-core' ),
				'icon'        => 'id',
				'keywords'    => array( __( 'نقش‌ها', 'music-wave-core' ), __( 'تهیه‌کنندگان', 'music-wave-core' ) ),
				'attributes'  => $credits_attributes,
				'usesContext' => array( 'postId', 'postType' ),
				'supports'    => $supports,
			),
			array(
				'name'        => 'music-wave/collection-list',
				'title'       => __( 'لیست قطعه مجموعه', 'music-wave-core' ),
				'description' => __( 'انتشارهای کودک را در یک مجموعه نمایش می‌دهد.', 'music-wave-core' ),
				'icon'        => 'playlist-audio',
				'keywords'    => array( __( 'قطعه‌ها', 'music-wave-core' ), __( 'قسمت‌ها', 'music-wave-core' ), __( 'دیسک', 'music-wave-core' ) ),
				'attributes'  => $collection_attributes,
				'usesContext' => array( 'postId', 'postType' ),
				'supports'    => $supports,
			),
			array(
				'name'        => 'music-wave/catalog-filters',
				'title'       => __( 'فیلترهای کاتالوگ', 'music-wave-core' ),
				'description' => __( 'فیلترهای کاتالوگ انتشار را نمایش می‌دهد.', 'music-wave-core' ),
				'icon'        => 'filter',
				'keywords'    => array( __( 'جست‌وجو', 'music-wave-core' ), __( 'آرشیو', 'music-wave-core' ) ),
				'attributes'  => $catalog_filter_attributes,
				'supports'    => $supports,
			),
			array(
				'name'        => 'music-wave/catalog-results',
				'title'       => __( 'نتایج کاتالوگ', 'music-wave-core' ),
				'description' => __( 'خلاصهٔ نتایج کاتالوگ فعلی را نمایش می‌دهد.', 'music-wave-core' ),
				'icon'        => 'chart-bar',
				'keywords'    => array( __( 'شمارش', 'music-wave-core' ), __( 'فیلترهای فعال', 'music-wave-core' ) ),
				'attributes'  => $catalog_results_attributes,
				'supports'    => $supports,
			),
			array(
				'name'        => 'music-wave/preview-player',
				'title'       => __( 'پخش‌کننده پیش‌نمایش', 'music-wave-core' ),
				'description' => __( 'پخش‌کننده پیش‌نمایش صوتی را برای انتشار نمایش می‌دهد.', 'music-wave-core' ),
				'icon'        => 'controls-play',
				'keywords'    => array( __( 'صوتی', 'music-wave-core' ), __( 'گوش کن', 'music-wave-core' ) ),
				'attributes'  => $preview_attributes,
				'usesContext' => array( 'postId', 'postType' ),
				'supports'    => $supports,
			),
			array(
				'name'        => 'music-wave/download-button',
				'title'       => __( 'دانلود ایمن', 'music-wave-core' ),
				'description' => __( 'اقدامات دانلود مجاز را برای یک انتشار نمایش می‌دهد.', 'music-wave-core' ),
				'icon'        => 'download',
				'keywords'    => array( __( 'کیفیت', 'music-wave-core' ), __( 'جریان', 'music-wave-core' ), __( 'فایل‌ها', 'music-wave-core' ) ),
				'attributes'  => $download_attributes,
				'usesContext' => array( 'postId', 'postType' ),
				'supports'    => $supports,
			),
			array(
				'name'        => 'music-wave/related-releases',
				'title'       => __( 'انتشارهای مرتبط', 'music-wave-core' ),
				'description' => __( 'انتشارهای مرتبط MusicWave را نمایش می‌دهد.', 'music-wave-core' ),
				'icon'        => 'share',
				'keywords'    => array( __( 'مشابه', 'music-wave-core' ), __( 'همان هنرمند', 'music-wave-core' ), __( 'ویترین', 'music-wave-core' ) ),
				'attributes'  => $related_attributes,
				'usesContext' => array( 'postId', 'postType' ),
				'supports'    => $supports,
			),
			array(
				'name'        => 'music-wave/artist-profile',
				'title'       => __( 'مشخصات هنرمند', 'music-wave-core' ),
				'description' => __( 'نمایهٔ طبقه‌بندی هنرمند را در صفحات بایگانی یا هر الگوی دیگری نمایش می‌دهد.', 'music-wave-core' ),
				'icon'        => 'admin-users',
				'keywords'    => array( __( 'بیوگرافی', 'music-wave-core' ), __( 'هنرمند', 'music-wave-core' ) ),
				'attributes'  => $artist_profile_attributes,
				'supports'    => $supports,
			),
			array(
				'name'        => 'music-wave/artists-shelf',
				'title'       => __( 'ویترین هنرمندان', 'music-wave-core' ),
				'description' => __( 'هنرمندان کاتالوگ را به‌صورت شبکهٔ واکنش‌گرا، نوار افقی یا فهرست جمع‌وجور با دکمه‌های دنبال‌کردن و تعداد انتشار نمایش می‌دهد.', 'music-wave-core' ),
				'icon'        => 'admin-users',
				'keywords'    => array( __( 'هنرمندان', 'music-wave-core' ), __( 'خواننده‌ها', 'music-wave-core' ), __( 'ویترین', 'music-wave-core' ), __( 'شبکه', 'music-wave-core' ), __( 'دنبال‌کردن', 'music-wave-core' ) ),
				'attributes'  => $artist_shelf_attributes,
				'supports'    => $supports,
			),
			array(
				'name'        => 'music-wave/taxonomy-shelf',
				'title'       => __( 'ویترین طبقه‌بندی', 'music-wave-core' ),
				'description' => __( 'ژانرها، حال‌وهواها یا برچسب‌ها را به‌صورت کاشی‌های مرور قابل کلیک، همراه با تعداد انتشار، در شبکه، نوار یا فهرست نمایش می‌دهد.', 'music-wave-core' ),
				'icon'        => 'tag',
				'keywords'    => array( __( 'ژانرها', 'music-wave-core' ), __( 'حال‌وهواها', 'music-wave-core' ), __( 'برچسب‌ها', 'music-wave-core' ), __( 'مرور', 'music-wave-core' ), __( 'دسته‌ها', 'music-wave-core' ), __( 'ویترین', 'music-wave-core' ) ),
				'attributes'  => $taxonomy_shelf_attributes,
				'supports'    => $supports,
			),
			array(
				'name'        => 'music-wave/term-hero',
				'title'       => __( 'معرفی اصطلاح', 'music-wave-core' ),
				'description' => __( 'سربرگ بایگانی برای صفحات هنرمند، ژانر، حال‌وهوا و برچسب؛ با بنر جلد یا ردیف فشرده شامل نام، توضیحات، تعداد انتشار و گزینهٔ دنبال‌کردن.', 'music-wave-core' ),
				'icon'        => 'format-image',
				'keywords'    => array( __( 'معرفی', 'music-wave-core' ), __( 'سربرگ', 'music-wave-core' ), __( 'هنرمند', 'music-wave-core' ), __( 'ژانر', 'music-wave-core' ), __( 'آرشیو', 'music-wave-core' ), __( 'بنر', 'music-wave-core' ) ),
				'attributes'  => $term_hero_attributes,
				'supports'    => $supports,
			),
			array(
				'name'        => 'music-wave/playback-queue',
				'title'       => __( 'صف پخش', 'music-wave-core' ),
				'description' => __( 'صف پخش پایدار شنوندهٔ واردشده با کنترل‌های مرتب‌سازی مجدد، حذف، پاک‌کردن، پخش تصادفی و تکرار؛ بدون نیاز به JavaScript.', 'music-wave-core' ),
				'icon'        => 'playlist-audio',
				'keywords'    => array( __( 'صف', 'music-wave-core' ), __( 'بعدی', 'music-wave-core' ), __( 'پخش‌کننده', 'music-wave-core' ), __( 'کلیک', 'music-wave-core' ), __( 'تکرار', 'music-wave-core' ) ),
				'attributes'  => $queue_block_attributes,
				'supports'    => $supports,
			),
			array(
				'name'        => 'music-wave/add-to-queue',
				'title'       => __( 'افزودن به صف', 'music-wave-core' ),
				'description' => __( 'انتشار فعلی یا سنجاق‌شده را به صف پخش شنونده اضافه می‌کند — بعد از قطعهٔ فعلی یا در پایان صف — بدون نیاز به JavaScript.', 'music-wave-core' ),
				'icon'        => 'controls-play',
				'keywords'    => array( __( 'صف', 'music-wave-core' ), __( 'پخش بعدی', 'music-wave-core' ), __( 'اضافه کردن', 'music-wave-core' ), __( 'پخش‌کننده', 'music-wave-core' ) ),
				'attributes'  => $queue_add_attributes,
				'usesContext' => array( 'postId', 'postType' ),
				'supports'    => $supports,
			),
			array(
				'name'        => 'music-wave/account-dashboard',
				'title'       => __( 'داشبورد موسیقی کاربر', 'music-wave-core' ),
				'description' => __( 'منطقه حساب یکپارچه: کتابخانه موسیقی، سفارش‌ها، بارگیری‌ها، آدرس‌ها، روش‌های پرداخت، عضویت و فهرست‌های پخش در یک پیمایش.', 'music-wave-core' ),
				'icon'        => 'dashboard',
				'keywords'    => array( __( 'کتابخانه', 'music-wave-core' ), __( 'حساب کاربری', 'music-wave-core' ) ),
				'attributes'  => array(
					'showLibrary'          => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showQuickLinks'       => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showStats'            => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showMembershipPanel'  => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showOrders'           => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showDownloads'        => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showAddresses'        => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showPaymentMethods'   => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showAccountDetails'   => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showSignOut'          => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'introText'            => array(
						'type'    => 'string',
						'default' => '',
					),
					'membershipHeading'    => array(
						'type'    => 'string',
						'default' => '',
					),
					'libraryHeading'       => array(
						'type'    => 'string',
						'default' => '',
					),
					'ordersHeading'        => array(
						'type'    => 'string',
						'default' => '',
					),
					'downloadsHeading'     => array(
						'type'    => 'string',
						'default' => '',
					),
					'addressesHeading'     => array(
						'type'    => 'string',
						'default' => '',
					),
					'paymentHeading'       => array(
						'type'    => 'string',
						'default' => '',
					),
					'accountHeading'       => array(
						'type'    => 'string',
						'default' => '',
					),
					'playlistsHeading'     => array(
						'type'    => 'string',
						'default' => '',
					),
					'notificationsHeading' => array(
						'type'    => 'string',
						'default' => '',
					),
					'panelOrder'           => array(
						'type'    => 'string',
						'default' => 'default',
					),
				),
				'supports'    => $supports,
			),
			array(
				'name'        => 'music-wave/membership-panel',
				'title'       => __( 'عضویت و طرح‌ها', 'music-wave-core' ),
				'description' => __( 'سطوح عضویت فعال مشتری را با انقضا و محصولات طرح VIP قابل خرید نشان می‌دهد.', 'music-wave-core' ),
				'icon'        => 'awards',
				'keywords'    => array( __( 'عضویت', 'music-wave-core' ), __( 'vip', 'music-wave-core' ), __( 'طرح‌ها', 'music-wave-core' ), __( 'اشتراک', 'music-wave-core' ) ),
				'attributes'  => array(
					'heading'        => array(
						'type'    => 'string',
						'default' => '',
					),
					'showActive'     => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showPlans'      => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showBuyButtons' => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'emptyText'      => array(
						'type'    => 'string',
						'default' => '',
					),
				),
				'supports'    => $supports,
			),
			array(
				'name'        => 'music-wave/music-library',
				'title'       => __( 'کتابخانه شخصی موسیقی', 'music-wave-core' ),
				'description' => __( 'آهنگ‌ها، آلبوم‌ها، پادکست‌ها و هنرمندانی را که بازدیدکننده ذخیره کرده‌اند را نمایش می‌دهد.', 'music-wave-core' ),
				'icon'        => 'albums',
				'keywords'    => array( __( 'مورد علاقه', 'music-wave-core' ), __( 'ذخیره‌شده', 'music-wave-core' ), __( 'مجموعه', 'music-wave-core' ) ),
				'attributes'  => $music_library_attributes,
				'supports'    => $supports,
			),
			array(
				'name'        => 'music-wave/library-button',
				'title'       => __( 'دکمه افزودن به کتابخانه', 'music-wave-core' ),
				'description' => __( 'به بازدیدکنندگان اجازه می‌دهد انتشاری را ذخیره کنند، آن را در فهرست علاقه‌مندی‌ها قرار دهند، انتشاری آینده را از قبل ذخیره کنند، یا هنرمندی را دنبال کنند.', 'music-wave-core' ),
				'icon'        => 'plus-alt',
				'keywords'    => array( __( 'ذخیره', 'music-wave-core' ), __( 'دنبال‌کردن', 'music-wave-core' ), __( 'مورد علاقه', 'music-wave-core' ) ),
				'attributes'  => $library_button_attributes,
				'usesContext' => array( 'postId', 'postType' ),
				'supports'    => $supports,
			),
			array(
				'name'        => 'music-wave/playlists',
				'title'       => __( 'فهرست‌های پخش', 'music-wave-core' ),
				'description' => __( 'به شنوندگانی که وارد سیستم شده‌اند اجازه می‌دهد فهرست‌های پخش خود را ایجاد، سفارش، اشتراک‌گذاری و حذف کنند.', 'music-wave-core' ),
				'icon'        => 'playlist-audio',
				'keywords'    => array( __( 'فهرست پخش', 'music-wave-core' ), __( 'صف', 'music-wave-core' ), __( 'مجموعه', 'music-wave-core' ) ),
				'attributes'  => array(
					'heading' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
				'supports'    => $supports,
			),
			array(
				'name'        => 'music-wave/add-to-playlist',
				'title'       => __( 'افزودن به فهرست پخش', 'music-wave-core' ),
				'description' => __( 'انتشار فعلی را به یکی از فهرست‌های پخش شنونده اضافه می‌کند.', 'music-wave-core' ),
				'icon'        => 'plus-alt',
				'keywords'    => array( __( 'فهرست پخش', 'music-wave-core' ), __( 'ذخیره', 'music-wave-core' ), __( 'صف', 'music-wave-core' ) ),
				'attributes'  => array(
					'releaseId' => array(
						'type'    => 'integer',
						'default' => 0,
					),
					'label'     => array(
						'type'    => 'string',
						'default' => '',
					),
				),
				'usesContext' => array( 'postId', 'postType' ),
				'supports'    => $supports,
			),
			array(
				'name'        => 'music-wave/public-playlists',
				'title'       => __( 'فهرست پخش عمومی', 'music-wave-core' ),
				'description' => __( 'فهرست‌های پخش عمومی جامعه را با جست‌وجو و صفحه‌بندی مرور و پخش کنید.', 'music-wave-core' ),
				'icon'        => 'groups',
				'keywords'    => array( __( 'فهرست پخش', 'music-wave-core' ), __( 'جامعه', 'music-wave-core' ), __( 'عمومی', 'music-wave-core' ) ),
				'attributes'  => array(
					'eyebrow'           => array(
						'type'    => 'string',
						'default' => '',
					),
					'heading'           => array(
						'type'    => 'string',
						'default' => '',
					),
					'showHeading'       => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'intro'             => array(
						'type'    => 'string',
						'default' => '',
					),
					'itemsToShow'       => array(
						'type'    => 'integer',
						'default' => 12,
					),
					'columns'           => array(
						'type'    => 'integer',
						'default' => 3,
					),
					'layout'            => array(
						'type'    => 'string',
						'default' => 'grid',
					),
					'orderby'           => array(
						'type'    => 'string',
						'default' => 'updated_at',
					),
					'imageShape'        => array(
						'type'    => 'string',
						'default' => 'square',
					),
					'showSearch'        => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showCount'         => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'searchPlaceholder' => array(
						'type'    => 'string',
						'default' => '',
					),
					'emptyMessage'      => array(
						'type'    => 'string',
						'default' => '',
					),
					'showArt'           => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showAuthor'        => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showUpdated'       => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showPlayButton'    => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showToggle'        => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showPagination'    => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'sectionUrl'        => array(
						'type'    => 'string',
						'default' => '',
					),
					'sectionLinkLabel'  => array(
						'type'    => 'string',
						'default' => '',
					),
				),
				'supports'    => $supports,
			),
			array(
				'name'        => 'music-wave/continue-listening',
				'title'       => __( 'به گوش‌دادن ادامه دهید', 'music-wave-core' ),
				'description' => __( 'به هر شنونده انتشارهای اخیراً پخش‌شده و در دست اجرا خود را نشان می‌دهد، با انتخاب اول رضایت برای بازدیدکنندگانی که سابقهٔ گوش‌دادن را فعال نکرده‌اند.', 'music-wave-core' ),
				'icon'        => 'controls-back',
				'keywords'    => array( __( 'ادامه', 'music-wave-core' ), __( 'اخیراً پخش‌شده', 'music-wave-core' ), __( 'تاریخ', 'music-wave-core' ), __( 'سابقه', 'music-wave-core' ), __( 'گوش‌دادن', 'music-wave-core' ) ),
				'attributes'  => array(
					'heading'            => array(
						'type'    => 'string',
						'default' => '',
					),
					'showHeading'        => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'intro'              => array(
						'type'    => 'string',
						'default' => '',
					),
					'source'             => array(
						'type'    => 'string',
						'default' => 'continue',
					),
					'itemsToShow'        => array(
						'type'    => 'integer',
						'default' => 8,
					),
					'columns'            => array(
						'type'    => 'integer',
						'default' => 4,
					),
					'layout'             => array(
						'type'    => 'string',
						'default' => 'scroll',
					),
					'imageShape'         => array(
						'type'    => 'string',
						'default' => 'square',
					),
					'showArtwork'        => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showArtist'         => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showWhen'           => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showPreview'        => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'sectionUrl'         => array(
						'type'    => 'string',
						'default' => '',
					),
					'sectionLinkLabel'   => array(
						'type'    => 'string',
						'default' => '',
					),
					'emptyMessage'       => array(
						'type'    => 'string',
						'default' => '',
					),
					'guestMessage'       => array(
						'type'    => 'string',
						'default' => '',
					),
					'consentMessage'     => array(
						'type'    => 'string',
						'default' => '',
					),
					'consentButtonLabel' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
				'supports'    => $supports,
			),
			array(
				'name'        => 'music-wave/preview-button',
				'title'       => __( 'دکمه پیش‌نمایش', 'music-wave-core' ),
				'description' => __( 'یک دکمه پیش‌نمایش صوتی انتشار را نمایش می‌دهد.', 'music-wave-core' ),
				'icon'        => 'controls-play',
				'keywords'    => array( __( 'پخش', 'music-wave-core' ), __( 'گوش کن', 'music-wave-core' ) ),
				'attributes'  => $preview_button_attributes,
				'usesContext' => array( 'postId', 'postType' ),
				'supports'    => $supports,
			),
			array(
				'name'        => 'music-wave/share-button',
				'title'       => __( 'اشتراک‌گذاری انتشار', 'music-wave-core' ),
				'description' => __( 'دکمه اشتراک‌گذاری بومی برای انتشار فعلی با Web Share API و بازگشت کپی پیوند.', 'music-wave-core' ),
				'icon'        => 'share',
				'keywords'    => array( __( 'اشتراک', 'music-wave-core' ), __( 'share', 'music-wave-core' ), __( 'پیوند', 'music-wave-core' ) ),
				'attributes'  => $share_button_attributes,
				'usesContext' => array( 'postId', 'postType' ),
				'supports'    => $supports,
			),
			array(
				'name'        => 'music-wave/shuffle-button',
				'title'       => __( 'پخش تصادفی', 'music-wave-core' ),
				'description' => __( 'انتشار فعلی یا مجموعه را به صورت تصادفی پخش می‌کند.', 'music-wave-core' ),
				'icon'        => 'randomize',
				'keywords'    => array( __( 'تصادفی', 'music-wave-core' ), __( 'shuffle', 'music-wave-core' ), __( 'پخش', 'music-wave-core' ) ),
				'attributes'  => $share_button_attributes,
				'usesContext' => array( 'postId', 'postType' ),
				'supports'    => $supports,
			),
			array(
				'name'        => 'music-wave/request-form',
				'title'       => __( 'فرم درخواست آهنگ و همکاری', 'music-wave-core' ),
				'description' => __( 'فرم عمومی سفارش آهنگ اختصاصی و پیشنهاد همکاری؛ با حالت ترکیبی یا اختصاصی (فقط سفارش آهنگ / فقط همکاری). درخواست‌ها در بخش «درخواست‌ها و همکاری» مدیریت می‌شوند.', 'music-wave-core' ),
				'icon'        => self::request_form_icon(),
				'keywords'    => array( __( 'درخواست', 'music-wave-core' ), __( 'همکاری', 'music-wave-core' ), __( 'سفارش آهنگ', 'music-wave-core' ), __( 'آهنگ اختصاصی', 'music-wave-core' ) ),
				'attributes'  => array(
					'mode'            => array(
						'type'    => 'string',
						'enum'    => array( 'both', 'song', 'collab' ),
						'default' => 'both',
					),
					'defaultType'     => array(
						'type'    => 'string',
						'default' => '',
					),
					'showTypeChips'   => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showRoles'       => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'defaultRole'     => array(
						'type'    => 'string',
						'default' => '',
					),
					'eyebrow'         => array(
						'type'    => 'string',
						'default' => '',
					),
					'heading'         => array(
						'type'    => 'string',
						'default' => '',
					),
					'intro'           => array(
						'type'    => 'string',
						'default' => '',
					),
					'showHeading'     => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showSteps'       => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showHighlights'  => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'highlight1Title' => array(
						'type'    => 'string',
						'default' => '',
					),
					'highlight1Text'  => array(
						'type'    => 'string',
						'default' => '',
					),
					'highlight2Title' => array(
						'type'    => 'string',
						'default' => '',
					),
					'highlight2Text'  => array(
						'type'    => 'string',
						'default' => '',
					),
					'highlight3Title' => array(
						'type'    => 'string',
						'default' => '',
					),
					'highlight3Text'  => array(
						'type'    => 'string',
						'default' => '',
					),
					'step1'           => array(
						'type'    => 'string',
						'default' => '',
					),
					'step2'           => array(
						'type'    => 'string',
						'default' => '',
					),
					'step3'           => array(
						'type'    => 'string',
						'default' => '',
					),
					'privacyNote'     => array(
						'type'    => 'string',
						'default' => '',
					),
					'showBudget'      => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showDeadline'    => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showPhone'       => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showLinks'       => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'submitLabel'     => array(
						'type'    => 'string',
						'default' => '',
					),
					'layout'          => array(
						'type'    => 'string',
						'enum'    => array( 'split', 'stacked' ),
						'default' => 'split',
					),
				),
				'supports'    => $supports,
			),
		);

		// Enrich the hand-rolled editor registry with block.json metadata that
		// must stay defined in exactly one place: the metadata file itself.
		foreach ( $editor_blocks as $index => $editor_block ) {
			$metadata = BlockMetadata::load( (string) $editor_block['name'] );
			if ( ! is_array( $metadata ) ) {
				continue;
			}
			if ( isset( $metadata['category'] ) ) {
				$editor_blocks[ $index ]['category'] = (string) $metadata['category'];
			}
			if ( isset( $metadata['example'] ) && is_array( $metadata['example'] ) ) {
				$editor_blocks[ $index ]['example'] = $metadata['example'];
			}
		}

		return $editor_blocks;
	}
}
